<x-layouts.authenticated title="Control de correlativos">
    <div class="space-y-6">
        <header class="ft-page-header">
            <div>
                <p class="ft-eyebrow">Serie compartida · Facultad de Tecnología</p>
                <h1 class="ft-page-title">Control de correlativos</h1>
                <p class="ft-page-description">Todas las secretarías comparten esta numeración por tipo documental y gestión. Los documentos ajenos se muestran ocupados sin revelar su contenido.</p>
            </div>
        </header>

        <form method="GET" action="{{ route('correlatives.index') }}" class="ft-card p-4 sm:p-5" aria-label="Selección del control de correlativos">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">Gestión
                    <input type="number" name="year" value="{{ $year }}" min="2000" max="{{ now()->year + 1 }}" onchange="this.form.requestSubmit()" class="mt-1 min-h-11 w-full rounded-lg border-slate-300" aria-describedby="year-help">
                    <p id="year-help" class="mt-1 text-xs font-normal text-slate-500">Se actualiza automáticamente al iniciar una nueva gestión.</p>
                </label>
                <label class="text-sm font-semibold text-slate-700">Tipo documental
                    <select name="document_type" onchange="this.form.requestSubmit()" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->public_id }}" @selected($selectedType?->is($type))>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </form>

        @if($selectedType)
            <section class="grid gap-4 sm:grid-cols-2" aria-label="Resumen de la serie">
                <div class="ft-card p-5"><p class="text-sm font-semibold text-slate-600">Último número utilizado</p><p class="mt-2 text-3xl font-bold text-slate-950">{{ str_pad((string) $lastNumber, 3, '0', STR_PAD_LEFT) }}</p></div>
                <div class="ft-card border-2 border-emerald-300 bg-emerald-50 p-5"><p class="text-sm font-semibold text-emerald-800">Siguiente número previsto</p><p class="mt-2 text-3xl font-bold text-emerald-950">{{ str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT) }}</p><p class="mt-2 text-xs text-emerald-800">No está reservado; se confirmará durante el envío oficial.</p></div>
            </section>

            <section class="ft-card p-4 sm:p-6" aria-labelledby="correlative-grid-title">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><h2 id="correlative-grid-title" class="ft-section-heading">{{ $selectedType->name }} · {{ $year }}</h2><p class="mt-1 text-sm text-slate-600">Los números tachados ya fueron utilizados.</p></div>
                    <a href="{{ route('documents.create', ['document_type' => $selectedType->public_id]) }}" class="ft-button-primary">Crear borrador</a>
                </div>
                <div class="mt-5 grid grid-cols-4 gap-2 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 xl:grid-cols-12">
                    @foreach($numbers as $item)
                        @if($item['used'])
                            @if($item['document'])
                                <a href="{{ route('documents.show', $item['document']) }}" class="rounded-lg border border-slate-300 bg-slate-100 p-3 text-center text-sm font-bold text-slate-600 line-through hover:border-blue-400 hover:bg-blue-50" title="Abrir documento autorizado">{{ str_pad((string) $item['number'], 3, '0', STR_PAD_LEFT) }}</a>
                            @else
                                <span class="rounded-lg border border-slate-300 bg-slate-100 p-3 text-center text-sm font-bold text-slate-500 line-through" title="Utilizado; documento sin acceso">{{ str_pad((string) $item['number'], 3, '0', STR_PAD_LEFT) }}</span>
                            @endif
                        @else
                            <span class="rounded-lg border-2 border-emerald-400 bg-emerald-50 p-3 text-center text-sm font-bold text-emerald-900" title="Siguiente número previsto">{{ str_pad((string) $item['number'], 3, '0', STR_PAD_LEFT) }}</span>
                        @endif
                    @endforeach
                </div>
            </section>

            @if($numbers->hasPages())<div>{{ $numbers->links() }}</div>@endif
        @else
            <div class="ft-empty-state">No existen tipos documentales activos para crear borradores.</div>
        @endif
    </div>
</x-layouts.authenticated>
