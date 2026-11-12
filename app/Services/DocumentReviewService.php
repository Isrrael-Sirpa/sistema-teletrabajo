<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DocumentEventType;
use App\Enums\ReviewRecommendation;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentReviewTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DocumentReviewService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /** @return Collection<int, User> */
    public function team(int $unitId): Collection
    {
        return User::query()->where('organizational_unit_id', $unitId)
            ->where('status', UserStatus::ACTIVE->value)
            ->role([RoleName::SECRETARY->value, RoleName::SECRETARY_ASSISTANT->value])
            ->orderBy('name')
            ->get();
    }

    /** @param array{assigned_to: int, instructions: string, assignment_key: string, recipient_lock_version: int} $data */
    public function assign(User $actor, Document $document, DocumentRecipient $recipient, array $data): void
    {
        DB::transaction(function () use ($actor, $document, $recipient, $data): void {
            [$document, $recipient] = $this->lockDestination($document, $recipient->getKey());
            $actor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            Gate::forUser($actor)->authorize('assignReview', [$document, $recipient]);

            if ($recipient->lock_version !== (int) $data['recipient_lock_version']) {
                throw ValidationException::withMessages(['assigned_to' => 'El destino cambió. Abra el documento de nuevo antes de asignar la revisión.']);
            }

            $assignee = User::query()->lockForUpdate()->find($data['assigned_to']);
            if (! $assignee || $assignee->organizational_unit_id !== $recipient->unit_id
                || ! DocumentReviewTask::canBeAssignedTo($assignee)) {
                throw ValidationException::withMessages(['assigned_to' => 'Seleccione una persona activa del equipo de esta unidad.']);
            }

            $task = $recipient->reviewTasks()->firstOrCreate(['assignment_key' => $data['assignment_key']], [
                'document_version_id' => $this->deliveredVersionId($document, $recipient),
                'assigned_to' => $assignee->getKey(),
                'assigned_by' => $actor->getKey(),
                'instructions' => $data['instructions'],
            ]);

            if ($task->wasRecentlyCreated) {
                $this->audit->record($actor, AuditAction::REVIEW_TASK_ASSIGNED, $task, newValues: [
                    'document_public_id' => $document->public_id,
                    'unit_id' => $recipient->unit_id,
                    'assigned_to' => $assignee->public_id,
                ]);
            }
        });
    }

    public function submit(User $actor, Document $document, DocumentReviewTask $task, ReviewRecommendation $recommendation, string $message): void
    {
        DB::transaction(function () use ($actor, $document, $task, $recommendation, $message): void {
            [$document, $recipient] = $this->lockDestination($document, $task->document_recipient_id);
            $actor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $task = $recipient->reviewTasks()->lockForUpdate()->findOrFail($task->getKey());
            Gate::forUser($actor)->authorize('submitReview', [$document, $recipient, $task]);

            if (! $task->isPending()) {
                throw ValidationException::withMessages(['message' => 'Esta revisión ya fue entregada o cerrada.']);
            }

            $task->forceFill([
                'recommendation' => $recommendation,
                'message' => $message,
                'submitted_at' => now(),
            ])->save();

            $this->audit->record($actor, AuditAction::REVIEW_TASK_SUBMITTED, $task, newValues: [
                'document_public_id' => $document->public_id,
                'unit_id' => $recipient->unit_id,
            ]);
        });
    }

    /** @return array{Document, DocumentRecipient} */
    private function lockDestination(Document $document, int $recipientId): array
    {
        $document = Document::query()->lockForUpdate()->findOrFail($document->getKey());

        return [$document, $document->recipients()->lockForUpdate()->findOrFail($recipientId)];
    }

    public function deliveredVersionId(Document $document, DocumentRecipient $recipient): ?int
    {
        $lastReturnId = $document->events()
            ->where('document_recipient_id', $recipient->getKey())
            ->where('event_type', DocumentEventType::DOCUMENT_RETURNED->value)
            ->max('id');

        // Each correction goes to every destination that was returned at the time.
        $correction = $lastReturnId ? $document->events()
            ->where('event_type', DocumentEventType::CORRECTION_SENT->value)
            ->where('id', '>', $lastReturnId)
            ->oldest('id')
            ->first() : null;

        return $document->versions()
            ->where('version_number', $correction?->metadata['version'] ?? 1)
            ->value('id');
    }
}
