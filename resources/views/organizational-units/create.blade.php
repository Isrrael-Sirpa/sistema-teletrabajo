<x-layouts.authenticated title="Agregar unidad">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('units.index') }}" class="ft-button-secondary mb-5">← Volver al listado</a>
        <header class="ft-page-header">
            <div>
                <h1 class="ft-page-title">Agregar unidad</h1>
            </div>
        </header>

        <form method="POST" action="{{ route('units.store') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @csrf
            @include('organizational-units._form', [
                'unit' => null,
                'parents' => $parents,
                'cancelUrl' => route('units.index'),
                'submitLabel' => 'Crear unidad',
            ])
        </form>
    </div>
</x-layouts.authenticated>
