<x-layouts.authenticated title="Editar tipo documental">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('document-types.index') }}" class="ft-button-secondary mb-5">← Volver al listado</a>
        <header class="ft-page-header">
            <div>
                <h1 class="ft-page-title">Editar tipo documental</h1>
            </div>
        </header>

        <form method="POST" action="{{ route('document-types.update', $documentType) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @method('PUT')
            @include('document-types._form', [
                'cancelUrl' => route('document-types.index'),
                'submitLabel' => 'Guardar cambios',
            ])
        </form>
    </div>
</x-layouts.authenticated>
