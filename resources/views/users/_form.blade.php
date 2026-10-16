@php
    $managedUser = $user ?? null;
    $selectedRole = old('role', $managedUser?->roleName()?->value ?? '');
    $selectedStatus = old('status', $managedUser?->status->value ?? App\Enums\UserStatus::ACTIVE->value);
    $selectedUnit = old('organizational_unit_id', $managedUser?->organizational_unit_id);
    $requiresCareer = $selectedRole !== '' && ! in_array($selectedRole, [
        App\Enums\RoleName::ADMINISTRATOR->value,
        App\Enums\RoleName::CO_ADMINISTRATOR->value,
    ], true);
@endphp

@if ($errors->any())
    <div role="alert" aria-live="polite" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
        <p class="font-semibold">Revise los datos ingresados.</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="space-y-5" data-user-form>
    <div>
        <label for="name" class="block text-sm font-semibold text-slate-800">Nombre completo</label>
        <input id="name" name="name" type="text" value="{{ old('name', $managedUser?->name) }}" required maxlength="255" autocomplete="name" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
        @error('name')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-semibold text-slate-800">Correo electrónico</label>
        <input id="email" name="email" type="email" value="{{ old('email', $managedUser?->email) }}" required maxlength="255" autocomplete="email" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
        <p class="mt-1.5 text-xs text-slate-500">Se guardará en minúsculas y no puede repetirse.</p>
        @error('email')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="role" class="block text-sm font-semibold text-slate-800">Rol institucional</label>
            <select id="role" name="role" required data-role-select @disabled($isSelf ?? false) class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
                <option value="">Seleccione un rol</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected($selectedRole === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            @if ($isSelf ?? false)
                <input type="hidden" name="role" value="{{ $selectedRole }}">
                <p class="mt-1.5 text-xs text-slate-500">Por seguridad, no puede cambiar su propio rol.</p>
            @endif
            @error('role')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="status" class="block text-sm font-semibold text-slate-800">Estado</label>
            <select id="status" name="status" required @disabled($isSelf ?? false) class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            @if ($isSelf ?? false)
                <input type="hidden" name="status" value="{{ $selectedStatus }}">
                <p class="mt-1.5 text-xs text-slate-500">No puede desactivar su propia cuenta.</p>
            @endif
            @error('status')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    </div>

    <div data-unit-section @if (! $requiresCareer) hidden @endif>
        <label for="organizational_unit_id" class="block text-sm font-semibold text-slate-800">Carrera</label>
        <select id="organizational_unit_id" name="organizational_unit_id" data-unit-select @required($requiresCareer) @disabled(! $requiresCareer) class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
            <option value="">Seleccione una carrera activa</option>
            @foreach ($careers as $career)
                <option value="{{ $career->id }}" @selected((string) $selectedUnit === (string) $career->id)>{{ $career->name }}</option>
            @endforeach
        </select>
        <p class="mt-1.5 text-xs text-slate-500">Jefatura, Secretarías, Auxiliares y usuarios Extra deben pertenecer a una carrera activa.</p>
        @error('organizational_unit_id')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>

    @if (! $managedUser)
        <fieldset class="rounded-xl border border-slate-200 p-4">
            <legend class="px-1 text-sm font-bold text-slate-800">Contraseña inicial</legend>
            <p class="mb-4 text-sm text-slate-600">Debe tener al menos 12 caracteres, mayúsculas, minúsculas, números y símbolos.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-800">Contraseña</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
                    @error('password')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-slate-800">Confirmar contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
                </div>
            </div>
        </fieldset>
    @else
        <p class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            La contraseña no cambia al guardar este formulario. Utilice la acción separada de restablecimiento cuando sea necesario.
        </p>
    @endif
</div>

<div class="mt-7 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
    <a href="{{ $cancelUrl }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
    <button type="submit" class="ft-button-primary">{{ $submitLabel }}</button>
</div>
