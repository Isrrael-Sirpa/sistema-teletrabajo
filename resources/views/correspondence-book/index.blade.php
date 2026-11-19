<x-layouts.authenticated title="Libro de correspondencia">
    <div class="space-y-6">
        <header class="ft-page-header">
            <div>
                <p class="ft-eyebrow">Reportes · entradas y salidas</p>
                <h1 class="ft-page-title">Libro de correspondencia</h1>
                <p class="ft-page-description">Relación cronológica de la correspondencia enviada y recibida. Cada fila representa un despacho a un destinatario.</p>
            </div>
            <a href="{{ route('reports.documents.index') }}" class="ft-button-secondary">Volver a reportes</a>
        </header>

        <form method="GET" action="{{ route('correspondence-book.index') }}" class="ft-card p-4 sm:p-5" aria-label="Filtros del libro de correspondencia">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <label class="text-sm font-semibold text-slate-700">Buscar
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Cite o asunto" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-semibold text-slate-700">Gestión
                    <input type="number" name="year" value="{{ $year }}" min="2000" max="{{ now()->year + 1 }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-semibold text-slate-700">Mes <span class="font-normal text-slate-500">(opcional)</span>
                    <select name="month" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                        <option value="">Toda la gestión</option>
                        @foreach ([1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'] as $number => $name)
                            <option value="{{ $number }}" @selected($month === $number)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-700">Tipo documental
                    <select name="document_type" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                        <option value="">Todos</option>
                        @foreach ($documentTypes as $type)
                            <option value="{{ $type->public_id }}" @selected(($filters['document_type'] ?? '') === $type->public_id)>{{ $type->name }}{{ $type->is_active ? '' : ' (inactivo)' }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($isGlobal)
                    <label class="text-sm font-semibold text-slate-700">Carrera o unidad
                        <select name="unit" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                            <option value="">Toda la Facultad</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->public_id }}" @selected(($filters['unit'] ?? '') === $unit->public_id)>{{ $unit->name }}{{ $unit->is_active ? '' : ' (inactiva)' }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <label class="text-sm font-semibold text-slate-700">Desde
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="ml-2 min-h-11 rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-semibold text-slate-700">Hasta
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="ml-2 min-h-11 rounded-lg border-slate-300">
                </label>
                <button type="submit" class="ft-button-primary">Aplicar filtros</button>
                <a href="{{ route('correspondence-book.index') }}" class="ft-button-secondary">Limpiar</a>
                <a href="{{ route('correspondence-book.pdf', request()->query()) }}" class="ft-button-secondary">Imprimir / descargar PDF</a>
            </div>
            @if ($errors->any())
                <div role="alert" class="ft-alert ft-alert-error mt-4"><p class="font-bold">Revise los filtros.</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
        </form>

        <section class="ft-card overflow-hidden" aria-labelledby="book-results-title">
            <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h2 id="book-results-title" class="font-bold text-slate-900">Correspondencia de la gestión {{ $year }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $entries->total() }} {{ $entries->total() === 1 ? 'registro' : 'registros' }}{{ $selectedUnit ? ' · '.$selectedUnit->name : '' }}</p>
            </div>
            <div class="ft-table-wrap">
                <table role="table" class="ft-data-table min-w-full text-left text-sm">
                    <caption class="sr-only">Registro de correspondencia enviada y recibida</caption>
                    <thead class="bg-slate-50 text-xs uppercase text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3">Cite</th>
                            <th scope="col" class="px-4 py-3">Referencia</th>
                            <th scope="col" class="px-4 py-3">Remitente / procedencia</th>
                            <th scope="col" class="px-4 py-3">Fecha de envío</th>
                            <th scope="col" class="px-4 py-3">Destinatario</th>
                            <th scope="col" class="px-4 py-3">Fecha de recepción</th>
                            <th scope="col" class="px-4 py-3">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($entries as $entry)
                            @php($document = $entry->document)
                            <tr>
                                <td data-label="Cite" class="px-4 py-3 font-mono text-xs font-semibold text-slate-800">{{ $document->code }}</td>
                                <td data-label="Documento" class="px-4 py-3">
                                    <a href="{{ route('documents.show', $document) }}" class="font-semibold text-institutional-700 hover:underline">{{ $document->subject }}</a>
                                    <p class="mt-1 text-xs text-slate-500">{{ $document->documentType->name }}</p>
                                </td>
                                <td data-label="Origen" class="px-4 py-3 text-slate-700">
                                    {{ $document->originUnit->name }}
                                    <p class="mt-1 text-xs text-slate-500">{{ $document->sender?->name ?? 'Sin remitente registrado' }}</p>
                                </td>
                                <td data-label="Fecha de envío" class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $document->sent_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                                <td data-label="Destino" class="px-4 py-3 text-slate-700">
                                    {{ $entry->unit->name }}
                                    @if ($entry->user)<p class="mt-1 text-xs text-slate-500">{{ $entry->user->name }}</p>@endif
                                </td>
                                <td data-label="Fecha de recepción" class="whitespace-nowrap px-4 py-3 text-slate-700">
                                    @if ($entry->received_at)
                                        {{ $entry->received_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    @else
                                        <span class="text-amber-800">Pendiente</span>
                                    @endif
                                </td>
                                <td data-label="Observaciones" class="px-4 py-3 text-slate-700" aria-label="Sin observaciones"></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="ft-empty-state"><span class="block font-semibold text-slate-800">No hay correspondencia para los filtros seleccionados.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($entries->hasPages())
            <div>{{ $entries->links() }}</div>
        @endif
    </div>
</x-layouts.authenticated>
