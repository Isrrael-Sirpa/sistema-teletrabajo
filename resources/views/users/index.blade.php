<x-layouts.authenticated title="Usuarios">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-blue-700">Administración institucional</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Usuarios</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Consulte y administre las cuentas que se encuentran dentro de su ámbito autorizado.</p>
        </div>
        @can('create', App\Models\User::class)
            <a href="{{ route('users.create') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-institutional-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-institutional-900">Crear usuario</a>
        @endcan
    </div>

    @if (session('status'))
        <div role="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('users.index') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Filtros de usuarios">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="search" class="block text-sm font-semibold text-slate-800">Nombre o correo</label>
                <input id="search" name="search" type="search" value="{{ request('search') }}" maxlength="100" class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Buscar usuario">
            </div>
            <div>
                <label for="role" class="block text-sm font-semibold text-slate-800">Rol</label>
                <select id="role" name="role" class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">Todos los roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            @if (auth()->user()->hasInstitutionalGlobalScope())
                <div>
                    <label for="unit" class="block text-sm font-semibold text-slate-800">Carrera</label>
                    <select id="unit" name="unit" class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Todas las carreras</option>
                        @foreach ($careers as $career)
                            <option value="{{ $career->id }}" @selected((string) request('unit') === (string) $career->id)>{{ $career->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="status" class="block text-sm font-semibold text-slate-800">Estado</label>
                <select id="status" name="status" class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">Todos los estados</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap justify-end gap-2">
            <a href="{{ route('users.index') }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpiar filtros</a>
            <button type="submit" class="min-h-10 rounded-lg bg-institutional-700 px-4 py-2 text-sm font-bold text-white hover:bg-institutional-900">Aplicar filtros</button>
        </div>
    </form>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="users-list-title">
        <h2 id="users-list-title" class="sr-only">Listado de usuarios</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <caption class="sr-only">Nombre, correo, rol, unidad, estado y acciones disponibles.</caption>
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Usuario</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Rol</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Unidad</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Estado</th>
                        <th scope="col" class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('users.show', $user) }}" class="font-semibold text-blue-800 underline-offset-4 hover:underline">{{ $user->name }}</a>
                                <p class="mt-1 text-sm text-slate-600">{{ $user->email }}</p>
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-700">{{ $user->roleName()?->label() ?? 'Sin rol' }}</td>
                            <td class="px-5 py-4 text-sm text-slate-700">{{ $user->organizationalUnit?->name ?? 'Ámbito global' }}</td>
                            <td class="px-5 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-emerald-50 text-emerald-800' => $user->status === App\Enums\UserStatus::ACTIVE,
                                    'bg-slate-200 text-slate-700' => $user->status === App\Enums\UserStatus::INACTIVE,
                                ])>{{ $user->status->label() }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('users.show', $user) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-slate-600">No se encontraron usuarios con los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($users->hasPages())
        <div class="mt-6">{{ $users->links() }}</div>
    @endif
</x-layouts.authenticated>
