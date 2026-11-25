<?php

namespace App\Policies;

use App\Enums\OrganizationalUnitType;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\OrganizationalUnit;
use App\Models\User;

class OrganizationalUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::UNITS_VIEW->value);
    }

    public function view(User $user, OrganizationalUnit $unit): bool
    {
        return $user->can(PermissionName::UNITS_VIEW->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::UNITS_CREATE->value);
    }

    public function update(User $user, OrganizationalUnit $unit): bool
    {
        return $this->canManage($user, $unit, PermissionName::UNITS_UPDATE);
    }

    public function activate(User $user, OrganizationalUnit $unit): bool
    {
        return $this->canManage($user, $unit, PermissionName::UNITS_ACTIVATE)
            && ! $unit->is_active;
    }

    public function deactivate(User $user, OrganizationalUnit $unit): bool
    {
        return $this->canManage($user, $unit, PermissionName::UNITS_DEACTIVATE)
            && $unit->is_active;
    }

    public function delete(User $user, OrganizationalUnit $unit): bool
    {
        return false;
    }

    private function canManage(User $user, OrganizationalUnit $unit, PermissionName $permission): bool
    {
        if ($unit->type === OrganizationalUnitType::FACULTY) {
            return false;
        }

        return $user->hasRole(RoleName::ADMINISTRATOR->value)
            && $user->can($permission->value);
    }
}
