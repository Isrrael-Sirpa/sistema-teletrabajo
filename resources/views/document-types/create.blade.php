<x-layouts.authenticated title="Crear tipo documental">
    <div class="mx-auto max-w-3xl">
        <p class="text-sm font-semibold text-blue-700">Catálogo institucional</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Crear tipo documental</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Agregue una opción clara que podrá utilizarse en documentos futuros.</p>

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
