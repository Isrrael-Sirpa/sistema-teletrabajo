@php
    $managedUser = $user ?? null;
    $selectedRole = old('role', $managedUser?->roleName()?->value ?? '');
    $selectedStatus = old('status', $managedUser?->status->value ?? App\Enums\UserStatus::ACTIVE->value);
    $selectedUnit = old('organizational_unit_id', $managedUser?->organizational_unit_id);
    $requiresUnit = $selectedRole !== '';
@endphp

@if ($errors->any())
    <div role="alert" class="ft-alert ft-alert-error mb-6" tabindex="-1" data-form-errors>
        <p class="font-semibold">Revise los datos ingresados.</p>
        <ul class="mt-1 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="space-y-5" data-user-form>
    <div class="ft-form-section">
        <h2 class="ft-form-section-title">Datos personales</h2>
    </div>
    <div class="grid gap-5 sm:grid-cols-[9rem_1fr]">
    <div>
        <label for="academic_title" class="block text-sm font-semibold text-slate-800">Título <span class="font-normal">(opcional)</span></label>
        <select id="academic_title" name="academic_title" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950">
            <option value="">Sin título</option>
            @foreach ($academicTitles as $title)<option value="{{ $title->value }}" @selected(old('academic_title', $managedUser?->academic_title) === $title->value)>{{ $title->value }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="name" class="block text-sm font-semibold text-slate-800">Nombre completo</label>
        <input id="name" name="name" type="text" value="{{ old('name', $managedUser?->name) }}" required maxlength="255" autocomplete="name" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="name-error" @enderror>
        @error('name')<p id="name-error" class="ft-field-error">{{ $message }}</p>@enderror
    </div>
    </div>

    <div>
        <label for="email" class="block text-sm font-semibold text-slate-800">Correo electrónico</label>
        <input id="email" name="email" type="email" value="{{ old('email', $managedUser?->email) }}" required maxlength="255" autocomplete="email" spellcheck="false" autocapitalize="none" class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="email-help{{ $errors->has('email') ? ' email-error' : '' }}">
        <p id="email-help" class="ft-field-help">Se guardará en minúsculas y no puede repetirse.</p>
        @error('email')<p id="email-error" class="ft-field-error">{{ $message }}</p>@enderror
    </div>

    <div class="ft-form-section">
        <h2 class="ft-form-section-title">Datos institucionales</h2>
    </div>
    <div class="grid gap-5 {{ $managedUser ? 'sm:grid-cols-2' : '' }}">
        <div>
            <label for="role" class="block text-sm font-semibold text-slate-800">Rol</label>
            <select id="role" name="role" required data-role-select @disabled($isSelf ?? false) aria-invalid="{{ $errors->has('role') ? 'true' : 'false' }}" @error('role') aria-describedby="role-error" @enderror class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
                <option value="">Seleccione el tipo de usuario</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected($selectedRole === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            @if ($isSelf ?? false)
                <input type="hidden" name="role" value="{{ $selectedRole }}">
                <p class="mt-1.5 text-xs text-slate-500">Por seguridad, no puede cambiar su propio rol.</p>
            @endif
            @error('role')<p id="role-error" class="ft-field-error">{{ $message }}</p>@enderror
        </div>

        @if ($managedUser)
        <div>
            <label for="status" class="block text-sm font-semibold text-slate-800">Estado</label>
            <select id="status" name="status" required @disabled($isSelf ?? false) aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" @error('status') aria-describedby="status-error" @enderror class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            @if ($isSelf ?? false)
                <input type="hidden" name="status" value="{{ $selectedStatus }}">
                <p class="mt-1.5 text-xs text-slate-500">No puede desactivar su propia cuenta.</p>
            @endif
            @error('status')<p id="status-error" class="ft-field-error">{{ $message }}</p>@enderror
        </div>
        @endif
    </div>

    <div data-unit-section @if (! $requiresUnit) hidden @endif>
        <label for="organizational_unit_id" class="block text-sm font-semibold text-slate-800">Unidad</label>
        <p data-fixed-unit hidden class="mt-2 flex min-h-12 items-center rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-base text-slate-950">Departamento de Computación</p>
        <input type="hidden" name="organizational_unit_id" data-fixed-unit-input disabled>
        <select id="organizational_unit_id" name="organizational_unit_id" data-unit-select @required($requiresUnit) @disabled(! $requiresUnit) aria-invalid="{{ $errors->has('organizational_unit_id') ? 'true' : 'false' }}" @error('organizational_unit_id') aria-describedby="unit-error" @enderror class="mt-2 block min-h-12 w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
            <option value="">Seleccione una unidad activa</option>
            @foreach ($units as $unit)
                <option value="{{ $unit->id }}" data-unit-type="{{ $unit->type->value }}" data-unit-acronym="{{ $unit->acronym }}" data-default-label="{{ $unit->name }} — {{ $unit->type->label() }}" @selected((string) $selectedUnit === (string) $unit->id)>{{ $unit->name }} — {{ $unit->type->label() }}</option>
            @endforeach
        </select>
        @error('organizational_unit_id')<p id="unit-error" class="ft-field-error">{{ $message }}</p>@enderror
    </div>

    @if (! $managedUser)
        <fieldset class="rounded-xl border border-slate-200 p-4">
            <legend class="px-1 text-sm font-bold text-slate-800">Contraseña inicial</legend>
            <p id="password-help" class="mb-4 text-sm text-slate-600">Debe tener al menos 12 caracteres, mayúsculas, minúsculas, números y símbolos.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="password" class="block text-sm font-semibold text-slate-800">Contraseña</label>
                    <div class="relative mt-2">
                        <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="password-help{{ $errors->has('password') ? ' password-error' : '' }}" class="block min-h-12 w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-3.5 pr-12 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
                        <button type="button" data-password-toggle aria-controls="password" aria-label="Mostrar contraseña" aria-pressed="false" class="absolute inset-y-0 right-0 grid w-12 place-items-center text-slate-600 hover:text-[var(--ft-primary)] focus:outline-none focus:ring-2 focus:ring-blue-600" hidden><span data-password-eye-visible><x-ui.icon name="eye" /></span><span data-password-eye-hidden hidden><x-ui.icon name="eye-off" /></span></button>
                    </div>
                    @error('password')<p id="password-error" class="ft-field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-slate-800">Confirmar contraseña</label>
                    <div class="relative mt-2">
                        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}" @error('password_confirmation') aria-describedby="password-confirmation-error" @enderror class="block min-h-12 w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-3.5 pr-12 text-base text-slate-950 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20">
                        <button type="button" data-password-toggle aria-controls="password_confirmation" aria-label="Mostrar contraseña" aria-pressed="false" class="absolute inset-y-0 right-0 grid w-12 place-items-center text-slate-600 hover:text-[var(--ft-primary)] focus:outline-none focus:ring-2 focus:ring-blue-600" hidden><span data-password-eye-visible><x-ui.icon name="eye" /></span><span data-password-eye-hidden hidden><x-ui.icon name="eye-off" /></span></button>
                    </div>
                    @error('password_confirmation')<p id="password-confirmation-error" class="ft-field-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </fieldset>
    @else
        <p class="ft-alert ft-alert-info">
            La contraseña no cambia al guardar este formulario. Utilice la acción separada de restablecimiento cuando sea necesario.
        </p>
    @endif
</div>

<div class="ft-form-actions">
    <a href="{{ $cancelUrl }}" class="ft-button-secondary">Cancelar</a>
    <button type="submit" class="ft-button-primary">{{ $submitLabel }}</button>
</div>
