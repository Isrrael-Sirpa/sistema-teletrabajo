<x-layouts.authenticated title="Crear usuario">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('users.index') }}" class="ft-button-secondary mb-5">← Volver a usuarios</a>
        <header class="ft-page-header">
            <div>
                <h1 class="ft-page-title">Crear usuario</h1>
            </div>
        </header>

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
