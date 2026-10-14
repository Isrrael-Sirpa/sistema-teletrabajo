<?php

namespace App\Notifications\Concerns;

use App\Models\Document;

trait BuildsDocumentNotificationData
{
    /** @return array<string, mixed> */
    private function documentData(
        Document $document,
        string $title,
        string $message,
        string $eventType,
        string $occurredAt,
        ?string $reason = null,
        bool $includeCode = true,
    ): array {
        return [
            'title' => $title,
            'message' => $message,
            'document_public_id' => $document->public_id,
            'document_code' => $includeCode ? $document->code : null,
            'event_type' => $eventType,
            'occurred_at' => $occurredAt,
            'reason' => $reason,
            'route' => [
                'name' => 'documents.show',
                'parameters' => ['document' => $document->public_id],
            ],
        ];
    }
}
