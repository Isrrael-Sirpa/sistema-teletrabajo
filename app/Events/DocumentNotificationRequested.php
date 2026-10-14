<?php

namespace App\Events;

use App\Enums\DocumentEventType;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentNotificationRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, int>  $recipientIds
     */
    public function __construct(
        public readonly int $documentId,
        public readonly int $actorId,
        public readonly DocumentEventType $eventType,
        public readonly array $recipientIds = [],
        public readonly ?string $reason = null,
        public readonly ?int $version = null,
        public readonly ?string $occurredAt = null,
    ) {}
}
