<x-layouts.authenticated title="Agregar carrera">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('units.index') }}" class="ft-button-secondary mb-5">← Volver al listado</a>
        <header class="ft-page-header">
            <div>
                <p class="ft-eyebrow">Administración institucional</p>
                <h1 class="ft-page-title">Agregar carrera</h1>
                <p class="ft-page-description">Registre una carrera perteneciente a la Facultad de Tecnología.</p>
            </div>
        </header>

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
