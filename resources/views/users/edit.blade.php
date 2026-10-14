<x-layouts.authenticated title="Editar usuario">
    <div class="mx-auto max-w-3xl">
        <p class="text-sm font-semibold text-blue-700">Gestión de usuarios</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Editar usuario</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Actualice únicamente los datos necesarios. El historial y la cuenta se conservarán.</p>

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
