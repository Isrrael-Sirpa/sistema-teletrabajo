<x-layouts.authenticated title="Restablecer contraseña">
    <div class="mx-auto max-w-xl">
        <p class="text-sm font-semibold text-blue-700">Gestión de usuarios</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">Restablecer contraseña</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Defina una contraseña temporal segura para <strong>{{ $user->name }}</strong>. La contraseña actual nunca se muestra.</p>

        <form method="POST" action="{{ route('users.password.update', $user) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <p class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">Use al menos 12 caracteres con mayúsculas, minúsculas, números y símbolos.</p>

            <div class="mt-5">
                <label for="password" class="block text-sm font-semibold text-slate-800">Nueva contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
            </div>
            <div class="mt-5">
                <label for="password_confirmation" class="block text-sm font-semibold text-slate-800">Confirmar nueva contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
            </div>

            <div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
                <a href="{{ route('users.show', $user) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
                <button type="submit" class="ft-button-primary">Guardar nueva contraseña</button>
            </div>
        </form>
    </div>
</x-layouts.authenticated>
