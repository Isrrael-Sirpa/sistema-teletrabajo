<x-layouts.authenticated title="Editar tipo documental">
    <div class="mx-auto max-w-3xl">
        <p class="text-sm font-semibold text-blue-700">Catálogo institucional</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Editar tipo documental</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Actualice el nombre o la explicación sin afectar documentos históricos.</p>

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
