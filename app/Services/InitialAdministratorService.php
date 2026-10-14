<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Support\InstitutionalPassword;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InitialAdministratorService
{
    public function __construct(
        private readonly AdministratorAccountGuard $administrators,
        private readonly AuditLogService $audit,
    ) {}

    public function bootstrapIsAvailable(): bool
    {
        if (! Role::query()->where('name', RoleName::ADMINISTRATOR->value)->exists()) {
            throw new DomainException('El catálogo RBAC no está cargado. Ejecute primero los seeders institucionales.');
        }

        return ! $this->administrators->exists();
    }

    /**
     * @param  array{name: string, email: string, password: string, password_confirmation: string}  $data
     */
    public function create(array $data): User
    {
        $data['name'] = Str::squish($data['name']);
        $data['email'] = Str::lower(trim($data['email']));

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', InstitutionalPassword::rule()],
        ], [
            'name.required' => 'Ingrese el nombre completo del Administrador.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'email.required' => 'Ingrese el correo electrónico del Administrador.',
            'email.email' => 'Ingrese un correo electrónico válido.',
            'email.max' => 'El correo electrónico no puede superar los 255 caracteres.',
            'password.required' => 'Ingrese una contraseña inicial.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.letters' => 'La contraseña debe contener letras.',
            'password.mixed' => 'La contraseña debe contener mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe contener números.',
            'password.symbols' => 'La contraseña debe contener al menos un símbolo.',
        ]);

        $validator->after(function ($validator) use ($data): void {
            if (User::query()->whereRaw('LOWER(email) = ?', [$data['email']])->exists()) {
                $validator->errors()->add('email', 'Ya existe un usuario con este correo electrónico.');
            }
        });

        /** @var array{name: string, email: string, password: string} $validated */
        $validated = $validator->validate();

        return DB::transaction(function () use ($validated): User {
            $this->administrators->lockRole();

            if ($this->administrators->exists()) {
                throw new DomainException('El bootstrap ya fue completado: existe una cuenta Administrador.');
            }

            if (User::query()->whereRaw('LOWER(email) = ?', [$validated['email']])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Ya existe un usuario con este correo electrónico.',
                ]);
            }

            $administrator = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => UserStatus::ACTIVE,
                'organizational_unit_id' => null,
            ]);
            $administrator->syncRoles([RoleName::ADMINISTRATOR->value]);

            $this->audit->record(null, AuditAction::USER_CREATED, $administrator, newValues: [
                'public_id' => $administrator->public_id,
                'name' => $administrator->name,
                'email' => $administrator->email,
                'status' => $administrator->status,
                'role' => RoleName::ADMINISTRATOR,
                'organizational_unit_id' => null,
                'source' => 'initial_bootstrap',
            ]);

            return $administrator->load('roles');
        });
    }
}
