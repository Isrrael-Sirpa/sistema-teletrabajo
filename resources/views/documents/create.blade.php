<x-layouts.authenticated title="Crear borrador">
    <div class="mx-auto max-w-4xl">
        <p class="text-sm font-semibold text-blue-700">Documentos</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Crear borrador</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">El documento permanecerá como borrador. En esta fase no se asignan destinatarios, archivos ni código oficial.</p>
        <x-ui.draft-steps />

        <form method="POST" action="{{ route('documents.store') }}" class="ft-card mt-6 p-5 sm:p-7">
            @csrf
            @include('documents._form')
        </form>
    </div>
</x-layouts.authenticated>
