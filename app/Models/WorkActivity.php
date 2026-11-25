<?php

namespace App\Models;

use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organizational_unit_id', 'document_id', 'created_by', 'assigned_by', 'title',
    'activity_type', 'instructions', 'expected_result', 'priority', 'status', 'due_at',
    'result_summary', 'result_document_id', 'submitted_at', 'reviewed_by', 'reviewed_at',
    'review_note', 'completed_at', 'cancelled_at', 'cancellation_reason', 'lock_version',
])]
class WorkActivity extends Model
{
    use HasUuids;

    protected $table = 'actividades_unidad';

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function resultDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'result_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'responsables_actividades', 'activity_id', 'user_id')
            ->withPivot('responsibility')->withTimestamps();
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ActivityUpdate::class, 'activity_id')->oldest();
    }

    public function files(): HasMany
    {
        return $this->hasMany(ActivityFile::class, 'activity_id')->latest();
    }

    public function primaryAssignee(): ?User
    {
        return $this->assignees->first(fn (User $user): bool => $user->pivot->responsibility === 'primary');
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignees()->whereKey($user->getKey())->exists();
    }

    /** @param Builder<WorkActivity> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasInstitutionalGlobalScope()) {
            return $query;
        }

        if ($scopeUnitIds = $user->activityScopeUnitIds()) {
            return $query->whereIn('organizational_unit_id', $scopeUnitIds);
        }

        return $query->whereHas('assignees', fn (Builder $assignees) => $assignees->whereKey($user->getKey()));
    }

    protected function casts(): array
    {
        return [
            'activity_type' => ActivityType::class,
            'priority' => ActivityPriority::class,
            'status' => ActivityStatus::class,
            'due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }
}
