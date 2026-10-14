<?php

namespace App\Notifications;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Models\Document;
use App\Notifications\Concerns\BuildsDocumentNotificationData;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CorrectionSentNotification extends Notification
{
    use BuildsDocumentNotificationData, Queueable;

    public function __construct(
        private readonly Document $document,
        private readonly int $version,
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
                'Corrección confidencial disponible',
                'Tiene disponible una nueva versión de un documento confidencial.',
                DocumentEventType::CORRECTION_SENT->value,
                $this->occurredAt,
                includeCode: false,
            );
        }

        $identifier = $this->document->code ?? 'el documento';

        return $this->documentData(
            $this->document,
            'Corrección recibida',
            "Se envió la versión {$this->version} corregida de {$identifier}.",
            DocumentEventType::CORRECTION_SENT->value,
            $this->occurredAt,
        );
    }
}
