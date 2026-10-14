<?php

namespace App\Notifications;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Models\Document;
use App\Notifications\Concerns\BuildsDocumentNotificationData;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentSentNotification extends Notification
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
                'Nuevo documento confidencial',
                'Tiene un nuevo documento confidencial.',
                DocumentEventType::DOCUMENT_SENT->value,
                $this->occurredAt,
                includeCode: false,
            );
        }

        $identifier = $this->document->code ?? 'Un documento';

        return $this->documentData(
            $this->document,
            'Nuevo documento recibido',
            "{$identifier} fue enviado para su atención.",
            DocumentEventType::DOCUMENT_SENT->value,
            $this->occurredAt,
        );
    }
}
