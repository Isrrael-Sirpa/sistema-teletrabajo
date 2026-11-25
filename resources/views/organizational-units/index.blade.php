<x-layouts.authenticated title="Carreras y unidades">
    <div class="ft-page-header">
        <div>
            <p class="ft-eyebrow">Administración institucional</p>
            <h1 class="ft-page-title">Carreras y unidades</h1>
            <p class="ft-page-description">
                Administre las dependencias operativas de la Facultad. La Facultad solo es la raíz institucional y no se muestra como una unidad de trabajo.
            </p>
        </div>

        @can('create', App\Models\OrganizationalUnit::class)
            <a href="{{ route('units.create') }}" class="ft-button-primary shrink-0">
                Agregar unidad
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('units.index') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Filtros de carreras y unidades">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="type" class="block text-sm font-semibold text-slate-800">Tipo de unidad</label>
                <select id="type" name="type" class="mt-2 block w-full bg-white px-3 py-2 text-sm">
                    <option value="">Todas las unidades</option>
                    @foreach (App\Enums\OrganizationalUnitType::cases() as $type)
                        @if ($type !== App\Enums\OrganizationalUnitType::FACULTY)
                            <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="block text-sm font-semibold text-slate-800">Estado</label>
                <select id="status" name="status" class="mt-2 block w-full bg-white px-3 py-2 text-sm">
                    <option value="">Todos los estados</option>
                    <option value="active" @selected(request('status') === 'active')>Activa</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactiva</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap justify-end gap-2">
            <a href="{{ route('units.index') }}" class="ft-button-secondary">Limpiar filtros</a>
            <button type="submit" class="ft-button-primary">Aplicar filtros</button>
        </div>
    </form>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="units-list-title">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4">
            <h2 id="units-list-title" class="font-bold text-slate-900">Listado de unidades organizacionales</h2>
            <p class="text-sm text-slate-600">{{ $units->total() }} {{ $units->total() === 1 ? 'unidad encontrada' : 'unidades encontradas' }}</p>
        </div>
        <div class="ft-table-wrap">
            <table class="ft-data-table min-w-full divide-y divide-slate-200" role="table">
                <caption class="sr-only">Nombre, sigla, tipo, estado y acciones disponibles de cada unidad.</caption>
                <thead class="bg-slate-50" role="rowgroup">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Nombre</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Sigla</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Tipo</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Estado</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white" role="rowgroup">
                    @forelse ($units as $unit)
                        <tr role="row">
                            <td role="cell" data-label="Nombre" class="px-5 py-4">
                                <a href="{{ route('units.show', $unit) }}" class="font-semibold text-blue-800 underline-offset-4 hover:underline">
                                    @if($unit->type === App\Enums\OrganizationalUnitType::CAREER)<span data-career="{{ $unit->acronym }}" class="ft-career-dot mr-2" aria-hidden="true"></span>@endif{{ $unit->name }}
                                </a>
                                @if ($unit->parent)
                                    <p class="mt-1 text-xs text-slate-500">Pertenece a {{ $unit->parent->name }}</p>
                                @endif
                            </td>
                            <td role="cell" data-label="Sigla" class="px-5 py-4 text-sm font-bold text-slate-700">{{ $unit->acronym }}</td>
                            <td role="cell" data-label="Tipo" class="px-5 py-4 text-sm text-slate-700">{{ $unit->type->label() }}</td>
                            <td role="cell" data-label="Estado" class="px-5 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-emerald-50 text-emerald-800' => $unit->is_active,
                                    'bg-slate-200 text-slate-700' => ! $unit->is_active,
                                ])>
                                    {{ $unit->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td role="cell" data-label="Acciones" class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('units.show', $unit) }}" class="ft-button-secondary" aria-label="Ver unidad {{ $unit->name }}">Ver</a>
                                    @can('update', $unit)
                                        <a href="{{ route('units.edit', $unit) }}" class="ft-button-secondary" aria-label="Editar carrera {{ $unit->name }}">Editar</a>
                                    @endcan
                                    @can('deactivate', $unit)
                                        <form method="POST" action="{{ route('units.deactivate', $unit) }}" onsubmit="return confirm('¿Está seguro de desactivar esta carrera? Esta acción no elimina su historial.')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="min-h-10 rounded-lg border border-amber-300 px-3 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-50">Desactivar</button>
                                        </form>
                                    @endcan
                                    @can('activate', $unit)
                                        <form method="POST" action="{{ route('units.activate', $unit) }}" onsubmit="return confirm('¿Está seguro de activar esta carrera?')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="min-h-10 rounded-lg border border-emerald-300 px-3 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-50">Activar</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr role="row">
                            <td role="cell" colspan="5" class="ft-empty-state">
                                @if (request()->filled('type') || request()->filled('status'))
                                    <span class="block font-bold text-slate-800">No hay unidades con estos filtros.</span>
                                    <span class="mt-1 block">Cambie el tipo o estado para ampliar la búsqueda.</span>
                                    <a href="{{ route('units.index') }}" class="ft-button-secondary mt-4">Mostrar todas las unidades</a>
                                @else
                                    <span class="block font-bold text-slate-800">Todavía no hay carreras registradas.</span>
                                    <span class="mt-1 block">Las unidades institucionales aparecerán aquí cuando estén disponibles.</span>
                                    @can('create', App\Models\OrganizationalUnit::class)<a href="{{ route('units.create') }}" class="ft-button-primary mt-4">Agregar carrera</a>@endcan
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($units->hasPages())
        <div class="mt-6">{{ $units->links() }}</div>
    @endif
</x-layouts.authenticated>
