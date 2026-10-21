<dialog data-file-viewer class="ft-file-viewer" aria-labelledby="file-viewer-title" aria-describedby="file-viewer-name">
    <header class="flex items-start justify-between gap-4 border-b border-slate-200 p-4">
        <div class="min-w-0">
            <h2 id="file-viewer-title" class="text-lg font-bold text-slate-950">Archivos adjuntos</h2>
            <p data-file-viewer-count class="mt-1 text-sm text-slate-600" aria-live="polite"></p>
        </div>
        <button type="button" data-file-viewer-close class="ft-button-secondary shrink-0" autofocus>Cerrar visor</button>
    </header>
    <div class="ft-file-viewer-body">
        <nav class="ft-file-viewer-list" aria-label="Seleccionar archivo adjunto">
            @foreach ($documentFiles as $file)
                <button type="button" class="ft-file-choice" data-file-viewer-item
                    data-file-id="{{ $file->public_id }}"
                    data-file-name="{{ $file->original_name }}"
                    data-file-mime="{{ $file->detected_mime_type }}"
                    data-file-supported="{{ $file->supportsInlinePreview() ? '1' : '0' }}"
                    data-file-preview-url="{{ route('documents.files.download', [$document, $file, 'presentation' => 'inline']) }}"
                    data-file-download-url="{{ route('documents.files.download', [$document, $file]) }}">
                    <span class="block break-words font-bold">{{ $file->original_name }}</span>
                    <span class="mt-1 block text-xs">Versión {{ $document->versions->firstWhere('id', $file->document_version_id)->version_number }} · {{ strtoupper($file->extension) }} · {{ number_format($file->size_bytes / ($file->size_bytes >= 1048576 ? 1048576 : 1024), 1, ',', '.') }} {{ $file->size_bytes >= 1048576 ? 'MB' : 'KB' }}</span>
                </button>
            @endforeach
        </nav>
        <section class="ft-file-viewer-selected" aria-labelledby="file-viewer-name">
            <h3 id="file-viewer-name" data-file-viewer-name class="break-words p-3 text-sm font-bold text-slate-950"></h3>
            <p data-file-viewer-status role="status" class="px-4 pb-3 text-sm text-slate-700"></p>
            <div data-file-viewer-content class="ft-file-viewer-content"></div>
        </section>
    </div>
    <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 p-3">
        <button type="button" data-file-viewer-previous class="ft-button-secondary text-sm">← Anterior</button>
        <a data-file-viewer-download class="ft-button-primary text-sm">Descargar archivo</a>
        <button type="button" data-file-viewer-next class="ft-button-secondary text-sm">Siguiente →</button>
    </footer>
</dialog>
