<x-layouts.authenticated title="Documentos">
    <header class="ft-page-header">
        <h1 class="ft-page-title">Documentos</h1>
        @can('create', App\Models\Document::class)
            <a href="{{ route('documents.create') }}" class="ft-button-primary shrink-0"><x-ui.icon name="plus" /> Nuevo documento</a>
        @endcan
    </header>

    @if (auth()->user()->hasInstitutionalGlobalScope() && auth()->user()->organizational_unit_id)
        <nav class="mt-5 flex flex-wrap gap-2" aria-label="Vista de documentos">
            <a href="{{ route('documents.index') }}" @class(['ft-button-secondary', 'border-[var(--ft-primary)] bg-[var(--ft-primary)] text-white hover:bg-[var(--ft-primary)]' => $workFilter !== 'my_unit'])>Todos los documentos</a>
            <a href="{{ route('documents.index', ['work' => 'my_unit']) }}" @class(['ft-button-secondary', 'border-[var(--ft-primary)] bg-[var(--ft-primary)] text-white hover:bg-[var(--ft-primary)]' => $workFilter === 'my_unit'])>Documentos de mi unidad</a>
        </nav>
    @endif

    @if ($workFilterLabel && $workFilter !== 'my_unit')
        <div class="ft-alert ft-alert-info mt-6 flex flex-wrap items-center justify-between gap-3">
            <div><p class="text-sm font-semibold text-blue-950">Vista de trabajo: {{ $workFilterLabel }}</p><p class="mt-1 text-sm">Puede acotar esta vista con el buscador y los filtros.</p></div>
            <a href="{{ route('documents.index') }}" class="text-sm font-bold text-blue-900 underline">Quitar esta vista</a>
        </div>
    @endif

    @php
        $documents->getCollection()->loadMissing('recipients.unit');
        $advancedNames = ['priority', 'deadline', 'document_type', 'origin_unit', 'recipient_unit', 'confidentiality', 'sent_from', 'sent_to'];
        $hasAdvancedFilters = collect($advancedNames)->contains(fn ($name) => filled($filters[$name] ?? null))
            || ($filters['sort'] ?? 'created_at') !== 'created_at'
            || ($filters['direction'] ?? 'desc') !== 'desc';
        $hasFilters = collect($filters)->except(['work'])->filter(fn ($value) => filled($value))->isNotEmpty() || $workFilter !== '';
    @endphp

    <form method="GET" action="{{ route('documents.index') }}" class="ft-card mt-6 space-y-4 p-4 sm:p-5" aria-labelledby="document-search-title">
        <div><h2 id="document-search-title" class="ft-form-section-title">{{ auth()->user()->hasRole('student') ? 'Buscar documentos' : 'Buscar y filtrar documentos' }}</h2><p class="ft-field-help">{{ auth()->user()->hasRole('student') ? 'Busque sus documentos por código o asunto.' : 'Escriba un código o parte del asunto. Use los filtros solo si necesita precisar la búsqueda.' }}</p></div>
        @if ($workFilter !== '')
            <input type="hidden" name="work" value="{{ $workFilter }}">
        @endif
        <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] sm:items-end">
            <label class="text-sm font-bold text-slate-800">Código o asunto
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Escriba un código o un asunto" class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
            </label>
            @unless (auth()->user()->hasRole('student'))
            <label class="text-sm font-bold text-slate-800">Estado documental
                <select name="status" class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                    <option value="">Todos</option>
                    @foreach (App\Enums\DocumentStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
            @endunless
        </div>

        @unless (auth()->user()->hasRole('student'))
        <details @if($hasAdvancedFilters && blank($filters['q'] ?? null)) open @endif class="rounded-lg border border-slate-200">
            <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-[var(--ft-secondary)]">
                Más filtros
                @if ($hasAdvancedFilters)
                    <span class="ft-badge ml-2 bg-blue-100 text-blue-900">En uso</span>
                @endif
            </summary>
            <div class="grid gap-4 border-t border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <label class="text-sm font-semibold text-slate-700">Prioridad
                    <select name="priority" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Todas</option>
                        @foreach (App\Enums\DocumentPriority::cases() as $priority)
                            <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Vencimiento
                    <select name="deadline" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Cualquier condición</option>
                        <option value="due_soon" @selected(($filters['deadline'] ?? '') === 'due_soon')>Próximos a vencer</option>
                        <option value="overdue" @selected(($filters['deadline'] ?? '') === 'overdue')>Vencidos</option>
                        <option value="has_due" @selected(($filters['deadline'] ?? '') === 'has_due')>Con fecha límite</option>
                        <option value="no_due" @selected(($filters['deadline'] ?? '') === 'no_due')>Sin fecha límite</option>
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Ordenar por
                    <select name="sort" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm">
                        <option value="created_at" @selected(($filters['sort'] ?? 'created_at') === 'created_at')>Fecha de creación</option>
                        <option value="sent_at" @selected(($filters['sort'] ?? '') === 'sent_at')>Fecha de envío</option>
                        <option value="due_at" @selected(($filters['sort'] ?? '') === 'due_at')>Fecha límite</option>
                        <option value="priority" @selected(($filters['sort'] ?? '') === 'priority')>Prioridad</option>
                        <option value="code" @selected(($filters['sort'] ?? '') === 'code')>Código</option>
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Orden
                    <select name="direction" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm">
                        <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>De mayor a menor</option>
                        <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>De menor a mayor</option>
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Tipo documental
                    <select name="document_type" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Todos</option>@foreach($documentTypes as $type)<option value="{{ $type->public_id }}" @selected(($filters['document_type'] ?? '') === $type->public_id)>{{ $type->name }}{{ $type->is_active ? '' : ' (inactivo)' }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Unidad de origen
                    <select name="origin_unit" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Todas</option>@foreach($units as $unit)<option value="{{ $unit->public_id }}" @selected(($filters['origin_unit'] ?? '') === $unit->public_id)>{{ $unit->name }}{{ $unit->is_active ? '' : ' (inactiva)' }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Unidad destinataria
                    <select name="recipient_unit" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Todas</option>@foreach($units as $unit)<option value="{{ $unit->public_id }}" @selected(($filters['recipient_unit'] ?? '') === $unit->public_id)>{{ $unit->name }}{{ $unit->is_active ? '' : ' (inactiva)' }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Clasificación
                    <select name="confidentiality" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Todas</option>@foreach(App\Enums\DocumentConfidentiality::cases() as $classification)<option value="{{ $classification->value }}" @selected(($filters['confidentiality'] ?? '') === $classification->value)>{{ $classification->label() }}</option>@endforeach</select>
                </label>
                @foreach ([['sent_from', 'Enviado desde'], ['sent_to', 'Enviado hasta']] as [$name, $label])
                    <label class="text-sm font-semibold text-slate-700">{{ $label }}
                        <input type="date" name="{{ $name }}" value="{{ $filters[$name] ?? '' }}" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm">
                    </label>
                @endforeach
            </div>
        </details>
        @endunless

        <div class="flex flex-wrap items-end gap-3">
            <button type="submit" class="ft-button-primary">Buscar</button>
            @if ($hasFilters)
                <a href="{{ route('documents.index') }}" class="ft-button-secondary">Limpiar filtros</a>
            @endif
        </div>
        @if ($errors->any())
            <div role="alert" class="ft-alert ft-alert-error" tabindex="-1" data-form-errors>{{ $errors->first() }}</div>
        @endif
    </form>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-semibold text-slate-700" role="status">{{ $documents->total() }} {{ $documents->total() === 1 ? 'documento encontrado' : 'documentos encontrados' }}</p>
        @if ($documents->total() > 0)
            <p class="text-sm text-slate-500">Mostrando {{ $documents->firstItem() }}–{{ $documents->lastItem() }} de {{ $documents->total() }}</p>
        @endif
    </div>

    <section class="ft-card mt-6 overflow-hidden" aria-labelledby="documents-list-title">
        <h2 id="documents-list-title" class="sr-only">Listado de documentos</h2>
        <div class="ft-table-wrap">
            <table class="ft-data-table w-full divide-y divide-slate-200" role="table">
                <caption class="sr-only">Código, asunto, tipo, origen, destino, prioridad, estado, fechas y acción para ver.</caption>
                <thead class="bg-slate-50" role="rowgroup">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Documento</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Origen / destino</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Prioridad</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Estado</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Fechas</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white" role="rowgroup">
                    @if ($documents->isNotEmpty())
                        @foreach ($documents as $document)
                        <tr role="row">
                            <td role="cell" data-label="Documento" class="px-5 py-4">
                                <p class="text-xs font-bold text-[var(--ft-secondary)]">{{ auth()->user()->hasRole('student') ? ($document->sent_at ? 'Solicitud enviada' : 'Borrador de solicitud') : ($document->code ?? 'Borrador sin código') }}</p>
                                <a href="{{ route('documents.show', $document) }}" class="mt-1 block max-w-sm break-words font-semibold text-slate-900 underline-offset-4 hover:underline">{{ $document->subject }}</a>
                                <p class="mt-1 text-xs text-slate-500">{{ $document->documentType->name }}</p>
                            </td>
                            <td role="cell" data-label="Origen / destino" class="px-5 py-4 text-sm text-slate-700">
                                <p><span class="font-semibold">Origen:</span> {{ $document->originUnit->name }}</p>
                                @php
                                    $destinationNames = $document->recipients->pluck('unit.name')->filter()->unique();
                                @endphp
                                <p class="mt-1 text-xs text-slate-600">
                                    <span class="font-semibold">Destino:</span> {{ $destinationNames->take(2)->join(', ') ?: 'Por definir' }}
                                    @if ($destinationNames->count() > 2)
                                        y {{ $destinationNames->count() - 2 }} más
                                    @endif
                                </p>
                            </td>
                            <td role="cell" data-label="Prioridad" class="px-5 py-4 text-sm text-slate-700">
                                @if ($document->priority === App\Enums\DocumentPriority::URGENT)
                                    <span class="ft-badge bg-red-50 text-red-900">{{ $document->priority->label() }}</span>
                                @else
                                    {{ $document->priority->label() }}
                                @endif
                            </td>
                            <td role="cell" data-label="Estado" class="px-5 py-4"><x-ui.status-badge :status="$document->general_status" /></td>
                            <td role="cell" data-label="Fechas" class="px-5 py-4 text-xs text-slate-600">
                                <p>Creado: {{ $document->created_at->format('d/m/Y H:i') }}</p>
                                <p class="mt-1">Límite: {{ $document->due_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? 'Sin fecha' }}</p>
                                @if ($deadlineStatuses[$document->id] === App\Enums\DocumentDeadlineStatus::DUE_SOON)
                                    <span class="mt-2 inline-flex rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 font-bold text-amber-900">PRÓXIMO A VENCER</span>
                                @elseif ($deadlineStatuses[$document->id] === App\Enums\DocumentDeadlineStatus::OVERDUE)
                                    <span class="mt-2 inline-flex rounded-full border border-red-300 bg-red-50 px-2.5 py-1 font-bold text-red-900">VENCIDO</span>
                                @endif
                            </td>
                            <td role="cell" data-label="Acciones" class="px-5 py-4 text-right"><a href="{{ route('documents.show', $document) }}" class="ft-button-secondary" aria-label="Ver documento: {{ $document->subject }}">Ver documento</a></td>
                        </tr>
                        @endforeach
                    @else
                        <tr role="row">
                            <td role="cell" colspan="6" class="ft-empty-state px-5 py-10 text-center text-sm text-slate-600">
                                <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100 text-[var(--ft-secondary)]"><x-ui.icon name="documents" /></span>
                                <span class="mt-4 block font-bold text-slate-800">No se encontraron documentos.</span>
                                <span class="mt-2 block">{{ $hasFilters ? 'Pruebe con otros filtros o quite la vista de trabajo.' : 'Los documentos que pueda consultar aparecerán aquí.' }}</span>
                                @if ($hasFilters)
                                    <a href="{{ route('documents.index') }}" class="ft-button-secondary mt-5">Ver sin filtros</a>
                                @else
                                    @can('create', App\Models\Document::class)
                                        <a href="{{ route('documents.create') }}" class="ft-button-primary mt-5">Crear un documento</a>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>

    @if ($documents->hasPages())
        <div class="mt-6">{{ $documents->links() }}</div>
    @endif
</x-layouts.authenticated>
