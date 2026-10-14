<?php

namespace App\Services;

use App\Enums\DocumentEventType;
use App\Models\Document;
use App\Models\DocumentEvent;
use App\Models\DocumentRecipient;
use App\Models\User;
use DateTimeInterface;

class DocumentEventService
{
    /** @param array<string, mixed> $metadata */
    public function record(
        Document $document,
        DocumentEventType $type,
        ?User $actor = null,
        ?DocumentRecipient $recipient = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $reason = null,
        array $metadata = [],
        ?DateTimeInterface $occurredAt = null,
    ): DocumentEvent {
        return DocumentEvent::query()->create([
            'document_id' => $document->getKey(),
            'document_recipient_id' => $recipient?->getKey(),
            'actor_id' => $actor?->getKey(),
            'event_type' => $type,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : $metadata,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    /** @return array<string, mixed> */
    public function recipientMetadata(DocumentRecipient $recipient): array
    {
        $recipient->loadMissing(['unit', 'user']);

        return [
            'recipient_type' => $recipient->user_id === null ? 'unit' : 'person',
            'recipient_label' => $recipient->user?->name ?? $recipient->unit->name,
            'unit_name' => $recipient->unit->name,
            'requires_response' => $recipient->requires_response,
        ];
    }
}
