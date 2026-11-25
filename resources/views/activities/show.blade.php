@php
    $relatedFiles = collect();

    if ($activity->document) {
        $relatedFiles = $activity->document->versions
            ->flatMap(fn ($version) => $version->files)
            ->values();
    }
@endphp

<x-layouts.authenticated :title="$activity->title">
    <div class="ft-page-header">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-slate-600">{{ $activity->activity_type->label() }}</p>
            <h1 class="ft-page-title mt-1 max-w-4xl">{{ $activity->title }}</h1>
        </div>
        <a href="{{ route('activities.index') }}" class="ft-button-secondary">Volver a actividades</a>
    </div>

    <div class="ft-card mt-6 p-5 sm:p-7">
        <div class="flex flex-wrap gap-2"><span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-900">{{ $activity->status->label() }}</span><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-800">Prioridad {{ mb_strtolower($activity->priority->label()) }}</span></div>
        <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="font-bold text-slate-800">Unidad</dt><dd class="mt-1 text-slate-700">{{ $activity->organizationalUnit->name }}</dd></div>
            <div><dt class="font-bold text-slate-800">Responsable principal</dt><dd class="mt-1 text-slate-700">{{ $activity->primaryAssignee()?->name }}</dd></div>
            <div><dt class="font-bold text-slate-800">Fecha límite</dt><dd class="mt-1 text-slate-700">{{ $activity->due_at?->format('d/m/Y') ?? 'Sin fecha límite' }}</dd></div>
            <div><dt class="font-bold text-slate-800">Asignada por</dt><dd class="mt-1 text-slate-700">{{ $activity->assigner->name }}</dd></div>
        </dl>
        <div class="mt-5 border-t border-slate-200 pt-5"><p class="font-bold text-slate-900">Indicaciones</p><p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ $activity->instructions }}</p></div>
        @if($activity->document && Gate::allows('view', $activity->document))<a href="{{ route('documents.show', $activity->document) }}" class="ft-button-secondary mt-5">Abrir correspondencia relacionada</a>@endif
    </div>

    @if($activity->review_note && $activity->status === App\Enums\ActivityStatus::OBSERVED)
        <div role="status" class="ft-alert ft-alert-warning mt-6"><p class="font-bold">Observaciones de Jefatura</p><p class="mt-1 whitespace-pre-wrap">{{ $activity->review_note }}</p></div>
    @endif

    @if($activity->document && $relatedFiles->isNotEmpty())
        <section class="ft-card mt-6 p-5 sm:p-7" id="related-files">
            <h2 class="text-lg font-bold text-slate-950">Archivos de la correspondencia relacionada</h2>
            <div class="mt-4 space-y-2">
                @foreach($relatedFiles as $file)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-2 text-sm">
                        <span class="min-w-0 break-words">{{ $file->original_name }}</span>
                        @can('downloadFile', [$activity->document, $file])
                            <div class="flex shrink-0 flex-wrap gap-2">
                                @if($file->supportsInlinePreview())
                                    <a href="#related-files" data-file-viewer-open="document-{{ $file->public_id }}" class="ft-button-secondary">Ver</a>
                                @endif
                                <a href="{{ route('documents.files.download', [$activity->document, $file]) }}" class="ft-button-secondary">Descargar</a>
                            </div>
                        @endcan
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="ft-card mt-6 p-5 sm:p-7" aria-labelledby="files-title">
        <h2 id="files-title" class="text-lg font-bold text-slate-950">Archivos de la actividad</h2>
        <div class="mt-4 space-y-2">
            @forelse($activity->files as $file)<div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-2 text-sm"><span>{{ $file->original_name }} <span class="text-slate-500">· {{ $file->uploader->displayName() }}</span></span><div class="flex flex-wrap gap-2">@if(in_array($file->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true))<a href="#files-title" data-file-viewer-open="activity-{{ $file->id }}" class="ft-button-secondary">Ver</a>@endif<a href="{{ route('activities.files.download', [$activity, $file]) }}" class="ft-button-secondary">Descargar</a>@if($activity->status->isOpen() && $file->uploaded_by === auth()->id())<form method="POST" action="{{ route('activities.files.destroy', [$activity, $file]) }}">@csrf @method('DELETE')<button class="ft-button-secondary text-red-800">Quitar</button></form>@endif</div></div>@empty<p class="text-sm text-slate-600">No hay archivos adjuntos.</p>@endforelse
        </div>
        @can('attachFile', $activity)
            <form method="POST" action="{{ route('activities.files.store', $activity) }}" enctype="multipart/form-data" class="mt-5 flex flex-wrap items-end gap-3 border-t border-slate-200 pt-5">@csrf<label for="activity_files" class="block text-sm font-bold text-slate-800">Adjuntar archivos propios<input id="activity_files" type="file" name="files[]" multiple required class="mt-1 block w-full rounded-lg border border-blue-300 bg-blue-50 p-2 text-sm font-normal file:mr-3 file:rounded-md file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:font-semibold file:text-white"></label><button class="ft-button-primary">Subir archivos</button></form>
        @endcan
    </section>

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
        <section class="ft-card p-5 sm:p-7" aria-labelledby="updates-title">
            <h2 id="updates-title" class="text-lg font-bold text-slate-950">Seguimiento</h2>
            <div class="mt-4 space-y-4">
                @forelse($activity->updates as $update)<article class="border-l-2 border-blue-300 pl-4"><p class="text-sm font-bold text-slate-900">{{ $update->user->name }} @if($update->progress_percent !== null)· {{ $update->progress_percent }}%@endif</p><p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">{{ $update->detail }}</p><time class="mt-1 block text-xs text-slate-500">{{ $update->created_at->format('d/m/Y H:i') }}</time></article>@empty<p class="text-sm text-slate-600">Todavía no se registraron avances.</p>@endforelse
            </div>
            @can('recordProgress', $activity)
                <form method="POST" action="{{ route('activities.progress', $activity) }}" class="mt-6 space-y-3 border-t border-slate-200 pt-5">@csrf<label for="detail" class="block text-sm font-bold text-slate-800">Registrar avance</label><textarea id="detail" name="detail" required maxlength="3000" rows="3" class="block w-full rounded-lg border-slate-300"></textarea><label for="progress_percent" class="block text-sm font-bold text-slate-800">Avance estimado <span class="font-normal text-slate-500">(opcional)</span></label><input id="progress_percent" type="number" name="progress_percent" min="0" max="100" class="block min-h-11 w-32 rounded-lg border-slate-300"><button class="ft-button-primary">Guardar avance</button></form>
            @endcan
        </section>

        <section class="ft-card p-5 sm:p-7" aria-labelledby="result-title">
            <h2 id="result-title" class="text-lg font-bold text-slate-950">Resultado</h2>
            @if($activity->result_summary)<p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ $activity->result_summary }}</p>@else<p class="mt-4 text-sm text-slate-600">El resultado todavía no fue presentado.</p>@endif
            @if($activity->resultDocument && Gate::allows('view', $activity->resultDocument))<a href="{{ route('documents.show', $activity->resultDocument) }}" class="ft-button-secondary mt-4">Abrir borrador presentado</a>@endif
            @can('submit', $activity)
                <form method="POST" action="{{ route('activities.submit', $activity) }}" class="mt-6 space-y-3 border-t border-slate-200 pt-5">@csrf<label for="result_summary" class="block text-sm font-bold text-slate-800">Producto o resultado presentado</label><textarea id="result_summary" name="result_summary" required maxlength="5000" rows="4" class="block w-full rounded-lg border-slate-300"></textarea><label for="result_document_public_id" class="block text-sm font-bold text-slate-800">Borrador preparado <span class="font-normal text-slate-500">(opcional)</span></label><select id="result_document_public_id" name="result_document_public_id" class="block min-h-11 w-full rounded-lg border-slate-300"><option value="">Sin borrador vinculado</option>@foreach($resultDocuments as $document)<option value="{{ $document->public_id }}">{{ $document->code ?? 'Borrador' }} — {{ Str::limit($document->subject, 70) }}</option>@endforeach</select><button class="ft-button-primary">Presentar a Jefatura</button></form>
            @endcan
            @can('review', $activity)
                <form method="POST" action="{{ route('activities.review', $activity) }}" class="mt-6 space-y-3 border-t border-slate-200 pt-5">@csrf<label for="review_note" class="block text-sm font-bold text-slate-800">Observación <span class="font-normal text-slate-500">(obligatoria si devuelve)</span></label><textarea id="review_note" name="review_note" maxlength="3000" rows="3" class="block w-full rounded-lg border-slate-300"></textarea><div class="flex flex-wrap gap-2"><button name="decision" value="complete" class="ft-button-primary">Dar por concluida</button><button name="decision" value="observe" class="ft-button-secondary">Devolver con observaciones</button></div></form>
            @endcan
        </section>
    </div>

    @can('cancel', $activity)
        <details class="ft-card mt-6 p-5"><summary class="cursor-pointer font-bold text-red-800">Cancelar actividad</summary><form method="POST" action="{{ route('activities.cancel', $activity) }}" class="mt-4 flex flex-col gap-3 sm:flex-row">@csrf<label for="reason" class="sr-only">Motivo de cancelación</label><input id="reason" name="reason" required maxlength="2000" class="min-h-11 flex-1 rounded-lg border-slate-300" placeholder="Motivo de la cancelación"><button class="ft-button-secondary text-red-800">Confirmar cancelación</button></form></details>
    @endcan

    @if($activity->files->isNotEmpty() || $relatedFiles->isNotEmpty())
        <dialog data-file-viewer class="ft-file-viewer" aria-labelledby="file-viewer-title" aria-describedby="file-viewer-name">
            <header class="flex items-start justify-between gap-4 border-b border-slate-200 p-4"><div class="min-w-0"><h2 id="file-viewer-title" class="text-lg font-bold text-slate-950">Visor de archivos</h2><p data-file-viewer-count class="mt-1 text-sm text-slate-600" aria-live="polite"></p></div><button type="button" data-file-viewer-close class="ft-button-secondary shrink-0" autofocus>Cerrar visor</button></header>
            <div class="ft-file-viewer-body">
                <nav class="ft-file-viewer-list" aria-label="Seleccionar archivo">
                    @foreach($relatedFiles as $file)
                        @can('downloadFile', [$activity->document, $file])
                            <button type="button" class="ft-file-choice" data-file-viewer-item data-file-id="document-{{ $file->public_id }}" data-file-name="{{ $file->original_name }}" data-file-mime="{{ $file->detected_mime_type }}" data-file-supported="{{ $file->supportsInlinePreview() ? '1' : '0' }}" data-file-preview-url="{{ route('documents.files.download', [$activity->document, $file, 'presentation' => 'inline']) }}" data-file-download-url="{{ route('documents.files.download', [$activity->document, $file]) }}"><span class="block break-words font-bold">{{ $file->original_name }}</span><span class="mt-1 block text-xs font-normal text-slate-500">Correspondencia relacionada</span></button>
                        @endcan
                    @endforeach
                    @foreach($activity->files as $file)
                        <button type="button" class="ft-file-choice" data-file-viewer-item data-file-id="activity-{{ $file->id }}" data-file-name="{{ $file->original_name }}" data-file-mime="{{ $file->mime_type }}" data-file-supported="{{ in_array($file->mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true) ? '1' : '0' }}" data-file-preview-url="{{ route('activities.files.preview', [$activity, $file]) }}" data-file-download-url="{{ route('activities.files.download', [$activity, $file]) }}"><span class="block break-words font-bold">{{ $file->original_name }}</span><span class="mt-1 block text-xs font-normal text-slate-500">Actividad</span></button>
                    @endforeach
                </nav>
                <section class="ft-file-viewer-selected" aria-labelledby="file-viewer-name"><h3 id="file-viewer-name" data-file-viewer-name class="break-words p-3 text-sm font-bold text-slate-950"></h3><p data-file-viewer-status role="status" class="px-4 pb-3 text-sm text-slate-700"></p><div data-file-viewer-content class="ft-file-viewer-content"></div></section>
            </div>
            <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 p-3"><button type="button" data-file-viewer-previous class="ft-button-secondary text-sm">Anterior</button><a data-file-viewer-download class="ft-button-primary text-sm">Descargar archivo</a><button type="button" data-file-viewer-next class="ft-button-secondary text-sm">Siguiente</button></footer>
        </dialog>
    @endif
</x-layouts.authenticated>
