<x-layouts.authenticated title="Tipos documentales">
    <div class="ft-page-header">
        <div>
            <p class="ft-eyebrow">Catálogo institucional</p>
            <h1 class="ft-page-title">Tipos documentales</h1>
            <p class="ft-page-description">Organice las opciones disponibles al crear un documento. Los tipos inactivos conservan su historial, pero no estarán disponibles para crear documentos nuevos.</p>
        </div>
        @can('create', App\Models\DocumentType::class)
            <a href="{{ route('document-types.create') }}" class="ft-button-primary shrink-0">Crear tipo documental</a>
        @endcan
    </div>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="document-types-list-title">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-4">
            <h2 id="document-types-list-title" class="font-bold text-slate-900">Listado de tipos documentales</h2>
            <p class="text-sm text-slate-600">{{ $documentTypes->total() }} {{ $documentTypes->total() === 1 ? 'tipo registrado' : 'tipos registrados' }}</p>
        </div>
        <div class="ft-table-wrap">
            <table class="ft-data-table min-w-full divide-y divide-slate-200" role="table">
                <caption class="sr-only">Nombre, descripción, estado y acciones disponibles.</caption>
                <thead class="bg-slate-50" role="rowgroup">
                    <tr role="row">
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Nombre</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Descripción</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-600">Estado</th>
                        <th role="columnheader" scope="col" class="px-5 py-3 text-right text-xs font-bold uppercase tracking-wide text-slate-600">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white" role="rowgroup">
                    @forelse ($documentTypes as $documentType)
                        <tr role="row">
                            <td role="cell" data-label="Nombre" class="px-5 py-4"><a href="{{ route('document-types.show', $documentType) }}" class="font-semibold text-blue-800 underline-offset-4 hover:underline">{{ $documentType->name }}</a></td>
                            <td role="cell" data-label="Descripción" class="max-w-xl px-5 py-4 text-sm text-slate-600">{{ $documentType->description ?: 'Sin descripción' }}</td>
                            <td role="cell" data-label="Estado" class="px-5 py-4">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-emerald-50 text-emerald-800' => $documentType->is_active,
                                    'bg-slate-200 text-slate-700' => ! $documentType->is_active,
                                ])>{{ $documentType->is_active ? 'Activo' : 'Inactivo' }}</span>
                            </td>
                            <td role="cell" data-label="Acciones" class="px-5 py-4 text-right"><a href="{{ route('document-types.show', $documentType) }}" class="ft-button-secondary" aria-label="Ver tipo documental {{ $documentType->name }}">Ver tipo</a></td>
                        </tr>
                    @empty
                        <tr role="row"><td role="cell" colspan="4" class="ft-empty-state"><span class="block font-bold text-slate-800">No existen tipos documentales registrados.</span><span class="mt-1 block">Agregue una opción para que pueda seleccionarse al crear documentos.</span>@can('create', App\Models\DocumentType::class)<a href="{{ route('document-types.create') }}" class="ft-button-primary mt-4">Crear tipo documental</a>@endcan</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($documentTypes->hasPages())
        <div class="mt-6">{{ $documentTypes->links() }}</div>
    @endif
</x-layouts.authenticated>
