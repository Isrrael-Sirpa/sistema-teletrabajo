<x-layouts.authenticated title="Agregar carrera">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('units.index') }}" class="text-sm font-semibold text-blue-800 underline-offset-4 hover:underline">← Volver al listado</a>
        <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Agregar carrera</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Registre una carrera perteneciente a la Facultad de Tecnología.</p>

        <form method="POST" action="{{ route('units.store') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            @include('organizational-units._form', [
                'unit' => null,
                'faculty' => $faculty,
                'cancelUrl' => route('units.index'),
                'submitLabel' => 'Crear carrera',
            ])
        </form>
    </div>
</x-layouts.authenticated>
