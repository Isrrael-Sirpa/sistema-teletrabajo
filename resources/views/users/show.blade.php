<x-layouts.authenticated :title="$user->name">
    <div class="mx-auto max-w-4xl">
        <div class="ft-page-header">
            <div>
                <p class="ft-eyebrow">Detalle del usuario</p>
                <h1 class="ft-page-title">{{ $user->name }}</h1>
                <p class="ft-page-description break-all">{{ $user->email }}</p>
            </div>
            <a href="{{ route('users.index') }}" class="ft-button-secondary">Volver al listado</a>
        </div>

        @if ($errors->any())
            <div role="alert" class="ft-alert ft-alert-error mt-6" tabindex="-1" data-form-errors>
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="user-information-title">
            <h2 id="user-information-title" class="text-lg font-bold text-slate-950">Información institucional</h2>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Rol</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $user->roleName()?->label() ?? 'Sin rol' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Ámbito</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $user->organizationalUnit?->name ?? 'Facultad completa' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Estado</dt><dd class="mt-1"><x-ui.status-badge :status="$user->status" /><p class="ft-field-help">{{ $user->status === App\Enums\UserStatus::ACTIVE ? 'Puede iniciar sesión en el sistema.' : 'No puede iniciar sesión en el sistema.' }}</p></dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Último ingreso</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Sin ingresos registrados' }}</dd></div>
            </dl>
        </section>

        @if (auth()->user()->canAny(['update', 'resetPassword', 'deactivate', 'activate'], $user))
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" aria-labelledby="user-actions-title">
                <h2 id="user-actions-title" class="ft-form-section-title">Administrar esta cuenta</h2>
                <p class="ft-field-help">Los cambios de acceso conservan la cuenta y su historial institucional.</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    @can('update', $user)
                        <a href="{{ route('users.edit', $user) }}" class="ft-button-primary">Editar usuario</a>
                    @endcan
                    @can('resetPassword', $user)
                        <a href="{{ route('users.password.edit', $user) }}" class="ft-button-secondary">Restablecer contraseña</a>
                    @endcan
                    @can('deactivate', $user)
                        <form method="POST" action="{{ route('users.deactivate', $user) }}" onsubmit="return confirm('¿Está seguro de desactivar este usuario? Ya no podrá iniciar sesión, pero su información se conservará.')">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="min-h-11 rounded-lg border border-amber-300 px-4 py-2.5 text-sm font-semibold text-amber-900 hover:bg-amber-50">Desactivar usuario</button>
                        </form>
                    @endcan
                    @can('activate', $user)
                        <form method="POST" action="{{ route('users.activate', $user) }}" onsubmit="return confirm('¿Está seguro de activar este usuario?')">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="min-h-11 rounded-lg border border-emerald-300 px-4 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-50">Activar usuario</button>
                        </form>
                    @endcan
                </div>
            </section>
        @endif
    </div>
</x-layouts.authenticated>
