<x-layouts.authenticated title="Usuarios">
    <div class="ft-page-header">
        <div>
            <p class="ft-eyebrow">Administración institucional</p>
            <h1 class="ft-page-title">Usuarios</h1>
            <p class="ft-page-description">Consulte y administre las cuentas que se encuentran dentro de su ámbito autorizado.</p>
        </div>
        @can('create', App\Models\User::class)
            <a href="{{ route('users.create') }}" class="ft-button-primary shrink-0"><x-ui.icon name="plus" /> Crear usuario</a>
        @endcan
    </div>

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
            <a href="{{ route('users.index') }}" class="ft-button-secondary">Limpiar filtros</a>
            <button type="submit" class="ft-button-primary">Aplicar filtros</button>
        </div>
    </form>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="users-list-title">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4">
            <h2 id="users-list-title" class="font-bold text-slate-900">Listado de usuarios</h2>
            <p class="text-sm text-slate-600">{{ $users->total() }} {{ $users->total() === 1 ? 'cuenta encontrada' : 'cuentas encontradas' }}</p>
        </div>
        <div class="ft-table-wrap">
            <table class="ft-data-table min-w-full divide-y divide-slate-200" role="table">
                <caption class="sr-only">Nombre, correo, rol, unidad, estado y acciones disponibles.</caption>
                <thead class="bg-slate-50" role="rowgroup">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Usuario</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Rol</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Unidad</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Estado</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white" role="rowgroup">
                    @forelse ($users as $user)
                        <tr role="row">
                            <td role="cell" data-label="Usuario" class="px-5 py-4">
                                <a href="{{ route('users.show', $user) }}" class="font-semibold text-blue-800 underline-offset-4 hover:underline">{{ $user->name }}</a>
                                <p class="mt-1 break-all text-sm text-slate-600">{{ $user->email }}</p>
                            </td>
                            <td role="cell" data-label="Rol" class="px-5 py-4 text-sm text-slate-700">{{ $user->roleName()?->label() ?? 'Sin rol' }}</td>
                            <td role="cell" data-label="Unidad" class="px-5 py-4 text-sm text-slate-700">{{ $user->organizationalUnit?->name ?? 'Ámbito global' }}</td>
                            <td role="cell" data-label="Estado" class="px-5 py-4">
                                <x-ui.status-badge :status="$user->status" />
                            </td>
                            <td role="cell" data-label="Acciones" class="px-5 py-4 text-right">
                                <a href="{{ route('users.show', $user) }}" class="ft-button-secondary" aria-label="Ver usuario {{ $user->name }}">Ver usuario</a>
                            </td>
                        </tr>
                    @empty
                        <tr role="row"><td role="cell" colspan="5" class="ft-empty-state"><span class="block font-bold text-slate-800">No se encontraron usuarios.</span><span class="mt-1 block">Pruebe con otro nombre, rol o estado, o limpie los filtros.</span><a href="{{ route('users.index') }}" class="ft-button-secondary mt-4">Mostrar todos los usuarios</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($users->hasPages())
        <div class="mt-6">{{ $users->links() }}</div>
    @endif
</x-layouts.authenticated>
