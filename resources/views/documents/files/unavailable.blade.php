<x-layouts.authenticated :title="$missing ? 'Archivo no disponible' : 'Visualización no disponible'">
    <section class="ft-card mx-auto max-w-2xl p-6 sm:p-8">
        <h1 class="text-xl font-bold text-slate-950">{{ $missing ? 'Archivo no disponible' : 'Visualización no disponible' }}</h1>
        <p class="mt-3 break-all font-semibold text-slate-800">{{ $file->original_name }}</p>
        <p class="mt-3 text-sm leading-6 text-slate-600">{{ $missing ? 'El archivo no está disponible en este momento. Informe al responsable del sistema para que revise el adjunto.' : 'Este archivo no puede visualizarse directamente en el sistema.' }}</p>
        <div class="mt-5 flex flex-wrap gap-3">
            @unless ($missing)
                <a href="{{ route('documents.files.download', [$document, $file]) }}" class="ft-button-primary">Descargar archivo</a>
            @endunless
            <a href="{{ route('documents.show', $document) }}#files-title" class="ft-button-secondary">Volver al documento</a>
        </div>
    </section>
</x-layouts.authenticated>
