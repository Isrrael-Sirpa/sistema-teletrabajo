<section class="rounded-xl border border-institutional-100 bg-institutional-50 p-4 sm:p-5" aria-labelledby="report-filters-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="report-filters-title" class="text-sm font-bold text-institutional-900">Filtros aplicados</h2>
        @if ($appliedFilters !== [])
            <a href="{{ route('reports.documents.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-institutional-700 underline underline-offset-4">Limpiar filtros</a>
        @endif
    </div>
    @if ($appliedFilters !== [])
        <dl class="mt-3 flex flex-wrap gap-2">
            @foreach ($appliedFilters as $label => $value)
                <div class="max-w-full rounded-lg border border-institutional-100 bg-white px-3 py-2 text-sm text-slate-700"><dt class="inline font-bold">{{ $label }}:</dt> <dd class="inline break-words">{{ $value }}</dd></div>
            @endforeach
        </dl>
        @if (isset($appliedFilters['Desde']) || isset($appliedFilters['Hasta']))
            <p class="ft-field-help">El período seleccionado corresponde a la fecha de {{ $periodBasis }}.</p>
        @endif
    @else
        <p class="mt-2 text-sm leading-6 text-slate-600">Sin filtros adicionales. Se muestran los documentos que puede consultar.</p>
    @endif
</section>
