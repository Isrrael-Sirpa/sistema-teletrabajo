<x-layouts.authenticated title="Crear usuario">
    <div class="mx-auto max-w-3xl">
        <p class="text-sm font-semibold text-blue-700">Gestión de usuarios</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Crear usuario</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Registre los datos institucionales y asigne el rol y la carrera que correspondan.</p>

        <form method="POST" action="{{ route('users.store') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @include('users._form', [
                'user' => null,
                'cancelUrl' => route('users.index'),
                'submitLabel' => 'Crear usuario',
                'isSelf' => false,
            ])
        </form>
    </div>
</x-layouts.authenticated>
