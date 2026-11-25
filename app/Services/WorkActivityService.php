<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Enums\AuditAction;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\WorkActivity;
use App\Notifications\ActivityNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkActivityService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data, array $assigneeIds): WorkActivity
    {
        return DB::transaction(function () use ($actor, $data, $assigneeIds): WorkActivity {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $unitId = (int) ($data['target_unit_id'] ?? 0);
            if (! $actor->canAssignActivityToUnit($unitId)) {
                throw ValidationException::withMessages(['target_unit_id' => 'No puede asignar actividades a esa unidad.']);
            }
            $unit = OrganizationalUnit::query()->active()->find($unitId);
            if (! $unit) {
                throw ValidationException::withMessages(['target_unit_id' => 'Seleccione una unidad activa.']);
            }
            $team = User::query()->lockForUpdate()->whereIn('id', $assigneeIds)
                ->where('organizational_unit_id', $unitId)->where('status', 'active')
                ->whereKeyNot($actor->getKey())->get();

            if ($team->count() !== count(array_unique($assigneeIds))) {
                throw ValidationException::withMessages(['assignees' => 'Seleccione únicamente personal activo de su unidad.']);
            }

            $primaryId = (int) $data['primary_assignee_id'];
            if (! $team->contains('id', $primaryId)) {
                throw ValidationException::withMessages(['primary_assignee_id' => 'El responsable principal debe estar entre las personas asignadas.']);
            }

            $document = $this->resolveDocument($actor, $data['document_public_id'] ?? null, $unitId);
            $activity = WorkActivity::query()->create([
                ...Arr::only($data, ['title', 'activity_type', 'instructions', 'priority', 'due_at']),
                'expected_result' => 'Resultado registrado al concluir la actividad.',
                'organizational_unit_id' => $unitId,
                'document_id' => $document?->getKey(),
                'created_by' => $actor->getKey(),
                'assigned_by' => $actor->getKey(),
                'status' => ActivityStatus::ASSIGNED,
            ]);

            $activity->assignees()->attach($team->mapWithKeys(fn (User $person): array => [
                $person->getKey() => ['responsibility' => $person->getKey() === $primaryId ? 'primary' : 'support'],
            ])->all());

            $this->audit->record($actor, AuditAction::ACTIVITY_CREATED, $activity, newValues: [
                'unit_id' => $unitId,
                'document_public_id' => $document?->public_id,
                'assignee_public_ids' => $team->pluck('public_id')->all(),
            ]);

            foreach ($team as $person) {
                $person->notify(new ActivityNotification(
                    $activity,
                    'Nueva actividad asignada',
                    $activity->title.' fue asignada para su atención.',
                    'activity_assigned',
                ));
            }

            return $activity;
        });
    }

    public function recordProgress(User $actor, WorkActivity $activity, string $detail, ?int $percent): void
    {
        DB::transaction(function () use ($actor, $activity, $detail, $percent): void {
            $activity = WorkActivity::query()->lockForUpdate()->findOrFail($activity->getKey());
            $activity->updates()->create(['user_id' => $actor->getKey(), 'detail' => $detail, 'progress_percent' => $percent]);
            if (in_array($activity->status, [ActivityStatus::ASSIGNED, ActivityStatus::OBSERVED], true)) {
                $activity->forceFill(['status' => ActivityStatus::IN_PROGRESS, 'lock_version' => $activity->lock_version + 1])->save();
            }
            $this->audit->record($actor, AuditAction::ACTIVITY_PROGRESS_RECORDED, $activity, newValues: ['progress_percent' => $percent]);
            $recipients = $activity->assignees->push($activity->assigner)->unique('id')
                ->reject(fn (User $person): bool => $person->is($actor));
            foreach ($recipients as $person) {
                $person->notify(new ActivityNotification($activity, 'Avance registrado', $actor->displayName().' registró un avance en '.$activity->title.'.', 'activity_progress'));
            }
        });
    }

    public function submit(User $actor, WorkActivity $activity, string $summary, ?Document $resultDocument): void
    {
        DB::transaction(function () use ($actor, $activity, $summary, $resultDocument): void {
            $activity = WorkActivity::query()->lockForUpdate()->findOrFail($activity->getKey());
            if ($resultDocument && ($resultDocument->origin_unit_id !== $activity->organizational_unit_id
                || $resultDocument->created_by !== $actor->getKey()
                || $resultDocument->general_status !== DocumentStatus::DRAFT)) {
                throw ValidationException::withMessages(['result_document_public_id' => 'Seleccione un borrador preparado por usted en esta unidad.']);
            }
            $activity->forceFill([
                'status' => ActivityStatus::SUBMITTED,
                'result_summary' => $summary,
                'result_document_id' => $resultDocument?->getKey(),
                'submitted_at' => now(),
                'review_note' => null,
                'lock_version' => $activity->lock_version + 1,
            ])->save();
            $activity->updates()->create([
                'user_id' => $actor->getKey(),
                'detail' => 'Resultado presentado: '.$summary,
                'progress_percent' => 100,
            ]);
            $this->audit->record($actor, AuditAction::ACTIVITY_SUBMITTED, $activity);
            $activity->assigner->notify(new ActivityNotification($activity, 'Resultado presentado', $activity->title.' está lista para revisión.', 'activity_submitted'));
        });
    }

    public function review(User $actor, WorkActivity $activity, string $decision, ?string $note): void
    {
        DB::transaction(function () use ($actor, $activity, $decision, $note): void {
            $activity = WorkActivity::query()->lockForUpdate()->findOrFail($activity->getKey());
            $completed = $decision === 'complete';
            $activity->forceFill([
                'status' => $completed ? ActivityStatus::COMPLETED : ActivityStatus::OBSERVED,
                'reviewed_by' => $actor->getKey(),
                'reviewed_at' => now(),
                'review_note' => $note,
                'completed_at' => $completed ? now() : null,
                'lock_version' => $activity->lock_version + 1,
            ])->save();
            $action = $completed ? AuditAction::ACTIVITY_COMPLETED : AuditAction::ACTIVITY_OBSERVED;
            $this->audit->record($actor, $action, $activity);
            if (! $completed && $note) {
                $activity->updates()->create([
                    'user_id' => $actor->getKey(),
                    'detail' => 'Observación: '.$note,
                    'progress_percent' => null,
                ]);
            }
            foreach ($activity->assignees as $person) {
                $person->notify(new ActivityNotification(
                    $activity,
                    $completed ? 'Actividad concluida' : 'Actividad con observaciones',
                    $completed ? 'El resultado fue aceptado.' : 'Revise las observaciones y presente nuevamente el resultado.',
                    $completed ? 'activity_completed' : 'activity_observed',
                ));
            }
        });
    }

    public function cancel(User $actor, WorkActivity $activity, string $reason): void
    {
        DB::transaction(function () use ($actor, $activity, $reason): void {
            $activity = WorkActivity::query()->lockForUpdate()->findOrFail($activity->getKey());
            $activity->forceFill([
                'status' => ActivityStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'lock_version' => $activity->lock_version + 1,
            ])->save();
            $this->audit->record($actor, AuditAction::ACTIVITY_CANCELLED, $activity);
            foreach ($activity->assignees as $person) {
                $person->notify(new ActivityNotification($activity, 'Actividad cancelada', $activity->title.' fue cancelada. Revise el motivo registrado.', 'activity_cancelled'));
            }
        });
    }

    private function resolveDocument(User $actor, ?string $publicId, int $unitId): ?Document
    {
        if (! filled($publicId)) {
            return null;
        }

        $document = Document::query()->visibleTo($actor)->where('public_id', $publicId)->first();
        if (! $document || ($document->origin_unit_id !== $unitId
            && ! $document->recipients()->where('unit_id', $unitId)->exists())) {
            throw ValidationException::withMessages(['document_public_id' => 'Seleccione un documento disponible para su unidad.']);
        }

        return $document;
    }
}
