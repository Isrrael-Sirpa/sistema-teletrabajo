<x-layouts.authenticated title="Delegaciones temporales">
    <header class="ft-page-header">
        <div>
            <p class="ft-eyebrow">Continuidad de la atención</p>
            <h1 class="ft-page-title">Delegaciones temporales</h1>
            <p class="ft-page-description">Consulte quién atiende temporalmente una Jefatura, durante qué período y con qué funciones autorizadas. No se modifican los roles permanentes.</p>
        </div>
        <a href="{{ route('delegations.create') }}" class="ft-button-primary"><x-ui.icon name="plus" /> Nueva delegación</a>
    </header>

    @if ($errors->any())
        <div role="alert" data-form-errors tabindex="-1" class="ft-alert ft-alert-error mt-6">
            <p class="font-bold">No se pudo completar la solicitud.</p>
            <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mt-6 space-y-4">
        @forelse ($items as $delegation)
            @php($state = $delegation->state())
            <article class="ft-card ft-card-accent p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-bold text-slate-950">{{ $delegation->organizationalUnit->name }}</h2>
                            <span @class(['ft-badge', 'bg-emerald-50 text-emerald-800' => $state === App\Enums\DelegationState::ACTIVE, 'bg-blue-50 text-blue-800' => $state === App\Enums\DelegationState::UPCOMING, 'bg-slate-100 text-slate-700' => $state === App\Enums\DelegationState::EXPIRED, 'bg-red-50 text-red-800' => $state === App\Enums\DelegationState::REVOKED])>{{ $state->label() }}</span>
                        </div>
                        <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                            <div><dt class="text-slate-500">Jefe titular</dt><dd class="font-medium">{{ $delegation->delegator->name }}</dd></div>
                            <div><dt class="text-slate-500">Secretaria delegada</dt><dd class="font-medium">{{ $delegation->delegate->name }}</dd></div>
                            <div><dt class="text-slate-500">Inicio</dt><dd>{{ $delegation->starts_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd></div>
                            <div><dt class="text-slate-500">Finaliza</dt><dd>{{ $delegation->ends_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd></div>
                            <div><dt class="text-slate-500">Autorizó</dt><dd>{{ $delegation->authorizer->name }}</dd></div>
                            <div><dt class="text-slate-500">Motivo</dt><dd class="break-words">{{ $delegation->reason }}</dd></div>
                        </dl>
                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Funciones autorizadas</p>
                        <p class="mt-1 text-sm text-slate-700">{{ $delegation->permissions->pluck('display_name')->join(', ') }}</p>
                    </div>
                    @if (in_array($state, [App\Enums\DelegationState::UPCOMING, App\Enums\DelegationState::ACTIVE], true))
                        <details class="w-full shrink-0 lg:max-w-xs">
                            <summary class="flex min-h-11 cursor-pointer items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Finalizar antes de tiempo</summary>
                        <form method="POST" action="{{ route('delegations.destroy', $delegation) }}" class="mt-3 rounded-xl border border-red-200 bg-red-50 p-4">
                            @csrf
                            @method('DELETE')
                            <p class="mb-3 text-sm leading-6 text-red-950">La Secretaria dejará de ejercer las funciones de esta delegación. El registro se conservará.</p>
                            <label class="text-sm font-semibold text-red-950">Motivo de revocación
                                <textarea name="revocation_reason" required maxlength="2000" rows="3" class="mt-1 w-full rounded-lg border-red-300"></textarea>
                            </label>
                            <button type="submit" class="mt-3 min-h-11 rounded-lg bg-red-800 px-4 py-2 text-sm font-bold text-white hover:bg-red-900">Revocar delegación</button>
                        </form>
                        </details>
                    @endif
                </div>
            </article>
        @empty
            <div class="ft-card ft-empty-state"><p class="font-bold text-slate-800">No hay delegaciones registradas.</p><p class="mt-2 text-sm">Si un Jefe necesita una sustitución temporal, registre una delegación con sus fechas y funciones.</p><a href="{{ route('delegations.create') }}" class="ft-button-primary mt-5">Registrar la primera delegación</a></div>
        @endforelse
    </div>
    <div class="mt-6">{{ $items->links() }}</div>
</x-layouts.authenticated>
