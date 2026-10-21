<x-layouts.authenticated :title="$documentType->name">
    <div class="mx-auto max-w-4xl">
        <div class="ft-page-header">
            <div>
                <p class="ft-eyebrow">Catálogo institucional · Tipo documental</p>
                <h1 class="ft-page-title">{{ $documentType->name }}</h1>
                <p class="ft-page-description">Consulte su uso y disponibilidad para documentos nuevos.</p>
            </div>
            <a href="{{ route('document-types.index') }}" class="ft-button-secondary">Volver al listado</a>
        </div>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="document-type-information-title">
            <h2 id="document-type-information-title" class="text-lg font-bold text-slate-950">Información</h2>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Estado</dt><dd class="mt-1"><span class="ft-badge" data-status="{{ $documentType->is_active ? 'active' : 'inactive' }}">{{ $documentType->is_active ? 'Activo' : 'Inactivo' }}</span><p class="ft-field-help">{{ $documentType->is_active ? 'Disponible para crear documentos nuevos.' : 'No disponible para documentos nuevos. El historial se conserva.' }}</p></dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Fecha de creación</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $documentType->created_at->format('d/m/Y H:i') }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Descripción</dt><dd class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $documentType->description ?: 'Sin descripción registrada.' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Código interno</dt><dd class="mt-1 break-all font-mono text-sm text-slate-700">{{ $documentType->code }}</dd></div>
            </dl>
        </section>

        <div class="mt-6 flex flex-wrap gap-3">
            @can('update', $documentType)
                <a href="{{ route('document-types.edit', $documentType) }}" class="ft-button-primary">Editar tipo</a>
            @endcan
            @can('deactivate', $documentType)
                <form method="POST" action="{{ route('document-types.deactivate', $documentType) }}" onsubmit="return confirm('¿Está seguro de desactivar este tipo? No se eliminarán documentos históricos, pero dejará de estar disponible para documentos nuevos.')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="min-h-11 rounded-lg border border-amber-300 px-4 py-2.5 text-sm font-semibold text-amber-900 hover:bg-amber-50">Desactivar tipo</button>
                </form>
            @endcan
            @can('activate', $documentType)
                <form method="POST" action="{{ route('document-types.activate', $documentType) }}" onsubmit="return confirm('¿Está seguro de activar este tipo documental?')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="min-h-11 rounded-lg border border-emerald-300 px-4 py-2.5 text-sm font-semibold text-emerald-900 hover:bg-emerald-50">Activar tipo</button>
                </form>
            @endcan
        </div>
    </div>
</x-layouts.authenticated>
