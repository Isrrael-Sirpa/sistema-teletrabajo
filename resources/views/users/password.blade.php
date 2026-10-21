<x-layouts.authenticated title="Restablecer contraseña">
    <div class="mx-auto max-w-xl">
        <a href="{{ route('users.show', $user) }}" class="ft-button-secondary mb-5">← Volver al usuario</a>
        <header class="ft-page-header">
            <div>
                <p class="ft-eyebrow">Gestión de usuarios</p>
                <h1 class="ft-page-title">Restablecer contraseña</h1>
                <p class="ft-page-description">Defina una nueva contraseña segura para <strong>{{ $user->name }}</strong>. La contraseña actual nunca se muestra.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('users.password.update', $user) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div role="alert" class="ft-alert ft-alert-error mb-6" tabindex="-1" data-form-errors>
                    <p class="font-semibold">Revise la contraseña ingresada.</p>
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <p id="password-help" class="ft-alert ft-alert-info">Use al menos 12 caracteres con mayúsculas, minúsculas, números y símbolos. Comparta la nueva contraseña con la persona únicamente por un medio seguro.</p>

            <div class="mt-5">
                <label for="password" class="block text-sm font-semibold text-slate-800">Nueva contraseña</label>
                <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="password-help{{ $errors->has('password') ? ' password-error' : '' }}" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
                @error('password')<p id="password-error" class="ft-field-error">{{ $message }}</p>@enderror
            </div>
            <div class="mt-5">
                <label for="password_confirmation" class="block text-sm font-semibold text-slate-800">Confirmar nueva contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}" @error('password_confirmation') aria-describedby="password-confirmation-error" @enderror class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
                @error('password_confirmation')<p id="password-confirmation-error" class="ft-field-error">{{ $message }}</p>@enderror
            </div>

            <div class="ft-form-actions">
                <a href="{{ route('users.show', $user) }}" class="ft-button-secondary">Cancelar</a>
                <button type="submit" class="ft-button-primary">Guardar nueva contraseña</button>
            </div>
        </form>
    </div>
</x-layouts.authenticated>
