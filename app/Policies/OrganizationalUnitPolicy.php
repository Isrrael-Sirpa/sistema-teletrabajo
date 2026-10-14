<?php

namespace App\Policies;

use App\Enums\OrganizationalUnitType;
use App\Enums\PermissionName;
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
        return $unit->type === OrganizationalUnitType::CAREER
            && $user->can(PermissionName::UNITS_UPDATE->value);
    }

    public function activate(User $user, OrganizationalUnit $unit): bool
    {
        return $unit->type === OrganizationalUnitType::CAREER
            && ! $unit->is_active
            && $user->can(PermissionName::UNITS_ACTIVATE->value);
    }

    public function deactivate(User $user, OrganizationalUnit $unit): bool
    {
        return $unit->type === OrganizationalUnitType::CAREER
            && $unit->is_active
            && $user->can(PermissionName::UNITS_DEACTIVATE->value);
    }

    public function delete(User $user, OrganizationalUnit $unit): bool
    {
        return false;
    }
}
