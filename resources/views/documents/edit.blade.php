<x-layouts.authenticated title="Editar borrador">
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('documents.show', $document) }}" class="mb-5 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-[var(--ft-secondary)] underline-offset-4 hover:underline"><span aria-hidden="true">←</span> Volver al documento</a>
        <header class="ft-page-header">
            <div>
                <p class="ft-eyebrow">Preparación de un documento</p>
                <h1 class="ft-page-title">Editar borrador</h1>
                <p class="ft-page-description">Actualice los datos antes de enviar. Los destinatarios y archivos se administran desde el detalle del documento.</p>
            </div>
        </header>
        <x-ui.draft-steps />

        <p class="ft-alert ft-alert-info mt-6">Está editando un borrador. Guardar los cambios no realiza el envío.</p>

        <form method="POST" action="{{ route('documents.update', $document) }}" class="ft-card mt-6 p-5 sm:p-7" aria-label="Actualizar datos del borrador">
            @csrf
            @method('PUT')
            @include('documents._form')
        </form>
    </div>
</x-layouts.authenticated>
