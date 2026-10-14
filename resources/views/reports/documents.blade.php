<x-layouts.authenticated title="Reportes documentales">
    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Administración</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-950">Reportes documentales</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Resumen histórico y detalle de documentos dentro de su ámbito autorizado.</p>
            </div>
            @can('reports.export')
                <a href="{{ route('reports.documents.csv', $filters) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">
                    Exportar CSV
                </a>
            @endcan
        </header>

        @if ($errors->has('export'))
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-900">{{ $errors->first('export') }}</div>
        @endif

        <form method="GET" action="{{ route('reports.documents.index') }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Filtros del reporte documental">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-sm font-semibold text-slate-700">Reporte
                    <select name="report" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                        @foreach ($reportLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['report'] ?? 'summary') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Desde
                    <input type="date" name="period_from" value="{{ $filters['period_from'] ?? '' }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-semibold text-slate-700">Hasta
                    <input type="date" name="period_to" value="{{ $filters['period_to'] ?? '' }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-semibold text-slate-700">Estado actual
                    <select name="status" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                        <option value="">Todos</option>
                        @foreach (App\Enums\DocumentStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <details @if(collect($filters)->except(['report', 'period_from', 'period_to'])->filter(fn ($value) => filled($value))->isNotEmpty()) open @endif class="rounded-lg border border-slate-200">
                <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-blue-900">Más filtros</summary>
                <div class="grid gap-4 border-t border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="text-sm font-semibold text-slate-700">Prioridad
                        <select name="priority" class="mt-1 min-h-11 w-full rounded-lg border-slate-300"><option value="">Todas</option>@foreach(App\Enums\DocumentPriority::cases() as $priority)<option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>@endforeach</select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Tipo documental
                        <select name="document_type" class="mt-1 min-h-11 w-full rounded-lg border-slate-300"><option value="">Todos</option>@foreach($documentTypes as $type)<option value="{{ $type->public_id }}" @selected(($filters['document_type'] ?? '') === $type->public_id)>{{ $type->name }}{{ $type->is_active ? '' : ' (inactivo)' }}</option>@endforeach</select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Unidad de origen
                        <select name="origin_unit" class="mt-1 min-h-11 w-full rounded-lg border-slate-300"><option value="">Todas</option>@foreach($units as $unit)<option value="{{ $unit->public_id }}" @selected(($filters['origin_unit'] ?? '') === $unit->public_id)>{{ $unit->name }}{{ $unit->is_active ? '' : ' (inactiva)' }}</option>@endforeach</select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Unidad destinataria
                        <select name="recipient_unit" class="mt-1 min-h-11 w-full rounded-lg border-slate-300"><option value="">Todas</option>@foreach($units as $unit)<option value="{{ $unit->public_id }}" @selected(($filters['recipient_unit'] ?? '') === $unit->public_id)>{{ $unit->name }}</option>@endforeach</select>
                    </label>
                    <label class="text-sm font-semibold text-slate-700">Vencimiento
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

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="min-h-11 rounded-lg bg-blue-800 px-5 py-2 font-bold text-white hover:bg-blue-900">Generar reporte</button>
                <a href="{{ route('reports.documents.index') }}" class="min-h-11 rounded-lg border border-slate-300 px-5 py-2 font-bold text-slate-700 hover:bg-slate-50">Limpiar filtros</a>
            </div>
            @if ($errors->any() && ! $errors->has('export'))
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ $errors->first() }}</div>
            @endif
        </form>

        <p class="text-sm text-slate-600">La exportación CSV utiliza los mismos filtros y admite hasta {{ number_format($exportMaxRows, 0, ',', '.') }} filas.</p>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ([['Estados', $summaries['statuses']], ['Prioridades', $summaries['priorities']], ['Tipos documentales', $summaries['types']]] as [$title, $rows])
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <h2 class="border-b border-slate-200 px-4 py-3 font-bold text-slate-900">{{ $title }}</h2>
                    <table class="w-full text-sm"><tbody class="divide-y divide-slate-100">@forelse($rows as $row)<tr><td class="px-4 py-2 text-slate-700">{{ $row['label'] }}</td><td class="px-4 py-2 text-right font-bold text-slate-950">{{ $row['count'] }}</td></tr>@empty<tr><td class="px-4 py-4 text-slate-500">Sin datos para el período.</td></tr>@endforelse</tbody></table>
                </section>
            @endforeach
        </div>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="units-summary-title">
            <h2 id="units-summary-title" class="border-b border-slate-200 px-4 py-3 font-bold text-slate-900">Documentos por unidad de origen</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th class="px-4 py-3">Unidad</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Enviados</th><th class="px-4 py-3 text-right">Aprobados</th><th class="px-4 py-3 text-right">Devueltos</th><th class="px-4 py-3 text-right">Vencidos</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">@forelse($summaries['units'] as $row)<tr><td class="px-4 py-3 font-medium text-slate-800">{{ $row['label'] }}</td><td class="px-4 py-3 text-right">{{ $row['total'] }}</td><td class="px-4 py-3 text-right">{{ $row['sent'] }}</td><td class="px-4 py-3 text-right">{{ $row['approved'] }}</td><td class="px-4 py-3 text-right">{{ $row['returned'] }}</td><td class="px-4 py-3 text-right">{{ $row['overdue'] }}</td></tr>@empty<tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Sin datos para los filtros aplicados.</td></tr>@endforelse</tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="report-detail-title">
            <div class="border-b border-slate-200 px-4 py-3"><h2 id="report-detail-title" class="font-bold text-slate-900">Detalle autorizado</h2><p class="mt-1 text-sm text-slate-600">{{ $documents->total() }} {{ $documents->total() === 1 ? 'documento' : 'documentos' }}</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr><th class="px-4 py-3">Documento</th><th class="px-4 py-3">Origen</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Prioridad</th><th class="px-4 py-3">Fecha de envío</th><th class="px-4 py-3">Vencimiento</th><th class="px-4 py-3 text-right">Destinatarios</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($documents as $document)
                            <tr><td class="px-4 py-3"><a href="{{ route('documents.show', $document) }}" class="font-semibold text-blue-800 hover:underline">{{ $document->subject }}</a><p class="mt-1 text-xs text-slate-500">{{ $document->code ?? 'Borrador sin código' }} · {{ $document->documentType->name }}</p></td><td class="px-4 py-3 text-slate-700">{{ $document->originUnit->name }}</td><td class="px-4 py-3">{{ $document->general_status->label() }}</td><td class="px-4 py-3">{{ $document->priority->label() }}</td><td class="px-4 py-3 whitespace-nowrap">{{ $document->sent_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'No enviado' }}</td><td class="px-4 py-3">{{ $deadlineStatuses[$document->id]->label() }}</td><td class="px-4 py-3 text-right">{{ $document->recipients_count }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No existen documentos autorizados para los filtros aplicados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($documents->hasPages())
            <div>{{ $documents->links() }}</div>
        @endif
    </div>
</x-layouts.authenticated>
