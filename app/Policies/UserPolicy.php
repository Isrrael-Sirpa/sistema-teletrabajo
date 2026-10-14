<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\DelegatedAuthorizationService;

class UserPolicy
{
    public function __construct(private readonly DelegatedAuthorizationService $delegations) {}

    public function viewAny(User $actor): bool
    {
        return ($actor->can(PermissionName::USERS_VIEW->value)
            && $this->hasManagementScope($actor))
            || $this->delegations->hasAny($actor, PermissionName::USERS_VIEW);
    }

    public function view(User $actor, User $target): bool
    {
        if ((! $actor->can(PermissionName::USERS_VIEW->value) || ! $this->hasManagementScope($actor))
            && ! $this->delegations->can($actor, PermissionName::USERS_VIEW, (int) $target->organizational_unit_id)) {
            return false;
        }

        if ($actor->hasInstitutionalGlobalScope()) {
            return true;
        }

        return ($actor->hasRole(RoleName::CAREER_HEAD->value)
            && $actor->organizational_unit_id !== null
            && $actor->organizational_unit_id === $target->organizational_unit_id)
            || $this->delegations->can($actor, PermissionName::USERS_VIEW, (int) $target->organizational_unit_id);
    }

    public function create(User $actor): bool
    {
        return ($actor->can(PermissionName::USERS_CREATE->value)
            && ($actor->hasInstitutionalGlobalScope() || (
                $actor->hasRole(RoleName::CAREER_HEAD->value)
                && $actor->organizational_unit_id !== null
            ))) || $this->delegations->hasAny($actor, PermissionName::USERS_CREATE);
    }

    public function update(User $actor, User $target): bool
    {
        return ($actor->can(PermissionName::USERS_UPDATE->value)
            || $this->delegations->can($actor, PermissionName::USERS_UPDATE, (int) $target->organizational_unit_id))
            && $this->canManage($actor, $target, PermissionName::USERS_UPDATE);
    }

    public function activate(User $actor, User $target): bool
    {
        return ($actor->can(PermissionName::USERS_ACTIVATE->value)
            || $this->delegations->can($actor, PermissionName::USERS_ACTIVATE, (int) $target->organizational_unit_id))
            && $target->status->value === 'inactive'
            && $this->canManage($actor, $target, PermissionName::USERS_ACTIVATE);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return ($actor->can(PermissionName::USERS_DEACTIVATE->value)
            || $this->delegations->can($actor, PermissionName::USERS_DEACTIVATE, (int) $target->organizational_unit_id))
            && ! $actor->is($target)
            && $target->status->value === 'active'
            && $this->canManage($actor, $target, PermissionName::USERS_DEACTIVATE);
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return ($actor->can(PermissionName::USERS_RESET_PASSWORD->value)
            || $this->delegations->can($actor, PermissionName::USERS_RESET_PASSWORD, (int) $target->organizational_unit_id))
            && $this->canManage($actor, $target, PermissionName::USERS_RESET_PASSWORD);
    }

    private function canManage(User $actor, User $target, PermissionName $permission): bool
    {
        if ($actor->hasRole(RoleName::ADMINISTRATOR->value)) {
            return true;
        }

        if ($actor->hasRole(RoleName::CO_ADMINISTRATOR->value)) {
            return ! $target->hasRole(RoleName::ADMINISTRATOR->value);
        }

        return ($actor->hasRole(RoleName::CAREER_HEAD->value)
            && $actor->organizational_unit_id !== null
            && $actor->organizational_unit_id === $target->organizational_unit_id
            && $target->roleName()?->isSecondary() === true)
            || ($target->organizational_unit_id !== null
                && $target->roleName()?->isSecondary() === true
                && $this->delegations->can($actor, $permission, $target->organizational_unit_id));
    }

    private function hasManagementScope(User $actor): bool
    {
        return $actor->hasInstitutionalGlobalScope()
            || ($actor->hasRole(RoleName::CAREER_HEAD->value) && $actor->organizational_unit_id !== null);
    }
}
