<x-layouts.authenticated title="Documentos">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-blue-700">Gestión documental</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Documentos</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Listado de documentos visibles dentro de su ámbito autorizado.</p>
        </div>
        @can('create', App\Models\Document::class)
            <a href="{{ route('documents.create') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-institutional-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-institutional-900">Crear borrador</a>
        @endcan
    </div>

    @if (session('status'))
        <div role="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    @if ($workFilterLabel)
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
            <p class="text-sm font-semibold text-blue-950">Vista de trabajo: {{ $workFilterLabel }}</p>
            <a href="{{ route('documents.index') }}" class="text-sm font-bold text-blue-900 underline">Quitar esta vista</a>
        </div>
    @endif

    @php
        $advancedNames = ['document_type', 'origin_unit', 'recipient_unit', 'recipient_user', 'recipient_status', 'creator', 'sender', 'confidentiality', 'sent_from', 'sent_to', 'due_from', 'due_to', 'created_from', 'created_to', 'correction_pending', 'version_min'];
        $hasAdvancedFilters = collect($advancedNames)->contains(fn ($name) => filled($filters[$name] ?? null));
        $hasFilters = collect($filters)->except(['work'])->filter(fn ($value) => filled($value))->isNotEmpty() || $workFilter !== '';
    @endphp

    <form method="GET" action="{{ route('documents.index') }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-4" aria-label="Buscar y filtrar documentos">
        @if ($workFilter !== '')
            <input type="hidden" name="work" value="{{ $workFilter }}">
        @endif
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5 xl:items-end">
            <label class="text-sm font-bold text-slate-800 sm:col-span-2 xl:col-span-1">Código o asunto
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Ej.: FTEC-SIS-2026-000125" class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
            </label>
            <label class="text-sm font-bold text-slate-800">Estado documental
                <select name="status" class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                    <option value="">Todos</option>
                    @foreach (App\Enums\DocumentStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-bold text-slate-800">Prioridad
                <select name="priority" class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                    <option value="">Todas</option>
                    @foreach (App\Enums\DocumentPriority::cases() as $priority)
                        <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-bold text-slate-800">Vencimiento
                <select name="deadline" class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                    <option value="">Cualquier condición</option>
                    <option value="due_soon" @selected(($filters['deadline'] ?? '') === 'due_soon')>Próximos a vencer</option>
                    <option value="overdue" @selected(($filters['deadline'] ?? '') === 'overdue')>Vencidos</option>
                    <option value="has_due" @selected(($filters['deadline'] ?? '') === 'has_due')>Con fecha límite</option>
                    <option value="no_due" @selected(($filters['deadline'] ?? '') === 'no_due')>Sin fecha límite</option>
                </select>
            </label>
            <label class="text-sm font-bold text-slate-800">Ordenar por
                <select name="sort" class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                    <option value="created_at" @selected(($filters['sort'] ?? 'created_at') === 'created_at')>Fecha de creación</option>
                    <option value="sent_at" @selected(($filters['sort'] ?? '') === 'sent_at')>Fecha de envío</option>
                    <option value="due_at" @selected(($filters['sort'] ?? '') === 'due_at')>Fecha límite</option>
                    <option value="priority" @selected(($filters['sort'] ?? '') === 'priority')>Prioridad</option>
                    <option value="code" @selected(($filters['sort'] ?? '') === 'code')>Código</option>
                </select>
            </label>
        </div>

        <details @if($hasAdvancedFilters) open @endif class="rounded-lg border border-slate-200">
            <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-blue-900">Más filtros</summary>
            <div class="grid gap-4 border-t border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <label class="text-sm font-semibold text-slate-700">Tipo documental
                    <select name="document_type" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Todos</option>@foreach($documentTypes as $type)<option value="{{ $type->public_id }}" @selected(($filters['document_type'] ?? '') === $type->public_id)>{{ $type->name }}{{ $type->is_active ? '' : ' (inactivo)' }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Unidad de origen
                    <select name="origin_unit" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Todas</option>@foreach($units as $unit)<option value="{{ $unit->public_id }}" @selected(($filters['origin_unit'] ?? '') === $unit->public_id)>{{ $unit->name }}{{ $unit->is_active ? '' : ' (inactiva)' }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Unidad destinataria
                    <select name="recipient_unit" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Todas</option>@foreach($units as $unit)<option value="{{ $unit->public_id }}" @selected(($filters['recipient_unit'] ?? '') === $unit->public_id)>{{ $unit->name }}{{ $unit->is_active ? '' : ' (inactiva)' }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Destinatario personal
                    <select name="recipient_user" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Todos</option>@foreach($people as $person)<option value="{{ $person->public_id }}" @selected(($filters['recipient_user'] ?? '') === $person->public_id)>{{ $person->name }}{{ $person->status->value === 'inactive' ? ' (inactivo)' : '' }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Estado del destinatario
                    <select name="recipient_status" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Todos</option>@foreach(App\Enums\DocumentRecipientStatus::cases() as $recipientStatus)<option value="{{ $recipientStatus->value }}" @selected(($filters['recipient_status'] ?? '') === $recipientStatus->value)>{{ $recipientStatus->label() }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Creador
                    <select name="creator" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Todos</option>@foreach($people as $person)<option value="{{ $person->public_id }}" @selected(($filters['creator'] ?? '') === $person->public_id)>{{ $person->name }}{{ $person->status->value === 'inactive' ? ' (inactivo)' : '' }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Remitente
                    <select name="sender" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Todos</option>@foreach($people as $person)<option value="{{ $person->public_id }}" @selected(($filters['sender'] ?? '') === $person->public_id)>{{ $person->name }}{{ $person->status->value === 'inactive' ? ' (inactivo)' : '' }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Clasificación
                    <select name="confidentiality" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm"><option value="">Todas</option>@foreach(App\Enums\DocumentConfidentiality::cases() as $classification)<option value="{{ $classification->value }}" @selected(($filters['confidentiality'] ?? '') === $classification->value)>{{ $classification->label() }}</option>@endforeach</select>
                </label>
                @foreach ([['sent_from', 'Enviado desde'], ['sent_to', 'Enviado hasta'], ['due_from', 'Límite desde'], ['due_to', 'Límite hasta'], ['created_from', 'Creado desde'], ['created_to', 'Creado hasta']] as [$name, $label])
                    <label class="text-sm font-semibold text-slate-700">{{ $label }}
                        <input type="date" name="{{ $name }}" value="{{ $filters[$name] ?? '' }}" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm">
                    </label>
                @endforeach
                <label class="text-sm font-semibold text-slate-700">Versión actual mínima
                    <input type="number" name="version_min" value="{{ $filters['version_min'] ?? '' }}" min="1" max="999" class="mt-1 block min-h-11 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-700">
                    <input type="checkbox" name="correction_pending" value="1" @checked(!empty($filters['correction_pending'])) class="rounded border-slate-300">
                    Corrección pendiente
                </label>
            </div>
        </details>

        <div class="flex flex-wrap items-end gap-3">
            <label class="text-sm font-bold text-slate-800">Dirección
                <select name="direction" class="ml-2 min-h-11 rounded-lg border-slate-300 text-sm">
                    <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Descendente</option>
                    <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>Ascendente</option>
                </select>
            </label>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institutional-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-institutional-900">Buscar</button>
            @if ($hasFilters)
                <a href="{{ route('documents.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Limpiar filtros</a>
            @endif
        </div>
        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first() }}</div>
        @endif
    </form>

    <p class="mt-4 text-sm font-semibold text-slate-700">{{ $documents->total() }} {{ $documents->total() === 1 ? 'documento encontrado' : 'documentos encontrados' }}</p>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="documents-list-title">
        <h2 id="documents-list-title" class="sr-only">Listado de documentos</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <caption class="sr-only">Asunto, tipo, origen, prioridad, estado y acciones.</caption>
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Documento</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Origen</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Creador</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Prioridad</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Estado</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Fechas</th>
                        <th scope="col" class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($documents as $document)
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('documents.show', $document) }}" class="font-semibold text-blue-800 underline-offset-4 hover:underline">{{ $document->subject }}</a>
                                <p class="mt-1 text-xs font-semibold text-slate-600">{{ $document->code ?? 'Borrador sin código' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $document->documentType->name }}</p>
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-700">{{ $document->originUnit->name }}</td>
                            <td class="px-5 py-4 text-sm text-slate-700">{{ $document->creator->name }}</td>
                            <td class="px-5 py-4 text-sm text-slate-700">{{ $document->priority->label() }}</td>
                            <td class="px-5 py-4"><span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800">{{ $document->general_status->label() }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-600">
                                <p>Creado: {{ $document->created_at->format('d/m/Y H:i') }}</p>
                                <p class="mt-1">Límite: {{ $document->due_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? 'Sin fecha' }}</p>
                                @if ($deadlineStatuses[$document->id] === App\Enums\DocumentDeadlineStatus::DUE_SOON)
                                    <span class="mt-2 inline-flex rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 font-bold text-amber-900">PRÓXIMO A VENCER</span>
                                @elseif ($deadlineStatuses[$document->id] === App\Enums\DocumentDeadlineStatus::OVERDUE)
                                    <span class="mt-2 inline-flex rounded-full border border-red-300 bg-red-50 px-2.5 py-1 font-bold text-red-900">VENCIDO</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('documents.show', $document) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-slate-600">No se encontraron documentos con los filtros aplicados dentro de su ámbito.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($documents->hasPages())
        <div class="mt-6">{{ $documents->links() }}</div>
    @endif
</x-layouts.authenticated>
