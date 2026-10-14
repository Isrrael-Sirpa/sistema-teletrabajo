<x-layouts.authenticated title="Delegaciones temporales">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Administración</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-950">Delegaciones temporales</h1>
            <p class="mt-2 text-sm text-slate-600">Sustituciones de Jefatura con vigencia y capacidades expresas. No modifican roles permanentes.</p>
        </div>
        <a href="{{ route('delegations.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-800 px-5 py-2 font-semibold text-white hover:bg-blue-900">Nueva delegación</a>
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($items as $delegation)
            @php($state = $delegation->state())
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-bold text-slate-950">{{ $delegation->organizationalUnit->name }}</h2>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ $state->label() }}</span>
                        </div>
                        <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                            <div><dt class="text-slate-500">Jefe titular</dt><dd class="font-medium">{{ $delegation->delegator->name }}</dd></div>
                            <div><dt class="text-slate-500">Delegada</dt><dd class="font-medium">{{ $delegation->delegate->name }}</dd></div>
                            <div><dt class="text-slate-500">Inicio</dt><dd>{{ $delegation->starts_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd></div>
                            <div><dt class="text-slate-500">Fin exclusivo</dt><dd>{{ $delegation->ends_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd></div>
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
            <div class="rounded-xl border border-slate-200 bg-white p-10 text-center text-slate-600">No existen delegaciones registradas.</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $items->links() }}</div>
</x-layouts.authenticated>
