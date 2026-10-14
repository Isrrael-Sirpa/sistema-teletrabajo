<x-layouts.authenticated :title="$documentType->name">
    <div class="mx-auto max-w-4xl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-700">Tipo documental</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $documentType->name }}</h1>
                <p class="mt-2 text-sm text-slate-600">Código interno: <span class="font-mono">{{ $documentType->code }}</span></p>
            </div>
            <a href="{{ route('document-types.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Volver al listado</a>
        </div>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="document-type-information-title">
            <h2 id="document-type-information-title" class="text-lg font-bold text-slate-950">Información</h2>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Estado</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $documentType->is_active ? 'Activo' : 'Inactivo' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Fecha de creación</dt><dd class="mt-1 text-sm font-semibold text-slate-900">{{ $documentType->created_at->format('d/m/Y H:i') }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Descripción</dt><dd class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $documentType->description ?: 'Sin descripción registrada.' }}</dd></div>
            </dl>
        </section>

        <div class="mt-6 flex flex-wrap gap-3">
            @can('update', $documentType)
                <a href="{{ route('document-types.edit', $documentType) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-institutional-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-institutional-900">Editar tipo</a>
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
