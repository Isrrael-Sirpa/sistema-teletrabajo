<?php

namespace App\Notifications;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Models\Document;
use App\Notifications\Concerns\BuildsDocumentNotificationData;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentAnnulledNotification extends Notification
{
    use BuildsDocumentNotificationData, Queueable;

    public function __construct(
        private readonly Document $document,
        private readonly string $occurredAt,
    ) {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        if ($this->document->confidentiality === DocumentConfidentiality::RESTRICTED) {
            return $this->documentData(
                $this->document,
                'Documento anulado',
                'Un documento confidencial fue anulado.',
                DocumentEventType::DOCUMENT_ANNULLED->value,
                $this->occurredAt,
                includeCode: false,
            );
        }

        return $this->documentData(
            $this->document,
            'Documento anulado',
            "El documento {$this->document->code} fue anulado.",
            DocumentEventType::DOCUMENT_ANNULLED->value,
            $this->occurredAt,
        );
    }
}
