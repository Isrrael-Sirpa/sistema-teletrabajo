<?php

namespace App\Http\Requests;

use App\Enums\AcademicTitle;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && Gate::allows('update', $target);
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
            'academic_title' => ['nullable', Rule::enum(AcademicTitle::class)],
            'email' => ['required', 'string', 'email', 'max:255'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'role' => ['required', Rule::enum(RoleName::class), Rule::in($allowedRoles)],
            'organizational_unit_id' => ['nullable', 'integer', Rule::exists(OrganizationalUnit::class, 'id')],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var User $target */
            $target = $this->route('user');

            if (! $validator->errors()->has('email') && $this->emailExists($target)) {
                $validator->errors()->add('email', 'Ya existe otro usuario con este correo electrónico.');
            }

            $role = RoleName::tryFrom($this->string('role')->toString());

            if ($role?->requiresUnit() === true && ! $this->filled('organizational_unit_id')) {
                $validator->errors()->add('organizational_unit_id', 'Seleccione una unidad para este rol.');
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

    private function emailExists(User $except): bool
    {
        return User::query()
            ->whereKeyNot($except->getKey())
            ->whereRaw('LOWER(email) = ?', [$this->string('email')->toString()])
            ->exists();
    }
}
