<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\OutgoingAuthorizationStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Services\DelegatedAuthorizationService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'document_type_id', 'origin_unit_id', 'created_by', 'sender_user_id',
    'subject', 'description', 'general_status', 'priority', 'confidentiality',
    'due_at', 'code', 'correlative_number', 'correlative_year', 'correlative_reserved_by', 'correlative_reserved_at', 'lock_version',
    'authorization_status', 'authorization_requested_by', 'authorization_requested_at',
    'authorized_by', 'authorized_at', 'authorization_note',
])]
class Document extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'documentos';

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function originUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'origin_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function annuller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    public function authorizationRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorization_requested_by');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function correlativeReserver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'correlative_reserved_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(DocumentRecipient::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DocumentEvent::class);
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(DocumentAccessGrant::class);
    }

    public function workActivities(): HasMany
    {
        return $this->hasMany(WorkActivity::class);
    }

    /** @param Builder<Document> $query */
    public function scopeVisibleTo(
        Builder $query,
        User $user,
        bool $includeGrants = true,
        bool $includeTasks = true,
    ): Builder {
        if ($user->hasInstitutionalGlobalScope()) {
            return $query;
        }

        if ($user->hasRole(RoleName::STUDENT->value)) {
            return $query->where('origin_unit_id', $user->organizational_unit_id)
                ->where(function (Builder $own) use ($user): void {
                    $own->where('created_by', $user->getKey())
                        ->orWhere('sender_user_id', $user->getKey());
                });
        }

        $delegatedUnits = app(DelegatedAuthorizationService::class)
            ->unitIdsFor($user, PermissionName::DOCUMENTS_VIEW);

        $isResponsible = $user->isResponsibleForUnit((int) $user->organizational_unit_id);

        return $query->where(function (Builder $visibility) use ($delegatedUnits, $includeGrants, $includeTasks, $isResponsible, $user): void {
            $visibility->whereRaw('1 = 0');

            if ($isResponsible) {
                $visibility->orWhere('origin_unit_id', $user->organizational_unit_id);
            }

            if ($user->hasRole(RoleName::SECRETARY->value)) {
                $visibility->orWhere(function (Builder $origin) use ($user): void {
                    $origin->where('origin_unit_id', $user->organizational_unit_id)
                        ->where('created_by', $user->getKey());
                });
            }

            if ($delegatedUnits->isNotEmpty()) {
                $visibility->orWhereIn('origin_unit_id', $delegatedUnits)
                    ->orWhereHas('recipients', fn (Builder $recipients) => $recipients
                        ->whereIn('unit_id', $delegatedUnits)
                        ->where('status', '!=', DocumentRecipientStatus::PREPARED->value));
            }

            if (! $user->hasRole(RoleName::EXTRA->value)) {
                $visibility->orWhereHas('recipients', function (Builder $recipients) use ($user, $isResponsible): void {
                    $recipients
                        ->where('status', '!=', DocumentRecipientStatus::PREPARED->value)
                        ->where(function (Builder $target) use ($user, $isResponsible): void {
                            $target->where('user_id', $user->getKey());

                            if ($isResponsible) {
                                $target->orWhere(fn (Builder $unit) => $unit
                                    ->where('unit_id', $user->organizational_unit_id)
                                    ->where(fn (Builder $recipients) => $recipients
                                        ->whereNull('user_id')
                                        ->orWhere('requires_response', true)));
                            }
                        });
                });
            }

            if ($includeTasks && DocumentReviewTask::canBeAssignedTo($user)) {
                $visibility->orWhereHas('recipients.reviewTasks', fn (Builder $tasks) => $tasks->assignedTo($user));
            }

            if ($includeTasks && $user->can(PermissionName::ACTIVITIES_VIEW->value)) {
                $visibility->orWhereHas('workActivities', fn (Builder $activities) => $activities
                    ->whereNotIn('status', [ActivityStatus::COMPLETED->value, ActivityStatus::CANCELLED->value])
                    ->whereHas('assignees', fn (Builder $assignees) => $assignees->whereKey($user->getKey())));
            }

            if ($includeGrants) {
                $visibility->orWhereHas('accessGrants', function (Builder $grants) use ($user): void {
                    $grants
                        ->effective()
                        ->where('user_id', $user->getKey())
                        ->whereIn('permission', ['view', 'download']);
                });
            }
        });
    }

    public function reviewVersionIdsFor(User $user): Builder
    {
        return DocumentReviewTask::query()
            ->assignedTo($user)
            ->whereRelation('recipient', 'document_id', $this->getKey())
            ->whereNotNull('document_version_id')
            ->select('document_version_id');
    }

    /** @param Builder<Document> $query */
    public function scopePendingWithDeadline(Builder $query): Builder
    {
        return $query
            ->whereNotNull('due_at')
            ->whereIn('general_status', DocumentStatus::deadlineActionableValues());
    }

    /** @param Builder<Document> $query */
    public function scopeDueSoon(Builder $query, CarbonInterface $now, CarbonInterface $cutoff): Builder
    {
        return $query
            ->pendingWithDeadline()
            ->where('due_at', '>', $now)
            ->where('due_at', '<=', $cutoff);
    }

    /** @param Builder<Document> $query */
    public function scopeOverdue(Builder $query, CarbonInterface $now): Builder
    {
        return $query
            ->pendingWithDeadline()
            ->where('due_at', '<=', $now);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'general_status' => DocumentStatus::class,
            'authorization_status' => OutgoingAuthorizationStatus::class,
            'priority' => DocumentPriority::class,
            'confidentiality' => DocumentConfidentiality::class,
            'due_at' => 'datetime',
            'sent_at' => 'datetime',
            'correlative_reserved_at' => 'datetime',
            'correlative_number' => 'integer',
            'correlative_year' => 'integer',
            'annulled_at' => 'datetime',
            'authorization_requested_at' => 'datetime',
            'authorized_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }
}
