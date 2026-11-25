<x-layouts.authenticated title="Reportes documentales">
    <div class="space-y-6">
        <header class="ft-page-header">
            <div>
                <p class="ft-eyebrow">Análisis y seguimiento</p>
                <h1 class="ft-page-title">Reportes documentales</h1>
                <p class="ft-page-description">Consulte, filtre y exporte información documental autorizada.</p>
            </div>
            <a href="{{ route('correspondence-book.index') }}" class="ft-button-secondary">Libro de correspondencia</a>
        </header>

        @if ($errors->has('export'))
            <div role="alert" data-form-errors tabindex="-1" class="ft-alert ft-alert-error"><p class="font-bold">No se pudo descargar el reporte.</p><p class="mt-1">{{ $errors->first('export') }}</p></div>
        @endif

        <form method="GET" action="{{ route('reports.documents.index') }}" class="ft-card space-y-4 p-4 sm:p-5" aria-label="Filtros del reporte documental">
            <div><h2 class="ft-form-section-title">Filtros del reporte</h2><p class="ft-field-help">Las fechas y filtros son opcionales. Solo se incluyen documentos que puede consultar.</p></div>
            @foreach (Illuminate\Support\Arr::except($filters, ['q', 'report', 'period_from', 'period_to', 'status', 'priority', 'document_type', 'origin_unit', 'recipient_unit', 'deadline', 'confidentiality', 'creator', 'sender']) as $name => $value)
                @if (filled($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
            @endforeach
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-sm font-semibold text-slate-700">Buscar
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Buscar documentos" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-semibold text-slate-700">Estado general del documento
                    <select name="status" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                        <option value="">Todos</option>
                        @foreach (App\Enums\DocumentStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Carrera / unidad de origen
                    <select name="origin_unit" class="mt-1 min-h-11 w-full rounded-lg border-slate-300"><option value="">Todas</option>@foreach($units as $unit)<option value="{{ $unit->public_id }}" @selected(($filters['origin_unit'] ?? '') === $unit->public_id)>{{ $unit->name }}{{ $unit->is_active ? '' : ' (inactiva)' }}</option>@endforeach</select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Tipo documental
                    <select name="document_type" class="mt-1 min-h-11 w-full rounded-lg border-slate-300"><option value="">Todos</option>@foreach($documentTypes as $type)<option value="{{ $type->public_id }}" @selected(($filters['document_type'] ?? '') === $type->public_id)>{{ $type->name }}{{ $type->is_active ? '' : ' (inactivo)' }}</option>@endforeach</select>
                </label>
            </div>

            <details @if(($filters['report'] ?? 'summary') !== 'summary' || collect($filters)->except(['q', 'status', 'origin_unit', 'document_type', 'report', 'page'])->filter(fn ($value) => filled($value))->isNotEmpty()) open @endif class="rounded-lg border border-slate-200">
                <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-institutional-900">Más filtros</summary>
                <div class="grid gap-4 border-t border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm font-semibold text-slate-700">Reporte
                        <select name="report" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                            @foreach ($reportLabels as $value => $label)
                                @php
                                    $unitLabel = auth()->user()->hasInstitutionalGlobalScope()
                                        ? $label
                                        : match ($value) {
                                            'sent' => 'Enviados desde mi unidad',
                                            'received' => 'Recibidos por mi unidad',
                                            default => $label,
                                        };
                                @endphp
                                <option value="{{ $value }}" @selected(($filters['report'] ?? 'summary') === $value)>{{ $unitLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Desde
                        <input type="date" name="period_from" value="{{ $filters['period_from'] ?? '' }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Hasta
                        <input type="date" name="period_to" value="{{ $filters['period_to'] ?? '' }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Prioridad
                        <select name="priority" class="mt-1 min-h-11 w-full rounded-lg border-slate-300"><option value="">Todas</option>@foreach(App\Enums\DocumentPriority::cases() as $priority)<option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>@endforeach</select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Unidad destinataria
                        <select name="recipient_unit" class="mt-1 min-h-11 w-full rounded-lg border-slate-300"><option value="">Todas</option>@foreach($units as $unit)<option value="{{ $unit->public_id }}" @selected(($filters['recipient_unit'] ?? '') === $unit->public_id)>{{ $unit->name }}</option>@endforeach</select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Situación del plazo
                        <select name="deadline" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                            <option value="">Cualquier condición</option>
                            <option value="due_soon" @selected(($filters['deadline'] ?? '') === 'due_soon')>Próximos a vencer</option>
                            <option value="overdue" @selected(($filters['deadline'] ?? '') === 'overdue')>Vencidos</option>
                            <option value="has_due" @selected(($filters['deadline'] ?? '') === 'has_due')>Con fecha límite</option>
                            <option value="no_due" @selected(($filters['deadline'] ?? '') === 'no_due')>Sin fecha límite</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Clasificación
                        <select name="confidentiality" class="mt-1 min-h-11 w-full rounded-lg border-slate-300"><option value="">Todas</option>@foreach(App\Enums\DocumentConfidentiality::cases() as $classification)<option value="{{ $classification->value }}" @selected(($filters['confidentiality'] ?? '') === $classification->value)>{{ $classification->label() }}</option>@endforeach</select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Creador
                        <select name="creator" class="mt-1 min-h-11 w-full rounded-lg border-slate-300"><option value="">Todos</option>@foreach($people as $person)<option value="{{ $person->public_id }}" @selected(($filters['creator'] ?? '') === $person->public_id)>{{ $person->name }}{{ $person->status->value === 'inactive' ? ' (inactivo)' : '' }}</option>@endforeach</select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Remitente
                        <select name="sender" class="mt-1 min-h-11 w-full rounded-lg border-slate-300"><option value="">Todos</option>@foreach($people as $person)<option value="{{ $person->public_id }}" @selected(($filters['sender'] ?? '') === $person->public_id)>{{ $person->name }}{{ $person->status->value === 'inactive' ? ' (inactivo)' : '' }}</option>@endforeach</select>
                    </label>
                </div>
            </details>

            <div class="ft-form-actions">
                <p class="ft-field-help">Después de cambiar su selección, pulse «Aplicar filtros».</p>
                <div class="flex flex-wrap gap-3">
                <button type="submit" class="ft-button-primary">Aplicar filtros</button>
                <a href="{{ route('reports.documents.index') }}" class="ft-button-secondary">Limpiar filtros</a>
                </div>
            </div>
            @if ($errors->any() && ! $errors->has('export'))
                <div role="alert" data-form-errors tabindex="-1" class="ft-alert ft-alert-error"><p class="font-bold">Revise los filtros seleccionados.</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
        </form>

        @include('reports.partials.filters-applied')

        @php
            // Reuse the complete filtered aggregates, never just this page's rows.
            $statusCounts = collect($summaries['statuses'])->pluck('count', 'label');
        @endphp
        <section aria-labelledby="report-summary-title">
            <h2 id="report-summary-title" class="ft-form-section-title">Resultados</h2>
            <p class="ft-field-help">Totales de los resultados filtrados, según su estado actual.</p>
            <dl class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="ft-card border-t-4 border-t-institutional-700 p-4"><dt class="text-sm font-semibold text-slate-600">Total de documentos</dt><dd class="mt-2 text-2xl font-bold text-institutional-900">{{ number_format($documents->total(), 0, ',', '.') }} <span class="text-sm font-medium">{{ $documents->total() === 1 ? 'documento' : 'documentos' }}</span></dd></div>
                @foreach ([App\Enums\DocumentStatus::SENT, App\Enums\DocumentStatus::DRAFT, App\Enums\DocumentStatus::ANNULLED] as $status)
                    <div class="ft-card p-4"><dt><x-ui.status-badge :status="$status" /></dt><dd class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($statusCounts->get($status->label(), 0), 0, ',', '.') }}</dd></div>
                @endforeach
            </dl>
        </section>

        <details class="ft-card">
            <summary class="px-4 py-4 text-sm font-bold text-institutional-900 sm:px-5">Ver desglose por estado, prioridad, tipo y unidad</summary>
            <div class="space-y-5 border-t border-slate-200 p-4 sm:p-5">

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ([['Estados', $summaries['statuses']], ['Prioridades', $summaries['priorities']], ['Tipos de documento', $summaries['types']]] as [$title, $rows])
                <section class="ft-card overflow-hidden">
                    <h2 class="border-b border-slate-200 px-4 py-3 font-bold text-slate-900">{{ $title }}</h2>
                    <dl class="divide-y divide-slate-100 text-sm">
                        @forelse ($rows as $row)
                            <div class="flex items-start justify-between gap-4 px-4 py-3"><dt class="text-slate-700">{{ $row['label'] }}</dt><dd class="font-bold text-slate-950"><span class="sr-only">Cantidad: </span>{{ $row['count'] }}</dd></div>
                        @empty
                            <div class="px-4 py-4 text-slate-500"><dt class="sr-only">Resumen</dt><dd>Sin datos para el período.</dd></div>
                        @endforelse
                    </dl>
                </section>
            @endforeach
        </div>

        <section class="ft-card overflow-hidden" aria-labelledby="units-summary-title">
            <h2 id="units-summary-title" class="border-b border-slate-200 px-4 py-3 font-bold text-slate-900">Documentos por unidad de origen</h2>
            <div class="ft-table-wrap">
                <table role="table" class="ft-data-table min-w-full text-left text-sm">
                    <caption class="sr-only">Cantidad de documentos por unidad y estado para los filtros actuales</caption>
                    <thead role="rowgroup" class="bg-slate-50 text-xs uppercase text-slate-600"><tr role="row"><th role="columnheader" scope="col" class="px-4 py-3">Unidad</th><th role="columnheader" scope="col" class="px-4 py-3 text-right">Total</th><th role="columnheader" scope="col" class="px-4 py-3 text-right">Enviados</th><th role="columnheader" scope="col" class="px-4 py-3 text-right">Aprobados</th><th role="columnheader" scope="col" class="px-4 py-3 text-right">Devueltos</th><th role="columnheader" scope="col" class="px-4 py-3 text-right">Vencidos</th></tr></thead>
                    <tbody role="rowgroup" class="divide-y divide-slate-100">@forelse($summaries['units'] as $row)<tr role="row"><td role="cell" data-label="Unidad" class="px-4 py-3 font-medium text-slate-800">{{ $row['label'] }}</td><td role="cell" data-label="Total" class="px-4 py-3 text-right">{{ $row['total'] }}</td><td role="cell" data-label="Enviados" class="px-4 py-3 text-right">{{ $row['sent'] }}</td><td role="cell" data-label="Aprobados" class="px-4 py-3 text-right">{{ $row['approved'] }}</td><td role="cell" data-label="Devueltos" class="px-4 py-3 text-right">{{ $row['returned'] }}</td><td role="cell" data-label="Vencidos" class="px-4 py-3 text-right">{{ $row['overdue'] }}</td></tr>@empty<tr role="row"><td role="cell" colspan="6" class="ft-empty-state"><span class="block font-semibold text-slate-800">Sin datos para los filtros aplicados.</span><span class="mt-2 block text-sm text-slate-600">Amplíe las fechas o quite algunos filtros para consultar otro período.</span></td></tr>@endforelse</tbody>
                </table>
            </div>
        </section>

            </div>
        </details>

        <section class="ft-card overflow-hidden" aria-labelledby="report-detail-title">
            <div class="border-b border-slate-200 px-4 py-3"><h2 id="report-detail-title" class="font-bold text-slate-900">Documentos del reporte</h2><p class="mt-1 text-sm text-slate-600">{{ $documents->total() }} {{ $documents->total() === 1 ? 'documento' : 'documentos' }} · Fechas y horas de Bolivia.</p></div>
            <div class="ft-table-wrap">
                <table role="table" class="ft-data-table min-w-full text-left text-sm">
                    <caption class="sr-only">Documentos incluidos en el reporte. Seleccione un asunto para abrir el documento.</caption>
                    <thead role="rowgroup" class="bg-slate-50 text-xs uppercase text-slate-600"><tr role="row"><th role="columnheader" scope="col" class="px-4 py-3">Código</th><th role="columnheader" scope="col" class="px-4 py-3">Asunto</th><th role="columnheader" scope="col" class="px-4 py-3">Unidad de origen</th><th role="columnheader" scope="col" class="px-4 py-3">Estado</th><th role="columnheader" scope="col" class="px-4 py-3">Fecha de envío</th><th role="columnheader" scope="col" class="px-4 py-3">Situación del plazo</th></tr></thead>
                    <tbody role="rowgroup" class="divide-y divide-slate-100">
                        @forelse ($documents as $document)
                            <tr role="row">
                                <td role="cell" data-label="Código" class="px-4 py-3 font-mono text-xs text-slate-700">{{ $document->code ?? '—' }}</td>
                                <td role="cell" data-label="Asunto" class="px-4 py-3"><a href="{{ route('documents.show', $document) }}" class="font-semibold text-institutional-700 hover:underline">{{ $document->subject }}</a><p class="mt-1 text-xs text-slate-500">{{ $document->documentType->name }} · Destinatarios: {{ $document->recipients_count }}</p></td>
                                <td role="cell" data-label="Unidad de origen" class="px-4 py-3 text-slate-700">{{ $document->originUnit->name }}<p class="mt-1 text-xs text-slate-500">Remitente: {{ $document->sender?->name ?? '—' }}</p></td>
                                <td role="cell" data-label="Estado" class="px-4 py-3"><x-ui.status-badge :status="$document->general_status" /><p class="mt-2 text-xs text-slate-500">Prioridad: {{ $document->priority->label() }}</p><p class="mt-1 text-xs text-slate-500">Clasificación: {{ $document->confidentiality->label() }}</p></td>
                                <td role="cell" data-label="Fecha de envío" class="px-4 py-3 whitespace-nowrap">{{ $document->sent_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</td>
                                <td role="cell" data-label="Situación del plazo" class="px-4 py-3">{{ $deadlineStatuses[$document->id]->label() }}<p class="mt-1 text-xs text-slate-500">Fecha límite: {{ $document->due_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p></td>
                            </tr>
                        @empty
                            <tr role="row"><td role="cell" colspan="6" class="ft-empty-state"><span class="block font-semibold text-slate-800">No se encontraron documentos con estos filtros.</span><span class="mt-2 block text-sm text-slate-600">Pruebe con un período más amplio o seleccione «Limpiar filtros».</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($documents->hasPages())
            <div>{{ $documents->links() }}</div>
        @endif

        @can('reports.export')
            <section class="ft-card space-y-4 p-4 sm:p-5" aria-labelledby="report-export-title">
                <div>
                    <h2 id="report-export-title" class="ft-section-heading">Exportar resultados</h2>
                    <p id="report-export-help" class="mt-2 text-sm text-slate-700">Descargue los resultados actuales utilizando los filtros seleccionados.</p>
                    <p class="ft-field-help">Incluye todas las páginas con los filtros aplicados. Máximo: {{ number_format($exportMaxRows, 0, ',', '.') }} documentos.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="flex flex-col items-start rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">CSV</h3>
                        <p class="mt-1 text-sm text-slate-600">Datos para análisis e intercambio de información.</p>
                    </div>
                    <a href="{{ route('reports.documents.csv', $filters) }}" class="ft-button-secondary mt-4 w-full" aria-describedby="report-export-help report-export-status" data-report-export><x-ui.icon name="list" /><span data-report-export-label>Descargar CSV</span></a>
                </div>
                <div class="flex flex-col items-start rounded-xl border border-institutional-100 bg-institutional-50 p-4">
                    <div>
                        <h3 class="text-sm font-bold text-institutional-900">Excel</h3>
                        <p class="mt-1 text-sm text-slate-600">Reporte formateado para trabajo administrativo.</p>
                    </div>
                    <a href="{{ route('reports.documents.xlsx', $filters) }}" class="ft-button-primary mt-4 w-full" aria-describedby="report-export-help report-export-status" data-report-export><x-ui.icon name="documents" /><span data-report-export-label>Descargar Excel</span></a>
                </div>
                <div class="flex flex-col items-start rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">PDF</h3>
                        <p class="mt-1 text-sm text-slate-600">Reporte institucional listo para imprimir.</p>
                    </div>
                    <a href="{{ route('reports.documents.pdf', $filters) }}" class="ft-button-secondary mt-4 w-full" aria-describedby="report-export-help report-export-status" data-report-export><x-ui.icon name="documents" /><span data-report-export-label>Descargar PDF</span></a>
                </div>
                </div>
                <p id="report-export-status" role="status" aria-live="polite" class="text-sm leading-6 text-slate-600" data-report-export-status></p>
            </section>
        @endcan
    </div>
</x-layouts.authenticated>
