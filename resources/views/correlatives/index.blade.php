<x-layouts.authenticated title="Control de correlativos">
    <div class="space-y-6">
        <header class="ft-page-header">
            <div>
                <p class="ft-eyebrow">Facultad de Tecnología</p>
                <h1 class="ft-page-title">Control de correlativos</h1>
                <p class="ft-page-description">La numeración es única por tipo documental y gestión para toda la Facultad. Al reservar el siguiente número se crea el borrador con ese cite.</p>
            </div>
        </header>

        @if($errors->any())<div role="alert" class="ft-alert ft-alert-error">{{ $errors->first() }}</div>@endif

        <form method="GET" action="{{ route('correlatives.index') }}" class="ft-card p-4 sm:p-5" aria-label="Selección de correlativos">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">Gestión
                    <input type="number" name="year" value="{{ $year }}" min="2000" max="{{ now()->year + 1 }}" onchange="this.form.requestSubmit()" class="mt-1 min-h-11 w-full rounded-lg border-slate-300">
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
                <div class="ft-card p-5"><p class="text-sm font-semibold text-slate-600">Último número reservado</p><p class="mt-2 text-3xl font-bold text-slate-950">{{ str_pad((string) $lastNumber, 4, '0', STR_PAD_LEFT) }}</p></div>
                <div class="ft-card border-2 border-emerald-300 bg-emerald-50 p-5"><p class="text-sm font-semibold text-emerald-800">Siguiente número</p><p class="mt-2 text-3xl font-bold text-emerald-950">{{ str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT) }}</p><p class="mt-2 text-xs text-emerald-800">Se asignará automáticamente al reservar; no se puede elegir otro número.</p></div>
            </section>

            <section class="ft-card p-4 sm:p-6" aria-labelledby="correlative-grid-title">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><h2 id="correlative-grid-title" class="ft-section-heading">{{ $selectedType->name }} · {{ $year }}</h2><p class="mt-1 text-sm text-slate-600">Los números reservados no se liberan ni se reutilizan.</p></div>
                    @if($pendingReservation)
                        <a href="{{ route('documents.show', $pendingReservation) }}" class="ft-button-primary">Continuar reserva pendiente</a>
                    @else
                        <form method="POST" action="{{ route('correlatives.reserve') }}">@csrf<input type="hidden" name="document_type" value="{{ $selectedType->public_id }}"><button type="submit" class="ft-button-primary">Reservar siguiente número</button></form>
                    @endif
                </div>
                @if($pendingReservation)<p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">Tiene pendiente el cite {{ $pendingReservation->code }}. Debe completar o enviar ese documento antes de reservar otro número.</p>@endif
                <div class="mt-5 grid grid-cols-4 gap-2 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 xl:grid-cols-12">
                    @foreach($numbers as $item)
                        @php
                            $reserver = $item['reservedBy'];
                        @endphp
                        @if($item['used'])
                            @if($item['sent'])
                                <span class="rounded-lg border border-slate-300 bg-slate-100 p-3 text-center text-sm font-bold text-slate-600 line-through">{{ str_pad((string) $item['number'], 4, '0', STR_PAD_LEFT) }}</span>
                            @else
                                <div class="group relative">
                                    <span tabindex="0" class="block cursor-help rounded-lg border-2 border-red-300 bg-red-50 p-3 text-center text-sm font-bold text-red-900 outline-none focus:border-red-600 focus:ring-2 focus:ring-red-600/20">{{ str_pad((string) $item['number'], 4, '0', STR_PAD_LEFT) }}</span>
                                    @if($reserver)
                                        <div role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden w-64 -translate-x-1/2 rounded-xl border border-slate-200 bg-white p-3 text-left text-sm shadow-xl group-hover:block group-focus-within:block">
                                            <p class="font-bold text-red-800">Reservado</p>
                                            <p class="mt-2 font-semibold text-slate-950">{{ $reserver->displayName() }}</p>
                                            <p class="text-slate-600">{{ $reserver->institutionalPosition() }}</p>
                                            <p class="mt-2 text-xs text-slate-500">{{ $item['reservedAt']?->format('d/m/Y H:i') }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @else
                            <span class="rounded-lg border-2 border-emerald-400 bg-emerald-50 p-3 text-center text-sm font-bold text-emerald-900">{{ str_pad((string) $item['number'], 4, '0', STR_PAD_LEFT) }}</span>
                        @endif
                    @endforeach
                </div>
            </section>

            @if($numbers->hasPages())<div>{{ $numbers->links() }}</div>@endif
        @else
            <div class="ft-empty-state">No existen tipos documentales activos que usen correlativo.</div>
        @endif
    </div>
</x-layouts.authenticated>
