<?php

namespace App\Http\Requests;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\UserManagementService;
use App\Support\InstitutionalPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', User::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $allowedRoles = collect(app(UserManagementService::class)->allowedRolesFor($this->user()))
            ->map(fn (RoleName $role): string => $role->value)
            ->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', InstitutionalPassword::rule()],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'role' => ['required', Rule::enum(RoleName::class), Rule::in($allowedRoles)],
            'organizational_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('email') && $this->emailExists()) {
                $validator->errors()->add('email', 'Ya existe un usuario con este correo electrónico.');
            }

            $role = RoleName::tryFrom($this->string('role')->toString());

            if ($role?->requiresCareer() === true && ! $this->filled('organizational_unit_id')) {
                $validator->errors()->add('organizational_unit_id', 'Seleccione una carrera para este rol.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ingrese el nombre completo del usuario.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'email.required' => 'Ingrese el correo electrónico del usuario.',
            'email.email' => 'Ingrese un correo electrónico válido.',
            'email.max' => 'El correo electrónico no puede superar los 255 caracteres.',
            'password.required' => 'Ingrese una contraseña inicial.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.letters' => 'La contraseña debe contener letras.',
            'password.mixed' => 'La contraseña debe contener mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe contener números.',
            'password.symbols' => 'La contraseña debe contener al menos un símbolo.',
            'status.required' => 'Seleccione el estado del usuario.',
            'status.enum' => 'El estado seleccionado no es válido.',
            'role.required' => 'Seleccione un rol.',
            'role.enum' => 'El rol seleccionado no es válido.',
            'role.in' => 'No está autorizado para asignar el rol seleccionado.',
            'organizational_unit_id.exists' => 'La unidad seleccionada no existe.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'organizational_unit_id' => $this->filled('organizational_unit_id')
                ? $this->input('organizational_unit_id')
                : null,
        ]);
    }

    protected function emailExists(?User $except = null): bool
    {
        $query = User::query()->whereRaw('LOWER(email) = ?', [$this->string('email')->toString()]);

        if ($except) {
            $query->whereKeyNot($except->getKey());
        }

        return $query->exists();
    }
}
