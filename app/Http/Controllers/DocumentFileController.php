<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\DestroyDocumentFileRequest;
use App\Http\Requests\StoreCorrectionVersionRequest;
use App\Http\Requests\StoreDocumentFileRequest;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Services\AuditLogService;
use App\Services\DocumentFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentFileController extends Controller
{
    public function __construct(
        private readonly DocumentFileService $files,
        private readonly AuditLogService $audit,
    ) {}

    public function store(StoreDocumentFileRequest $request, Document $document): RedirectResponse
    {
        $this->files->upload(
            $request->user(),
            $document,
            $request->file('files', []),
            $request->integer('lock_version'),
        );

        return back()->with('status', 'Archivos adjuntados correctamente.');
    }

    public function storeCorrection(
        StoreCorrectionVersionRequest $request,
        Document $document,
    ): RedirectResponse {
        $version = $this->files->prepareCorrection(
            $request->user(),
            $document,
            $request->file('files', []),
            $request->integer('document_lock_version'),
        );

        return back()->with('status', "La versión {$version->version_number} fue preparada correctamente. Revise sus archivos antes de enviarla.");
    }

    public function download(Document $document, DocumentFile $documentFile): StreamedResponse
    {
        $documentFile->loadMissing('documentVersion');
        abort_unless($documentFile->documentVersion->document_id === $document->getKey(), 404);
        Gate::authorize('downloadFile', $document);
        abort_unless(Storage::disk($documentFile->disk)->exists($documentFile->path), 404);

        $this->audit->record(auth()->user(), AuditAction::FILE_DOWNLOADED, $documentFile, newValues: [
            'document_id' => $document->getKey(),
            'document_public_id' => $document->public_id,
            'file_public_id' => $documentFile->public_id,
            'version' => $documentFile->documentVersion->version_number,
            'confidentiality' => $document->confidentiality,
        ]);

        return Storage::disk($documentFile->disk)->download(
            $documentFile->path,
            $documentFile->original_name,
            ['Content-Type' => $documentFile->detected_mime_type],
        );
    }

    public function destroy(
        DestroyDocumentFileRequest $request,
        Document $document,
        DocumentFile $documentFile,
    ): RedirectResponse {
        $this->files->remove(
            $request->user(),
            $document,
            $documentFile,
            $request->integer('lock_version'),
        );

        return back()->with('status', 'Archivo retirado correctamente.');
    }
}
