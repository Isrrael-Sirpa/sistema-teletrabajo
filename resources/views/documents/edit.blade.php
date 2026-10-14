<x-layouts.authenticated title="Editar borrador">
    <div class="mx-auto max-w-4xl">
        <p class="text-sm font-semibold text-blue-700">Documentos</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Editar borrador</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Solo pueden modificarse documentos que continúan en estado borrador.</p>

        <form method="POST" action="{{ route('documents.update', $document) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @method('PUT')
            @include('documents._form')
        </form>
    </div>
</x-layouts.authenticated>
