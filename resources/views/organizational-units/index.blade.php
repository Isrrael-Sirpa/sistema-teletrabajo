<x-layouts.authenticated title="Carreras y unidades">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-blue-700">Administración institucional</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Carreras y unidades</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                La Facultad y Kardex son unidades protegidas. Las carreras pueden agregarse, actualizarse y desactivarse sin eliminar su historial.
            </p>
        </div>

        @can('create', App\Models\OrganizationalUnit::class)
            <a href="{{ route('units.create') }}" class="ft-button-primary shrink-0">
                Agregar carrera
            </a>
        @endcan
    </div>

    @if (session('status'))
        <div role="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ session('status') }}
        </div>
    @endif

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="units-list-title">
        <h2 id="units-list-title" class="sr-only">Listado de unidades organizacionales</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <caption class="sr-only">Nombre, sigla, tipo, estado y acciones disponibles de cada unidad.</caption>
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Nombre</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Sigla</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Tipo</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Estado</th>
                        <th scope="col" class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($units as $unit)
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('units.show', $unit) }}" class="font-semibold text-blue-800 underline-offset-4 hover:underline">
                                    @if($unit->type === App\Enums\OrganizationalUnitType::CAREER)<span data-career="{{ $unit->acronym }}" class="ft-career-dot mr-2" aria-hidden="true"></span>@endif{{ $unit->name }}
                                </a>
                                @if ($unit->parent)
                                    <p class="mt-1 text-xs text-slate-500">Pertenece a {{ $unit->parent->name }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-sm font-bold text-slate-700">{{ $unit->acronym }}</td>
                            <td class="px-5 py-4 text-sm text-slate-700">{{ $unit->type->label() }}</td>
                            <td class="px-5 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-emerald-50 text-emerald-800' => $unit->is_active,
                                    'bg-slate-200 text-slate-700' => ! $unit->is_active,
                                ])>
                                    {{ $unit->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('units.show', $unit) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver</a>
                                    @can('update', $unit)
                                        <a href="{{ route('units.edit', $unit) }}" class="inline-flex min-h-10 items-center rounded-lg border border-blue-300 px-3 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-50">Editar</a>
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
                        <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-slate-600"><span class="block font-bold text-slate-800">Todavía no hay carreras registradas.</span><span class="mt-1 block">Las unidades institucionales aparecerán aquí cuando estén disponibles.</span></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($units->hasPages())
        <div class="mt-6">{{ $units->links() }}</div>
    @endif
</x-layouts.authenticated>
