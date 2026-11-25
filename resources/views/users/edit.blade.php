<x-layouts.authenticated title="Editar usuario">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('users.show', $user) }}" class="ft-button-secondary mb-5">← Volver al usuario</a>
        <header class="ft-page-header">
            <div>
                <h1 class="ft-page-title">Editar usuario</h1>
            </div>
        </header>

        <form method="POST" action="{{ route('users.update', $user) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @method('PUT')
            @include('users._form', [
                'cancelUrl' => route('users.show', $user),
                'submitLabel' => 'Guardar cambios',
            ])
        </form>
    </div>
</x-layouts.authenticated>
