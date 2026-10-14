<?php

namespace App\Services;

use App\Enums\DocumentEventType;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;

class DocumentFileService
{
    public function __construct(private readonly DocumentEventService $events) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, DocumentFile>
     */
    public function upload(User $actor, Document $document, array $files, int $expectedVersion): array
    {
        Gate::forUser($actor)->authorize('manageFiles', $document);

        $maxFiles = (int) config('documents.uploads.max_files_per_request');

        if ($files === [] || count($files) > $maxFiles) {
            throw ValidationException::withMessages([
                'files' => "Seleccione entre 1 y {$maxFiles} archivos.",
            ]);
        }

        $preparedFiles = array_map(
            fn (UploadedFile $file): array => $this->validateAndDescribe($file),
            $files,
        );
        $disk = (string) config('documents.disk');
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($actor, $document, $preparedFiles, $expectedVersion, $disk, &$storedPaths): array {
                $this->bumpDraftVersion($document, $expectedVersion);

                $version = DocumentVersion::query()->firstOrCreate(
                    [
                        'document_id' => $document->getKey(),
                        'version_number' => 1,
                    ],
                    [
                        'uploaded_by' => $actor->getKey(),
                        'note' => null,
                    ],
                );

                $created = [];

                foreach ($preparedFiles as $prepared) {
                    $storedName = Str::uuid()->toString().'.'.$prepared['extension'];
                    $directory = $document->public_id.'/version-1';
                    $path = Storage::disk($disk)->putFileAs($directory, $prepared['file'], $storedName);

                    if (! is_string($path) || $path === '') {
                        throw new RuntimeException('No fue posible almacenar el archivo en el disco privado.');
                    }

                    $storedPaths[] = $path;
                    $storedSize = Storage::disk($disk)->size($path);

                    if ($storedSize !== $prepared['size_bytes']) {
                        throw new RuntimeException('El tamaño almacenado no coincide con el archivo recibido.');
                    }

                    $createdFile = DocumentFile::query()->create([
                        'document_version_id' => $version->getKey(),
                        'disk' => $disk,
                        'path' => $path,
                        'original_name' => $prepared['original_name'],
                        'stored_name' => $storedName,
                        'extension' => $prepared['extension'],
                        'detected_mime_type' => $prepared['detected_mime_type'],
                        'size_bytes' => $storedSize,
                        'sha256' => $this->hashStoredFile($disk, $path),
                        'uploaded_by' => $actor->getKey(),
                    ]);
                    $created[] = $createdFile;

                    $this->events->record(
                        $document,
                        DocumentEventType::FILE_ADDED,
                        $actor,
                        metadata: [
                            'original_name' => $createdFile->original_name,
                            'version' => $version->version_number,
                            'size_bytes' => $createdFile->size_bytes,
                        ],
                    );
                }

                return $created;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function prepareCorrection(
        User $actor,
        Document $document,
        array $files,
        int $expectedDocumentVersion,
    ): DocumentVersion {
        Gate::forUser($actor)->authorize('createCorrectionVersion', $document);

        $maxFiles = (int) config('documents.uploads.max_files_per_request');

        if ($files === [] || count($files) > $maxFiles) {
            throw ValidationException::withMessages([
                'files' => "Seleccione entre 1 y {$maxFiles} archivos.",
            ]);
        }

        $preparedFiles = array_map(
            fn (UploadedFile $file): array => $this->validateAndDescribe($file),
            $files,
        );
        $disk = (string) config('documents.disk');
        $storedPaths = [];

        try {
            return DB::transaction(function () use (
                $actor,
                $document,
                $preparedFiles,
                $expectedDocumentVersion,
                $disk,
                &$storedPaths,
            ): DocumentVersion {
                $locked = Document::query()->lockForUpdate()->findOrFail($document->getKey());

                Gate::forUser($actor)->authorize('createCorrectionVersion', $locked);

                if ($locked->lock_version !== $expectedDocumentVersion) {
                    throw ValidationException::withMessages([
                        'document_lock_version' => 'El documento cambió en otra sesión. Recargue la página antes de preparar la corrección.',
                    ]);
                }

                $returnedRecipient = $locked->recipients()
                    ->where('requires_response', true)
                    ->where('status', 'returned')
                    ->lockForUpdate()
                    ->first();

                if (! $returnedRecipient) {
                    throw ValidationException::withMessages([
                        'document' => 'No existe una devolución formal pendiente de corrección.',
                    ]);
                }

                if ($this->pendingCorrectionVersion($locked)) {
                    throw ValidationException::withMessages([
                        'document' => 'Ya existe una corrección preparada. Revísela y envíela antes de crear otra versión.',
                    ]);
                }

                $versionNumber = ((int) $locked->versions()->max('version_number')) + 1;
                $version = DocumentVersion::query()->create([
                    'document_id' => $locked->getKey(),
                    'version_number' => $versionNumber,
                    'uploaded_by' => $actor->getKey(),
                    'note' => 'Corrección posterior a devolución',
                ]);

                foreach ($preparedFiles as $prepared) {
                    $storedName = Str::uuid()->toString().'.'.$prepared['extension'];
                    $directory = $locked->public_id.'/version-'.$versionNumber;
                    $path = Storage::disk($disk)->putFileAs($directory, $prepared['file'], $storedName);

                    if (! is_string($path) || $path === '') {
                        throw new RuntimeException('No fue posible almacenar el archivo en el disco privado.');
                    }

                    $storedPaths[] = $path;
                    $storedSize = Storage::disk($disk)->size($path);

                    if ($storedSize !== $prepared['size_bytes']) {
                        throw new RuntimeException('El tamaño almacenado no coincide con el archivo recibido.');
                    }

                    $createdFile = DocumentFile::query()->create([
                        'document_version_id' => $version->getKey(),
                        'disk' => $disk,
                        'path' => $path,
                        'original_name' => $prepared['original_name'],
                        'stored_name' => $storedName,
                        'extension' => $prepared['extension'],
                        'detected_mime_type' => $prepared['detected_mime_type'],
                        'size_bytes' => $storedSize,
                        'sha256' => $this->hashStoredFile($disk, $path),
                        'uploaded_by' => $actor->getKey(),
                    ]);

                    $this->events->record(
                        $locked,
                        DocumentEventType::FILE_ADDED,
                        $actor,
                        metadata: [
                            'original_name' => $createdFile->original_name,
                            'version' => $versionNumber,
                            'size_bytes' => $createdFile->size_bytes,
                        ],
                    );
                }

                $locked->forceFill([
                    'lock_version' => $locked->lock_version + 1,
                    'updated_at' => now(),
                ])->save();

                $this->events->record(
                    $locked,
                    DocumentEventType::DOCUMENT_VERSION_CREATED,
                    $actor,
                    fromStatus: DocumentStatus::RETURNED->value,
                    toStatus: DocumentStatus::RETURNED->value,
                    metadata: [
                        'version' => $versionNumber,
                        'file_count' => count($preparedFiles),
                    ],
                );

                return $version->load(['files.uploader', 'uploader']);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }
    }

    public function pendingCorrectionVersion(Document $document): ?DocumentVersion
    {
        $createdEvent = $document->events()
            ->where('event_type', DocumentEventType::DOCUMENT_VERSION_CREATED->value)
            ->latest('id')
            ->first();

        if (! $createdEvent) {
            return null;
        }

        $sentEvent = $document->events()
            ->where('event_type', DocumentEventType::CORRECTION_SENT->value)
            ->latest('id')
            ->first();

        if ($sentEvent && $sentEvent->getKey() > $createdEvent->getKey()) {
            return null;
        }

        $versionNumber = (int) ($createdEvent->metadata['version'] ?? 0);

        return $document->versions()
            ->with(['files.uploader', 'uploader'])
            ->where('version_number', $versionNumber)
            ->whereHas('files')
            ->first();
    }

    public function remove(User $actor, Document $document, DocumentFile $file, int $expectedVersion): void
    {
        Gate::forUser($actor)->authorize('manageFiles', $document);

        $trashPath = null;
        $originalPath = null;
        $disk = null;

        try {
            DB::transaction(function () use ($actor, $document, $file, $expectedVersion, &$trashPath, &$originalPath, &$disk): void {
                $lockedFile = DocumentFile::query()
                    ->with('documentVersion')
                    ->lockForUpdate()
                    ->find($file->getKey());

                if (! $lockedFile || $lockedFile->documentVersion->document_id !== $document->getKey()) {
                    throw ValidationException::withMessages([
                        'file' => 'El archivo no pertenece a este documento.',
                    ]);
                }

                $this->bumpDraftVersion($document, $expectedVersion);

                $disk = $lockedFile->disk;
                $originalPath = $lockedFile->path;

                if (! Storage::disk($disk)->exists($originalPath)) {
                    throw ValidationException::withMessages([
                        'file' => 'El archivo físico no está disponible. No se modificó el registro.',
                    ]);
                }

                $trashPath = '.trash/'.Str::uuid()->toString();

                if (! Storage::disk($disk)->move($originalPath, $trashPath)) {
                    throw new RuntimeException('No fue posible preparar el retiro del archivo.');
                }

                $version = $lockedFile->documentVersion;
                $this->events->record(
                    $document,
                    DocumentEventType::FILE_REMOVED,
                    $actor,
                    metadata: [
                        'original_name' => $lockedFile->original_name,
                        'version' => $version->version_number,
                        'size_bytes' => $lockedFile->size_bytes,
                    ],
                );
                $lockedFile->delete();

                if (! DocumentFile::query()->where('document_version_id', $version->getKey())->exists()) {
                    $version->delete();
                }
            });
        } catch (Throwable $exception) {
            if ($disk && $trashPath && $originalPath
                && Storage::disk($disk)->exists($trashPath)
                && ! Storage::disk($disk)->exists($originalPath)) {
                try {
                    Storage::disk($disk)->move($trashPath, $originalPath);
                } catch (Throwable $restoreException) {
                    report($restoreException);
                }
            }

            throw $exception;
        }

        if ($disk && $trashPath && Storage::disk($disk)->exists($trashPath)) {
            try {
                Storage::disk($disk)->delete($trashPath);
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }
        }
    }

    /** @return array{file: UploadedFile, original_name: string, extension: string, detected_mime_type: string, size_bytes: int} */
    private function validateAndDescribe(UploadedFile $file): array
    {
        $originalName = $file->getClientOriginalName();

        if ($originalName === ''
            || str_contains($originalName, '/')
            || str_contains($originalName, '\\')
            || preg_match('/[\x00-\x1F\x7F]/u', $originalName)
            || mb_strlen($originalName) > 255) {
            throw ValidationException::withMessages([
                'files' => 'Uno de los archivos tiene un nombre no permitido.',
            ]);
        }

        $extension = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = config('documents.uploads.extensions', []);

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'files' => "El formato .{$extension} no está permitido.",
            ]);
        }

        $size = $file->getSize();
        $maxBytes = (int) config('documents.uploads.max_size_kb') * 1024;

        if (! is_int($size) || $size <= 0 || $size > $maxBytes) {
            throw ValidationException::withMessages([
                'files' => 'El archivo está vacío o supera el tamaño máximo permitido.',
            ]);
        }

        $detectedMime = Str::lower((string) $file->getMimeType());
        $allowedMimes = config("documents.uploads.mime_types_by_extension.{$extension}", []);

        if ($detectedMime === 'application/octet-stream'
            || ! in_array($detectedMime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'files' => "El contenido de {$originalName} no coincide con su formato.",
            ]);
        }

        $this->validateFileStructure($file, $extension, $detectedMime, $originalName);

        return [
            'file' => $file,
            'original_name' => $originalName,
            'extension' => $extension,
            'detected_mime_type' => $detectedMime,
            'size_bytes' => $size,
        ];
    }

    private function validateFileStructure(UploadedFile $file, string $extension, string $mime, string $name): void
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['files' => "No fue posible leer {$name}."]);
        }

        if ($extension === 'pdf' && file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            throw ValidationException::withMessages(['files' => "El contenido de {$name} no es un PDF válido."]);
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $image = @getimagesize($path);
            $expectedMime = in_array($extension, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png';

            if (! is_array($image) || ($image['mime'] ?? null) !== $expectedMime || $mime !== $expectedMime) {
                throw ValidationException::withMessages(['files' => "La imagen {$name} no es válida."]);
            }
        }

        if (in_array($extension, ['docx', 'xlsx', 'pptx', 'zip'], true)) {
            $this->validateZipStructure($path, $extension, $name);
        }

        if (in_array($extension, ['doc', 'xls', 'ppt'], true)) {
            $signature = file_get_contents($path, false, null, 0, 8);

            if ($signature !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
                throw ValidationException::withMessages(['files' => "El archivo de Office {$name} no es válido."]);
            }
        }
    }

    private function validateZipStructure(string $path, string $extension, string $name): void
    {
        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::CHECKCONS) !== true) {
            throw ValidationException::withMessages(['files' => "El archivo comprimido {$name} no es válido."]);
        }

        if ($extension !== 'zip') {
            $requiredEntry = match ($extension) {
                'docx' => 'word/document.xml',
                'xlsx' => 'xl/workbook.xml',
                'pptx' => 'ppt/presentation.xml',
            };

            if ($archive->locateName('[Content_Types].xml') === false
                || $archive->locateName($requiredEntry) === false) {
                $archive->close();

                throw ValidationException::withMessages([
                    'files' => "La estructura interna de {$name} no corresponde a su formato.",
                ]);
            }
        }

        $archive->close();
    }

    private function hashStoredFile(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('No fue posible verificar la integridad del archivo almacenado.');
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    private function bumpDraftVersion(Document $document, int $expectedVersion): void
    {
        $updated = Document::query()
            ->whereKey($document->getKey())
            ->where('general_status', DocumentStatus::DRAFT->value)
            ->where('lock_version', $expectedVersion)
            ->update([
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw ValidationException::withMessages([
                'lock_version' => 'Los archivos cambiaron en otra sesión. Recargue la página antes de continuar.',
            ]);
        }

        $document->refresh();
    }
}
