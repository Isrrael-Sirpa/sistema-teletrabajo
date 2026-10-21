<x-layouts.authenticated title="Editar carrera" :career-unit="$unit">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('units.show', $unit) }}" class="ft-button-secondary mb-5">← Volver al detalle</a>
        <header class="ft-page-header">
            <div>
                <p class="ft-eyebrow">Administración institucional</p>
                <h1 class="ft-page-title">Editar carrera</h1>
                <p class="ft-page-description">Actualice los datos de {{ $unit->name }}. La carrera seguirá perteneciendo a {{ $unit->parent->name }}.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('units.update', $unit) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            @method('PUT')
            @include('organizational-units._form', [
                'unit' => $unit,
                'faculty' => $unit->parent,
                'cancelUrl' => route('units.show', $unit),
                'submitLabel' => 'Guardar cambios',
            ])
        </form>
    </div>
</x-layouts.authenticated>
