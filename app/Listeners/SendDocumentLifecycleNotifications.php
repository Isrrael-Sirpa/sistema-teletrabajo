<?php

namespace App\Listeners;

use App\Enums\DocumentEventType;
use App\Events\DocumentNotificationRequested;
use App\Models\Document;
use App\Notifications\CorrectionSentNotification;
use App\Notifications\DocumentAnnulledNotification;
use App\Notifications\DocumentDecisionNotification;
use App\Notifications\DocumentSentNotification;
use App\Services\DocumentNotificationRecipientResolver;
use Illuminate\Support\Facades\Notification;

class SendDocumentLifecycleNotifications
{
    public function __construct(
        private readonly DocumentNotificationRecipientResolver $recipients,
    ) {}

    public function handle(DocumentNotificationRequested $event): void
    {
        if (! in_array($event->eventType, [
            DocumentEventType::DOCUMENT_SENT,
            DocumentEventType::DOCUMENT_RECEIVED,
            DocumentEventType::REVIEW_STARTED,
            DocumentEventType::DOCUMENT_APPROVED,
            DocumentEventType::DOCUMENT_RETURNED,
            DocumentEventType::DOCUMENT_REJECTED,
            DocumentEventType::DOCUMENT_ANNULLED,
            DocumentEventType::CORRECTION_SENT,
        ], true)) {
            return;
        }

        $document = Document::query()->find($event->documentId);

        if (! $document) {
            return;
        }

        $occurredAt = $event->occurredAt ?? now()->toIso8601String();

        if ($event->eventType === DocumentEventType::DOCUMENT_ANNULLED) {
            $users = $this->recipients->forAnnulment($document, $event->actorId);
        } elseif (in_array($event->eventType, [
            DocumentEventType::DOCUMENT_SENT,
            DocumentEventType::CORRECTION_SENT,
        ], true)) {
            $users = $this->recipients->forDocumentRecipients(
                $document,
                $event->recipientIds,
                $event->actorId,
            );
        } else {
            $users = $this->recipients->forOrigin(
                $document,
                $event->actorId,
                in_array($event->eventType, [
                    DocumentEventType::DOCUMENT_APPROVED,
                    DocumentEventType::DOCUMENT_RETURNED,
                    DocumentEventType::DOCUMENT_REJECTED,
                ], true),
            );
        }

        if ($users->isEmpty()) {
            return;
        }

        $notification = match ($event->eventType) {
            DocumentEventType::DOCUMENT_SENT => new DocumentSentNotification($document, $occurredAt),
            DocumentEventType::DOCUMENT_ANNULLED => new DocumentAnnulledNotification($document, $occurredAt),
            DocumentEventType::CORRECTION_SENT => new CorrectionSentNotification(
                $document,
                (int) $event->version,
                $occurredAt,
            ),
            default => new DocumentDecisionNotification(
                $document,
                $event->eventType,
                $occurredAt,
                $event->reason,
            ),
        };

        Notification::send($users, $notification);
    }
}
