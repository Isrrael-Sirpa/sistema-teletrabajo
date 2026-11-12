<?php

namespace App\Models;

use App\Enums\ReviewRecommendation;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assignment_key', 'document_version_id', 'assigned_to', 'assigned_by', 'instructions'])]
class DocumentReviewTask extends Model
{
    use HasUuids;

    protected $table = 'tareas_revision_documentos';

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(DocumentRecipient::class, 'document_recipient_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isPending(): bool
    {
        return $this->submitted_at === null && $this->closed_at === null;
    }

    /** @param Builder<DocumentReviewTask> $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('submitted_at')->whereNull('closed_at');
    }

    /** @param Builder<DocumentReviewTask> $query */
    public function scopeForUnit(Builder $query, int $unitId): Builder
    {
        return $query->whereHas('recipient.document')->whereRelation('recipient', 'unit_id', $unitId);
    }

    public static function canBeAssignedTo(User $user): bool
    {
        return $user->status === UserStatus::ACTIVE
            && $user->roleName()?->canReviewInternally()
            && $user->organizationalUnit?->is_active
            && $user->organizationalUnit->type->responsibleRole() !== null;
    }

    /** @param Builder<DocumentReviewTask> $query */
    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->where('assigned_to', $user->getKey())
            ->whereRelation('recipient', 'unit_id', $user->organizational_unit_id)
            ->when(! self::canBeAssignedTo($user), fn (Builder $tasks) => $tasks->whereRaw('1 = 0'));
    }

    protected function casts(): array
    {
        return [
            'recommendation' => ReviewRecommendation::class,
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
