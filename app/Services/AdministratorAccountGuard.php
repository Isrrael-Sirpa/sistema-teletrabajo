<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AdministratorAccountGuard
{
    public function lockRole(): Role
    {
        return Role::query()
            ->where('name', RoleName::ADMINISTRATOR->value)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function exists(): bool
    {
        return User::query()->role(RoleName::ADMINISTRATOR->value)->exists();
    }

    public function ensureAnotherActiveExists(User $excluded): void
    {
        $exists = User::query()
            ->whereKeyNot($excluded->getKey())
            ->where('status', UserStatus::ACTIVE->value)
            ->role(RoleName::ADMINISTRATOR->value)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'status' => 'Debe permanecer al menos un Administrador activo en el sistema.',
            ]);
        }
    }
}
