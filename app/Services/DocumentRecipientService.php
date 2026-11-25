<?php

namespace App\Services;

use App\Enums\DocumentEventType;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DocumentRecipientService
{
    public function __construct(
        private readonly DocumentRecipientEligibility $eligibility,
        private readonly DocumentEventService $events,
    ) {}

    /** @param array<string, mixed> $data */
    public function add(User $actor, Document $document, array $data): DocumentRecipient
    {
        Gate::forUser($actor)->authorize('manageRecipients', $document);

        try {
            return DB::transaction(function () use ($actor, $document, $data): DocumentRecipient {
                $target = $this->resolveTarget($actor, $data);

                $existing = DocumentRecipient::withTrashed()
                    ->where('document_id', $document->getKey())
                    ->where('unit_id', $target['unit_id'])
                    ->where('user_id', $target['user_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing && ! $existing->trashed()) {
                    throw ValidationException::withMessages([
                        'recipient' => 'Este destinatario ya fue agregado al borrador.',
                    ]);
                }

                $this->bumpDraftVersion($document, (int) $data['lock_version']);

                if ($existing) {
                    $existing->restore();
                    $existing->forceFill([
                        'requires_response' => (bool) $data['requires_response'],
                        'status' => DocumentRecipientStatus::PREPARED,
                        'received_by' => null,
                        'received_at' => null,
                        'current_handler_id' => null,
                        'decided_by' => null,
                        'decided_at' => null,
                        'decision_reason' => null,
                        'lock_version' => $existing->lock_version + 1,
                    ])->save();
                    $recipient = $existing->refresh();
                } else {
                    $recipient = DocumentRecipient::query()->create([
                        'document_id' => $document->getKey(),
                        'unit_id' => $target['unit_id'],
                        'user_id' => $target['user_id'],
                        'requires_response' => (bool) $data['requires_response'],
                        'status' => DocumentRecipientStatus::PREPARED,
                        'lock_version' => 0,
                    ]);
                }

                $this->events->record(
                    $document,
                    DocumentEventType::RECIPIENT_ADDED,
                    $actor,
                    $recipient,
                    metadata: [
                        ...$this->events->recipientMetadata($recipient),
                        'restored' => $existing !== null,
                    ],
                );

                return $recipient;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'recipient' => 'Este destinatario ya fue agregado al borrador.',
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Document $document, array $data): DocumentRecipient
    {
        Gate::forUser($actor)->authorize('manageRecipients', $document);

        return DB::transaction(function () use ($actor, $document, $data): DocumentRecipient {
            $recipient = $this->findPreparedRecipient($document, (int) $data['recipient_id']);
            $this->assertAllowedClassification($recipient, (bool) $data['requires_response']);
            $previous = $recipient->requires_response;
            $this->bumpDraftVersion($document, (int) $data['lock_version']);

            $recipient->update([
                'requires_response' => (bool) $data['requires_response'],
            ]);

            $recipient->refresh();

            if ($previous !== $recipient->requires_response) {
                $this->events->record(
                    $document,
                    DocumentEventType::RECIPIENT_UPDATED,
                    $actor,
                    $recipient,
                    metadata: [
                        ...$this->events->recipientMetadata($recipient),
                        'from_requires_response' => $previous,
                    ],
                );
            }

            return $recipient;
        });
    }

    /** @param array<string, mixed> $data */
    public function remove(User $actor, Document $document, array $data): void
    {
        Gate::forUser($actor)->authorize('manageRecipients', $document);

        DB::transaction(function () use ($actor, $document, $data): void {
            $recipient = $this->findPreparedRecipient($document, (int) $data['recipient_id']);
            $this->bumpDraftVersion($document, (int) $data['lock_version']);
            $this->events->record(
                $document,
                DocumentEventType::RECIPIENT_REMOVED,
                $actor,
                $recipient,
                metadata: $this->events->recipientMetadata($recipient),
            );
            $recipient->delete();
        });
    }

    /** @param array<string, mixed> $data
     * @return array{unit_id: int, user_id: int|null}
     */
    private function resolveTarget(User $actor, array $data): array
    {
        if ($data['recipient_type'] === 'unit') {
            $unit = OrganizationalUnit::query()
                ->availableAsDocumentDestination()
                ->find($data['unit_id']);

            if (! $unit) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Seleccione una carrera o Kardex activo.',
                ]);
            }

            $this->eligibility->assertAllowedUnitFor($actor, $unit);

            return ['unit_id' => $unit->getKey(), 'user_id' => null];
        }

        if ($actor->hasRole(\App\Enums\RoleName::STUDENT->value)) {
            throw ValidationException::withMessages([
                'recipient_type' => 'Como estudiante, solo puede enviar documentos a su carrera o a Kardex.',
            ]);
        }

        $user = User::query()->find($data['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'Seleccione una persona válida.',
            ]);
        }

        $this->eligibility->assertPersonalCandidate($user, (bool) $data['requires_response']);

        return [
            'unit_id' => $user->organizational_unit_id,
            'user_id' => $user->getKey(),
        ];
    }

    private function assertAllowedClassification(DocumentRecipient $recipient, bool $requiresResponse): void
    {
        if ($recipient->user_id === null) {
            return;
        }

        $user = User::query()->find($recipient->user_id);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'El destinatario personal ya no está disponible.',
            ]);
        }

        $this->eligibility->assertPersonalCandidate($user, $requiresResponse);
    }

    private function findPreparedRecipient(Document $document, int $recipientId): DocumentRecipient
    {
        $recipient = DocumentRecipient::query()
            ->where('document_id', $document->getKey())
            ->where('status', DocumentRecipientStatus::PREPARED->value)
            ->find($recipientId);

        if (! $recipient) {
            throw ValidationException::withMessages([
                'recipient' => 'El destinatario no pertenece a este borrador o ya no puede modificarse.',
            ]);
        }

        return $recipient;
    }

    private function bumpDraftVersion(Document $document, int $expectedVersion): void
    {
        $updated = Document::query()
            ->whereKey($document->getKey())
            ->where('general_status', DocumentStatus::DRAFT->value)
            ->where('lock_version', $expectedVersion)
            ->update([
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw ValidationException::withMessages([
                'lock_version' => 'Los destinatarios cambiaron en otra sesión. Recargue la página antes de continuar.',
            ]);
        }

        $document->refresh();
    }
}
