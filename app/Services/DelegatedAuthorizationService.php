<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\TemporaryDelegation;
use App\Models\User;
use App\Support\CareerHeadDelegationCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DelegatedAuthorizationService
{
    public function can(User $user, string|PermissionName $permission, int $unitId, bool $lock = false): bool
    {
        $value = $permission instanceof PermissionName ? $permission->value : $permission;

        if (! CareerHeadDelegationCatalog::contains($value)
            || $user->status !== UserStatus::ACTIVE
            || ! $user->hasRole(RoleName::SECRETARY->value)
            || $user->organizational_unit_id !== $unitId) {
            return false;
        }

        $query = TemporaryDelegation::query()
            ->effective()
            ->where('delegate_user_id', $user->getKey())
            ->where('organizational_unit_id', $unitId)
            ->whereHas('permissions', fn (Builder $permissions) => $permissions
                ->where('name', $value)
                ->where('guard_name', 'web'));

        if ($lock) {
            $query->sharedLock();
        }

        return $query->exists();
    }

    public function hasAny(User $user, string|PermissionName $permission): bool
    {
        return $this->unitIdsFor($user, $permission)->isNotEmpty();
    }

    /** @return Collection<int, int> */
    public function unitIdsFor(User $user, string|PermissionName $permission): Collection
    {
        $value = $permission instanceof PermissionName ? $permission->value : $permission;

        if (! CareerHeadDelegationCatalog::contains($value)
            || $user->status !== UserStatus::ACTIVE
            || ! $user->hasRole(RoleName::SECRETARY->value)) {
            return collect();
        }

        if ($user->organizational_unit_id === null) {
            return collect();
        }

        return TemporaryDelegation::query()
            ->effective()
            ->where('delegate_user_id', $user->getKey())
            ->where('organizational_unit_id', $user->organizational_unit_id)
            ->whereHas('permissions', fn (Builder $permissions) => $permissions
                ->where('name', $value)
                ->where('guard_name', 'web'))
            ->pluck('organizational_unit_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /** @return Collection<int, TemporaryDelegation> */
    public function activeFor(User $user): Collection
    {
        if ($user->status !== UserStatus::ACTIVE
            || ! $user->hasRole(RoleName::SECRETARY->value)
            || $user->organizational_unit_id === null) {
            return collect();
        }

        return TemporaryDelegation::query()
            ->effective()
            ->where('delegate_user_id', $user->getKey())
            ->where('organizational_unit_id', $user->organizational_unit_id)
            ->with(['organizationalUnit', 'delegator', 'permissions'])
            ->orderBy('ends_at')
            ->get();
    }
}
