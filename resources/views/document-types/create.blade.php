<x-layouts.authenticated title="Crear tipo documental">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('document-types.index') }}" class="ft-button-secondary mb-5">← Volver a tipos documentales</a>
        <header class="ft-page-header">
            <div>
                <p class="ft-eyebrow">Catálogo institucional</p>
                <h1 class="ft-page-title">Crear tipo documental</h1>
                <p class="ft-page-description">Agregue una opción clara que podrá utilizarse en documentos futuros.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('document-types.store') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @include('document-types._form', [
                'documentType' => null,
                'cancelUrl' => route('document-types.index'),
                'submitLabel' => 'Crear tipo documental',
            ])
        </form>
    </div>
</x-layouts.authenticated>
