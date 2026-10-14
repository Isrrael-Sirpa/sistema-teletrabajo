<x-layouts.authenticated :title="$user->name">
    <div class="mx-auto max-w-4xl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-700">Detalle del usuario</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $user->name }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ $user->email }}</p>
            </div>
            <a href="{{ route('users.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Volver al listado</a>
        </div>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="user-information-title">
            <h2 id="user-information-title" class="text-lg font-bold text-slate-950">Información institucional</h2>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Rol</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $user->roleName()?->label() ?? 'Sin rol' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Ámbito</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $user->organizationalUnit?->name ?? 'Facultad completa' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Estado</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $user->status->label() }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Último ingreso</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Sin ingresos registrados' }}</dd></div>
            </dl>
        </section>

        <div class="mt-6 flex flex-wrap gap-3">
            @can('update', $user)
                <a href="{{ route('users.edit', $user) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institutional-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-institutional-900">Editar usuario</a>
            @endcan
            @can('resetPassword', $user)
                <a href="{{ route('users.password.edit', $user) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-blue-300 px-4 py-2.5 text-sm font-semibold text-blue-800 hover:bg-blue-50">Restablecer contraseña</a>
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
    </div>
</x-layouts.authenticated>
