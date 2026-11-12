<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DocumentEventType;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentTransition;
use App\Enums\OrganizationalUnitType;
use App\Events\DocumentNotificationRequested;
use App\Models\Document;
use App\Models\DocumentCounter;
use App\Models\DocumentRecipient;
use App\Models\DocumentReviewTask;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentWorkflowService
{
    public function __construct(
        private readonly DocumentRecipientEligibility $recipientEligibility,
        private readonly DocumentEventService $events,
        private readonly DocumentFileService $files,
        private readonly AuditLogService $audit,
    ) {}

    public function send(User $actor, Document $document, int $expectedVersion): Document
    {
        Gate::forUser($actor)->authorize('send', $document);

        return DB::transaction(function () use ($actor, $document, $expectedVersion): Document {
            $locked = Document::query()
                ->with(['documentType', 'originUnit.parent'])
                ->lockForUpdate()
                ->findOrFail($document->getKey());

            Gate::forUser($actor)->authorize('send', $locked);
            $this->assertDocumentVersion($locked, $expectedVersion);
            $this->validateForSending($locked);

            $recipients = DocumentRecipient::query()
                ->where('document_id', $locked->getKey())
                ->lockForUpdate()
                ->get();

            if ($recipients->isEmpty()) {
                throw ValidationException::withMessages([
                    'recipients' => 'Agregue al menos un destinatario antes de enviar el documento.',
                ]);
            }

            if ($recipients->contains(fn (DocumentRecipient $recipient): bool => $recipient->status !== DocumentRecipientStatus::PREPARED)) {
                throw ValidationException::withMessages([
                    'recipients' => 'Los destinatarios cambiaron o el documento ya fue enviado.',
                ]);
            }

            $recipients->each(
                fn (DocumentRecipient $recipient) => $this->recipientEligibility->assertPreparedRecipient($recipient),
            );

            $sentAt = now();
            $number = $this->nextNumber($locked->origin_unit_id, $sentAt->year);
            $code = $this->formatCode($locked, $sentAt->year, $number);

            DocumentRecipient::query()
                ->whereKey($recipients->modelKeys())
                ->update([
                    'status' => DocumentRecipientStatus::SENT->value,
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => $sentAt,
                ]);

            $locked->forceFill([
                'code' => $code,
                'sender_user_id' => $actor->getKey(),
                'sent_at' => $sentAt,
                'general_status' => DocumentStatus::SENT,
                'lock_version' => $locked->lock_version + 1,
                'updated_at' => $sentAt,
            ])->save();

            $this->events->record(
                $locked,
                DocumentEventType::DOCUMENT_SENT,
                $actor,
                fromStatus: DocumentStatus::DRAFT->value,
                toStatus: DocumentStatus::SENT->value,
                metadata: [
                    'code' => $code,
                    'sender' => $actor->name,
                    'origin_unit' => $locked->originUnit->name,
                    'recipient_count' => $recipients->count(),
                ],
                occurredAt: $sentAt,
            );

            $this->audit->record($actor, AuditAction::DOCUMENT_SENT, $locked, newValues: [
                'document_public_id' => $locked->public_id,
                'code' => $code,
                'general_status' => DocumentStatus::SENT,
                'origin_unit_id' => $locked->origin_unit_id,
                'recipient_count' => $recipients->count(),
                'confidentiality' => $locked->confidentiality,
            ]);

            DocumentNotificationRequested::dispatch(
                $locked->getKey(),
                $actor->getKey(),
                DocumentEventType::DOCUMENT_SENT,
                $recipients->modelKeys(),
                occurredAt: $sentAt->toIso8601String(),
            );

            return $locked->refresh();
        });
    }

    public function annul(User $actor, Document $document, int $expectedVersion, string $reason): Document
    {
        Gate::forUser($actor)->authorize('annul', $document);

        return DB::transaction(function () use ($actor, $document, $expectedVersion, $reason): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->getKey());

            Gate::forUser($actor)->authorize('annul', $locked);
            $this->assertDocumentVersion($locked, $expectedVersion);

            $cleanReason = trim($reason);
            $maximum = max(1, min(10_000, (int) config('documents.workflow.annulment_reason_max_length', 2_000)));
            if ($cleanReason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'Debe indicar el motivo de la anulación.',
                ]);
            }
            if (mb_strlen($cleanReason) > $maximum) {
                throw ValidationException::withMessages([
                    'reason' => 'El motivo de la anulación supera la longitud máxima permitida.',
                ]);
            }

            $fromStatus = $locked->general_status;
            $annulledAt = now();
            $locked->forceFill([
                'general_status' => DocumentStatus::ANNULLED,
                'annulled_by' => $actor->getKey(),
                'annulled_at' => $annulledAt,
                'annulment_reason' => $cleanReason,
                'lock_version' => $locked->lock_version + 1,
                'updated_at' => $annulledAt,
            ])->save();

            DocumentReviewTask::query()
                ->whereRelation('recipient', 'document_id', $locked->getKey())
                ->pending()
                ->update(['closed_at' => $annulledAt]);

            $this->events->record(
                $locked,
                DocumentEventType::DOCUMENT_ANNULLED,
                $actor,
                fromStatus: $fromStatus->value,
                toStatus: DocumentStatus::ANNULLED->value,
                reason: $cleanReason,
                occurredAt: $annulledAt,
            );

            $this->audit->record(
                $actor,
                AuditAction::DOCUMENT_ANNULLED,
                $locked,
                oldValues: [
                    'general_status' => $fromStatus,
                ],
                newValues: [
                    'document_public_id' => $locked->public_id,
                    'general_status' => DocumentStatus::ANNULLED,
                    'annulled_by' => $actor->public_id,
                    'annulled_at' => $annulledAt,
                    'annulment_reason' => $cleanReason,
                    'confidentiality' => $locked->confidentiality,
                ],
            );

            DocumentNotificationRequested::dispatch(
                $locked->getKey(),
                $actor->getKey(),
                DocumentEventType::DOCUMENT_ANNULLED,
                reason: $cleanReason,
                occurredAt: $annulledAt->toIso8601String(),
            );

            return $locked->refresh();
        });
    }

    public function sendCorrection(User $actor, Document $document, int $expectedVersion): Document
    {
        Gate::forUser($actor)->authorize('sendCorrection', $document);

        return DB::transaction(function () use ($actor, $document, $expectedVersion): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->getKey());

            Gate::forUser($actor)->authorize('sendCorrection', $locked);
            $this->assertDocumentVersion($locked, $expectedVersion);

            $version = $this->files->pendingCorrectionVersion($locked);

            if (! $version) {
                throw ValidationException::withMessages([
                    'document' => 'No existe una nueva versión preparada para enviar.',
                ]);
            }

            $returnedRecipients = DocumentRecipient::query()
                ->with(['unit', 'user'])
                ->where('document_id', $locked->getKey())
                ->where('requires_response', true)
                ->where('status', DocumentRecipientStatus::RETURNED->value)
                ->lockForUpdate()
                ->get();

            if ($returnedRecipients->isEmpty()) {
                throw ValidationException::withMessages([
                    'document' => 'No existe un destinatario formal devuelto que pueda recibir la corrección.',
                ]);
            }

            $now = now();
            $recipientLabels = [];

            foreach ($returnedRecipients as $recipient) {
                $recipientLabels[] = $recipient->user?->name ?? $recipient->unit->name;
                $recipient->forceFill([
                    'status' => DocumentRecipientStatus::SENT,
                    'received_by' => null,
                    'received_at' => null,
                    'current_handler_id' => null,
                    'decided_by' => null,
                    'decided_at' => null,
                    'decision_reason' => null,
                    'lock_version' => $recipient->lock_version + 1,
                    'updated_at' => $now,
                ])->save();
            }

            $fromStatus = $locked->general_status;
            $newGeneralStatus = $this->aggregateStatus($locked);
            $locked->forceFill([
                'general_status' => $newGeneralStatus,
                'lock_version' => $locked->lock_version + 1,
                'updated_at' => $now,
            ])->save();

            $this->events->record(
                $locked,
                DocumentEventType::CORRECTION_SENT,
                $actor,
                fromStatus: $fromStatus->value,
                toStatus: $newGeneralStatus->value,
                metadata: [
                    'version' => $version->version_number,
                    'file_count' => $version->files()->count(),
                    'recipient_count' => $returnedRecipients->count(),
                    'recipients' => $recipientLabels,
                ],
                occurredAt: $now,
            );

            DocumentNotificationRequested::dispatch(
                $locked->getKey(),
                $actor->getKey(),
                DocumentEventType::CORRECTION_SENT,
                $returnedRecipients->modelKeys(),
                version: $version->version_number,
                occurredAt: $now->toIso8601String(),
            );

            return $locked->refresh();
        });
    }

    public function transition(
        User $actor,
        Document $document,
        DocumentRecipient $recipient,
        DocumentTransition $transition,
        int $expectedDocumentVersion,
        int $expectedRecipientVersion,
        ?string $reason = null,
    ): DocumentRecipient {
        return DB::transaction(function () use (
            $actor,
            $document,
            $recipient,
            $transition,
            $expectedDocumentVersion,
            $expectedRecipientVersion,
            $reason,
        ): DocumentRecipient {
            $lockedDocument = Document::query()->lockForUpdate()->findOrFail($document->getKey());
            $lockedRecipient = DocumentRecipient::query()
                ->where('document_id', $lockedDocument->getKey())
                ->lockForUpdate()
                ->find($recipient->getKey());

            if (! $lockedRecipient) {
                throw ValidationException::withMessages([
                    'recipient' => 'El destinatario no pertenece a este documento.',
                ]);
            }

            $this->assertDocumentVersion($lockedDocument, $expectedDocumentVersion);
            $this->assertRecipientVersion($lockedRecipient, $expectedRecipientVersion);
            $actor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            Gate::forUser($actor)->authorize(
                $transition->policyAbility(),
                [$lockedDocument, $lockedRecipient],
            );

            $cleanReason = filled($reason) ? trim((string) $reason) : null;
            $this->validateReason($transition, $cleanReason);
            $now = now();
            $fromRecipientStatus = $lockedRecipient->status;
            $fromGeneralStatus = $lockedDocument->general_status;

            match ($transition) {
                DocumentTransition::RECEIVE => $lockedRecipient->forceFill([
                    'status' => DocumentRecipientStatus::RECEIVED,
                    'received_by' => $actor->getKey(),
                    'received_at' => $now,
                ]),
                DocumentTransition::START_REVIEW => $lockedRecipient->forceFill([
                    'status' => DocumentRecipientStatus::IN_REVIEW,
                    'current_handler_id' => $actor->getKey(),
                ]),
                DocumentTransition::APPROVE => $lockedRecipient->forceFill([
                    'status' => DocumentRecipientStatus::APPROVED,
                    'current_handler_id' => $actor->getKey(),
                    'decided_by' => $actor->getKey(),
                    'decided_at' => $now,
                    'decision_reason' => $cleanReason,
                ]),
                DocumentTransition::RETURN => $lockedRecipient->forceFill([
                    'status' => DocumentRecipientStatus::RETURNED,
                    'current_handler_id' => $actor->getKey(),
                    'decided_by' => $actor->getKey(),
                    'decided_at' => $now,
                    'decision_reason' => $cleanReason,
                ]),
                DocumentTransition::REJECT => $lockedRecipient->forceFill([
                    'status' => DocumentRecipientStatus::REJECTED,
                    'current_handler_id' => $actor->getKey(),
                    'decided_by' => $actor->getKey(),
                    'decided_at' => $now,
                    'decision_reason' => $cleanReason,
                ]),
            };

            $lockedRecipient->lock_version++;
            $lockedRecipient->save();

            if (in_array($transition, [DocumentTransition::APPROVE, DocumentTransition::RETURN, DocumentTransition::REJECT], true)) {
                $lockedRecipient->reviewTasks()
                    ->pending()
                    ->update(['closed_at' => $now]);
            }

            $newGeneralStatus = $this->aggregateStatus($lockedDocument);
            $lockedDocument->forceFill([
                'general_status' => $newGeneralStatus,
                'lock_version' => $lockedDocument->lock_version + 1,
                'updated_at' => $now,
            ])->save();

            $this->events->record(
                $lockedDocument,
                $this->eventTypeFor($transition),
                $actor,
                $lockedRecipient,
                $fromRecipientStatus->value,
                $lockedRecipient->status->value,
                $cleanReason,
                [
                    ...$this->events->recipientMetadata($lockedRecipient),
                    'from_general_status' => $fromGeneralStatus->value,
                    'to_general_status' => $newGeneralStatus->value,
                ],
                $now,
            );

            DocumentNotificationRequested::dispatch(
                $lockedDocument->getKey(),
                $actor->getKey(),
                $this->eventTypeFor($transition),
                [$lockedRecipient->getKey()],
                $cleanReason,
                occurredAt: $now->toIso8601String(),
            );

            return $lockedRecipient->refresh();
        });
    }

    private function eventTypeFor(DocumentTransition $transition): DocumentEventType
    {
        return match ($transition) {
            DocumentTransition::RECEIVE => DocumentEventType::DOCUMENT_RECEIVED,
            DocumentTransition::START_REVIEW => DocumentEventType::REVIEW_STARTED,
            DocumentTransition::APPROVE => DocumentEventType::DOCUMENT_APPROVED,
            DocumentTransition::RETURN => DocumentEventType::DOCUMENT_RETURNED,
            DocumentTransition::REJECT => DocumentEventType::DOCUMENT_REJECTED,
        };
    }

    private function validateForSending(Document $document): void
    {
        if ($document->general_status !== DocumentStatus::DRAFT
            || $document->code !== null
            || $document->sent_at !== null) {
            throw ValidationException::withMessages([
                'document' => 'El documento ya no es un borrador disponible para envío.',
            ]);
        }

        if (! $document->documentType->is_active) {
            throw ValidationException::withMessages([
                'document_type_id' => 'El tipo documental ya no está activo.',
            ]);
        }

        if (! $document->originUnit->is_active || blank(trim($document->subject))) {
            throw ValidationException::withMessages([
                'document' => 'El documento no tiene un origen activo o un asunto válido.',
            ]);
        }
    }

    private function nextNumber(int $originUnitId, int $year): int
    {
        DocumentCounter::query()->insertOrIgnore([
            'origin_unit_id' => $originUnitId,
            'year' => $year,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DocumentCounter::query()
            ->where('origin_unit_id', $originUnitId)
            ->where('year', $year)
            ->lockForUpdate()
            ->sole();

        $counter->last_number++;
        $counter->save();

        return $counter->last_number;
    }

    private function formatCode(Document $document, int $year, int $number): string
    {
        $origin = $document->originUnit;
        $faculty = $origin->type === OrganizationalUnitType::FACULTY
            ? $origin
            : $origin->parent;

        if (! $faculty || $faculty->type !== OrganizationalUnitType::FACULTY || ! $faculty->is_active) {
            throw ValidationException::withMessages([
                'origin_unit_id' => 'La unidad de origen no pertenece a una Facultad activa.',
            ]);
        }

        $segments = [Str::upper($faculty->acronym)];

        if ($origin->getKey() !== $faculty->getKey()) {
            $segments[] = Str::upper($origin->acronym);
        }

        $digits = max(1, min(12, (int) config('documents.workflow.code_digits', 6)));
        $segments[] = (string) $year;
        $segments[] = str_pad((string) $number, $digits, '0', STR_PAD_LEFT);

        return implode('-', $segments);
    }

    private function assertDocumentVersion(Document $document, int $expectedVersion): void
    {
        if ($document->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages([
                'document_lock_version' => 'El documento cambió en otra sesión. Recargue la página antes de continuar.',
            ]);
        }
    }

    private function assertRecipientVersion(DocumentRecipient $recipient, int $expectedVersion): void
    {
        if ($recipient->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages([
                'recipient_lock_version' => 'Este destinatario cambió en otra sesión. Recargue la página antes de continuar.',
            ]);
        }
    }

    private function validateReason(DocumentTransition $transition, ?string $reason): void
    {
        if (in_array($transition, [DocumentTransition::RETURN, DocumentTransition::REJECT], true)
            && blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Debe indicar un motivo para devolver o rechazar el documento.',
            ]);
        }

        if ($reason !== null && mb_strlen($reason) > (int) config('documents.workflow.reason_max_length')) {
            throw ValidationException::withMessages([
                'reason' => 'El motivo supera la longitud máxima permitida.',
            ]);
        }
    }

    private function aggregateStatus(Document $document): DocumentStatus
    {
        /** @var Collection<int, DocumentRecipientStatus> $required */
        $required = DocumentRecipient::query()
            ->where('document_id', $document->getKey())
            ->where('requires_response', true)
            ->pluck('status');

        if ($required->contains(DocumentRecipientStatus::REJECTED)) {
            return DocumentStatus::REJECTED;
        }

        if ($required->contains(DocumentRecipientStatus::RETURNED)) {
            return DocumentStatus::RETURNED;
        }

        if ($required->isNotEmpty()
            && $required->every(fn (DocumentRecipientStatus $status): bool => $status === DocumentRecipientStatus::APPROVED)) {
            return DocumentStatus::APPROVED;
        }

        if ($required->contains(DocumentRecipientStatus::IN_REVIEW)) {
            return DocumentStatus::IN_REVIEW;
        }

        if ($required->contains(DocumentRecipientStatus::RECEIVED)) {
            return DocumentStatus::RECEIVED;
        }

        if ($required->isEmpty()
            && DocumentRecipient::query()
                ->where('document_id', $document->getKey())
                ->where('status', DocumentRecipientStatus::RECEIVED->value)
                ->exists()) {
            return DocumentStatus::RECEIVED;
        }

        return DocumentStatus::SENT;
    }
}
