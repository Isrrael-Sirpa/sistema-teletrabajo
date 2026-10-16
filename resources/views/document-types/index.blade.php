<x-layouts.authenticated title="Tipos documentales">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-blue-700">Catálogo institucional</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Tipos documentales</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Los tipos inactivos conservan su historial, pero no estarán disponibles para crear documentos nuevos.</p>
        </div>
        @can('create', App\Models\DocumentType::class)
            <a href="{{ route('document-types.create') }}" class="ft-button-primary shrink-0">Crear tipo documental</a>
        @endcan
    </div>

    @if (session('status'))
        <div role="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="document-types-list-title">
        <h2 id="document-types-list-title" class="sr-only">Listado de tipos documentales</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <caption class="sr-only">Nombre, descripción, estado y acciones disponibles.</caption>
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Nombre</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Descripción</th>
                        <th scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Estado</th>
                        <th scope="col" class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($documentTypes as $documentType)
                        <tr>
                            <td class="px-5 py-4"><a href="{{ route('document-types.show', $documentType) }}" class="font-semibold text-blue-800 underline-offset-4 hover:underline">{{ $documentType->name }}</a></td>
                            <td class="max-w-xl px-5 py-4 text-sm text-slate-600">{{ $documentType->description ?: 'Sin descripción' }}</td>
                            <td class="px-5 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-emerald-50 text-emerald-800' => $documentType->is_active,
                                    'bg-slate-200 text-slate-700' => ! $documentType->is_active,
                                ])>{{ $documentType->is_active ? 'Activo' : 'Inactivo' }}</span>
                            </td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('document-types.show', $documentType) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-slate-600">No existen tipos documentales registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($documentTypes->hasPages())
        <div class="mt-6">{{ $documentTypes->links() }}</div>
    @endif
</x-layouts.authenticated>
