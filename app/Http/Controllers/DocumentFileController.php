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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

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

    public function download(Request $request, Document $document, DocumentFile $documentFile): Response
    {
        $documentFile->loadMissing('documentVersion');
        abort_unless($documentFile->documentVersion->document_id === $document->getKey(), 404);
        Gate::authorize('downloadFile', [$document, $documentFile]);
        $validated = $request->validate([
            'presentation' => ['sometimes', 'required', 'string', Rule::in(['attachment', 'inline'])],
        ]);
        $presentation = $validated['presentation'] ?? 'attachment';
        $privateHeaders = [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        if (! Storage::disk($documentFile->disk)->exists($documentFile->path)) {
            return response()->view('documents.files.unavailable', [
                'document' => $document,
                'file' => $documentFile,
                'missing' => true,
            ], 404, $privateHeaders);
        }

        if ($presentation === 'inline' && ! $documentFile->supportsInlinePreview()) {
            return response()->view('documents.files.unavailable', [
                'document' => $document,
                'file' => $documentFile,
                'missing' => false,
            ], 415, $privateHeaders);
        }

        $this->audit->record(auth()->user(), AuditAction::FILE_DOWNLOADED, $documentFile, newValues: [
            'document_id' => $document->getKey(),
            'document_public_id' => $document->public_id,
            'file_public_id' => $documentFile->public_id,
            'version' => $documentFile->documentVersion->version_number,
            'confidentiality' => $document->confidentiality,
        ]);

        // Only the authorized, supported inline response may be framed by this origin.
        $request->attributes->set('authorized_private_file_inline', $presentation === 'inline');

        return Storage::disk($documentFile->disk)->response(
            $documentFile->path,
            $documentFile->original_name,
            ['Content-Type' => $documentFile->detected_mime_type, ...$privateHeaders],
            $presentation,
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
