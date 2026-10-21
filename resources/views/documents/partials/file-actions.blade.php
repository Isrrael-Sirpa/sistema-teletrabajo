@can('downloadFile', $document)
    <a href="{{ route('documents.files.download', [$document, $file, 'presentation' => 'inline']) }}" data-file-viewer-open="{{ $file->public_id }}" aria-haspopup="dialog" aria-label="Visualizar archivo: {{ $file->original_name }}" class="ft-button-secondary text-sm">Visualizar archivo</a>
    <a href="{{ route('documents.files.download', [$document, $file]) }}" aria-label="Descargar archivo: {{ $file->original_name }}" class="ft-button-secondary text-sm">Descargar archivo</a>
@endcan
