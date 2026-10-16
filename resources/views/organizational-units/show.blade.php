<x-layouts.authenticated :title="$unit->name" :career-unit="$unit->type === App\Enums\OrganizationalUnitType::CAREER ? $unit : null">
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('units.index') }}" class="text-sm font-semibold text-blue-800 underline-offset-4 hover:underline">← Volver al listado</a>

        @if (session('status'))
            <div role="status" class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="unit-title">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-blue-700">{{ $unit->type->label() }}</p>
                    <h1 id="unit-title" class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $unit->name }}</h1>
                    <p class="mt-2 flex items-center gap-2 text-sm font-medium text-slate-600"><span class="ft-career-dot" aria-hidden="true"></span> Sigla: {{ $unit->acronym }}</p>
                </div>
                <span @class([
                    'inline-flex w-fit rounded-full px-3 py-1 text-sm font-bold',
                    'bg-emerald-50 text-emerald-800' => $unit->is_active,
                    'bg-slate-200 text-slate-700' => ! $unit->is_active,
                ])>{{ $unit->is_active ? 'Activa' : 'Inactiva' }}</span>
            </div>

            <dl class="mt-7 grid gap-6 border-t border-slate-200 pt-6 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-semibold text-slate-700">Unidad padre</dt>
                    <dd class="mt-1 text-sm text-slate-600">{{ $unit->parent?->name ?? 'Unidad raíz institucional' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm font-semibold text-slate-700">Descripción</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $unit->description ?: 'Sin descripción registrada.' }}</dd>
                </div>
            </dl>

            @if ($unit->type === App\Enums\OrganizationalUnitType::CAREER)
                <div class="mt-7 flex flex-wrap gap-3 border-t border-slate-200 pt-6">
                    @can('update', $unit)
                        <a href="{{ route('units.edit', $unit) }}" class="ft-button-primary">Editar carrera</a>
                    @endcan
                    @can('deactivate', $unit)
                        <form method="POST" action="{{ route('units.deactivate', $unit) }}" onsubmit="return confirm('¿Está seguro de desactivar esta carrera? Esta acción no elimina su historial.')">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="min-h-11 rounded-lg border border-amber-300 px-4 py-2.5 text-sm font-semibold text-amber-900 hover:bg-amber-50">Desactivar carrera</button>
                        </form>
                    @endcan
                    @can('activate', $unit)
                        <form method="POST" action="{{ route('units.activate', $unit) }}" onsubmit="return confirm('¿Está seguro de activar esta carrera?')">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="min-h-11 rounded-lg border border-emerald-300 px-4 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-50">Activar carrera</button>
                        </form>
                    @endcan
                </div>
            @else
                <p class="mt-7 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm leading-6 text-blue-900">
                    Esta unidad institucional está protegida y no puede editarse, desactivarse ni eliminarse desde el CRUD ordinario.
                </p>
            @endif
        </section>
    </div>
</x-layouts.authenticated>
