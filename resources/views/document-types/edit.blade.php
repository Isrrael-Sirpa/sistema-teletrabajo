<x-layouts.authenticated title="Editar tipo documental">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('document-types.show', $documentType) }}" class="ft-button-secondary mb-5">← Volver al tipo documental</a>
        <header class="ft-page-header">
            <div>
                <p class="ft-eyebrow">Catálogo institucional</p>
                <h1 class="ft-page-title">Editar tipo documental</h1>
                <p class="ft-page-description">Actualice el nombre o la explicación de {{ $documentType->name }} sin afectar documentos históricos.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('document-types.update', $documentType) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @method('PUT')
            @include('document-types._form', [
                'cancelUrl' => route('document-types.show', $documentType),
                'submitLabel' => 'Guardar cambios',
            ])
        </form>
    </div>
</x-layouts.authenticated>
