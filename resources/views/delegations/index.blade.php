<x-layouts.authenticated title="Delegaciones temporales">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Administración</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-950">Delegaciones temporales</h1>
            <p class="mt-2 text-sm text-slate-600">Sustituciones de Jefatura con vigencia y capacidades expresas. No modifican roles permanentes.</p>
        </div>
        <a href="{{ route('delegations.create') }}" class="ft-button-primary"><x-ui.icon name="plus" /> Nueva delegación</a>
    </div>

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
                            <div><dt class="text-slate-500">Delegada</dt><dd class="font-medium">{{ $delegation->delegate->name }}</dd></div>
                            <div><dt class="text-slate-500">Inicio</dt><dd>{{ $delegation->starts_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd></div>
                            <div><dt class="text-slate-500">Finaliza</dt><dd>{{ $delegation->ends_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd></div>
                            <div><dt class="text-slate-500">Autorizó</dt><dd>{{ $delegation->authorizer->name }}</dd></div>
                            <div><dt class="text-slate-500">Motivo</dt><dd>{{ $delegation->reason }}</dd></div>
                        </dl>
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Capacidades</p>
                        <p class="mt-1 text-sm text-slate-700">{{ $delegation->permissions->pluck('display_name')->join(', ') }}</p>
                    </div>
                    @if (in_array($state, [App\Enums\DelegationState::UPCOMING, App\Enums\DelegationState::ACTIVE], true))
                        <form method="POST" action="{{ route('delegations.destroy', $delegation) }}" class="w-full max-w-sm rounded-lg border border-red-200 bg-red-50 p-3">
                            @csrf
                            @method('DELETE')
                            <label class="text-sm font-semibold text-red-950">Motivo de revocación
                                <textarea name="revocation_reason" required maxlength="2000" rows="2" class="mt-1 w-full rounded-lg border-red-300"></textarea>
                            </label>
                            <button class="mt-2 min-h-10 rounded-lg bg-red-800 px-4 py-2 text-sm font-bold text-white">Revocar delegación</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="ft-card p-10 text-center text-slate-600"><p class="font-bold text-slate-800">No hay delegaciones registradas.</p><p class="mt-1 text-sm">Las sustituciones temporales aparecerán aquí cuando se autoricen.</p></div>
        @endforelse
    </div>
    <div class="mt-6">{{ $items->links() }}</div>
</x-layouts.authenticated>
