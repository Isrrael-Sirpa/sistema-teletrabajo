<x-layouts.authenticated title="Editar carrera">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('units.show', $unit) }}" class="text-sm font-semibold text-blue-800 underline-offset-4 hover:underline">← Volver al detalle</a>
        <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Editar carrera</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Actualice los datos institucionales sin alterar el tipo ni la unidad padre.</p>

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
