<x-layouts.authenticated title="Crear borrador">
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('documents.index') }}" class="mb-5 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-[var(--ft-secondary)] underline-offset-4 hover:underline"><span aria-hidden="true">←</span> Volver a documentos</a>
        <header class="ft-page-header">
            <h1 class="ft-page-title">Crear borrador</h1>
        </header>
        <x-ui.draft-steps />

        <p class="ft-alert ft-alert-info mt-6">Guardar este formulario no envía el documento. El código oficial se asignará al realizar el envío.</p>

        <form method="POST" action="{{ route('documents.store') }}" class="ft-card mt-6 p-5 sm:p-7" aria-label="Datos del nuevo borrador">
            @csrf
            @include('documents._form')
        </form>
    </div>
</x-layouts.authenticated>
