<?php

namespace App\Policies;

use App\Enums\ActivityStatus;
use App\Enums\PermissionName;
use App\Models\User;
use App\Models\WorkActivity;

class WorkActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ACTIVITIES_VIEW->value);
    }

    public function view(User $user, WorkActivity $activity): bool
    {
        return $user->can(PermissionName::ACTIVITIES_VIEW->value)
            && WorkActivity::query()->visibleTo($user)->whereKey($activity->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ACTIVITIES_CREATE->value)
            && $user->activityScopeUnitIds() !== [];
    }

    public function recordProgress(User $user, WorkActivity $activity): bool
    {
        return $user->can(PermissionName::ACTIVITIES_PROGRESS->value)
            && $activity->status->isOpen()
            && $activity->status !== ActivityStatus::SUBMITTED
            && $activity->isAssignedTo($user);
    }

    public function attachFile(User $user, WorkActivity $activity): bool
    {
        return $activity->status->isOpen()
            && ($activity->isAssignedTo($user)
                || $activity->assigned_by === $user->getKey()
                || $user->canAssignActivityToUnit((int) $activity->organizational_unit_id));
    }

    public function deleteOwnFile(User $user, WorkActivity $activity): bool
    {
        return $activity->status->isOpen() && $this->view($user, $activity);
    }

    public function submit(User $user, WorkActivity $activity): bool
    {
        return $user->can(PermissionName::ACTIVITIES_SUBMIT->value)
            && in_array($activity->status, [ActivityStatus::ASSIGNED, ActivityStatus::IN_PROGRESS, ActivityStatus::OBSERVED], true)
            && $activity->assignees()->whereKey($user)->wherePivot('responsibility', 'primary')->exists();
    }

    public function review(User $user, WorkActivity $activity): bool
    {
        return $user->can(PermissionName::ACTIVITIES_REVIEW->value)
            && $activity->assigned_by === $user->getKey()
            && in_array($activity->status, [ActivityStatus::SUBMITTED, ActivityStatus::OBSERVED], true);
    }

    public function cancel(User $user, WorkActivity $activity): bool
    {
        return $user->can(PermissionName::ACTIVITIES_CANCEL->value)
            && $activity->assigned_by === $user->getKey()
            && $activity->status->isOpen();
    }
}
