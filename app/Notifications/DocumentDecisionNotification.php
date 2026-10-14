<?php

namespace App\Notifications;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Models\Document;
use App\Notifications\Concerns\BuildsDocumentNotificationData;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentDecisionNotification extends Notification
{
    use BuildsDocumentNotificationData, Queueable;

    public function __construct(
        private readonly Document $document,
        private readonly DocumentEventType $eventType,
        private readonly string $occurredAt,
        private readonly ?string $reason = null,
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
                'Actualización de documento confidencial',
                'Un documento confidencial recibió una actualización de estado.',
                $this->eventType->value,
                $this->occurredAt,
                includeCode: false,
            );
        }

        $identifier = $this->document->code ?? 'El documento';
        [$title, $message] = match ($this->eventType) {
            DocumentEventType::DOCUMENT_RECEIVED => [
                'Documento recibido',
                "{$identifier} fue recibido por el destino.",
            ],
            DocumentEventType::REVIEW_STARTED => [
                'Revisión iniciada',
                "Se inició la revisión de {$identifier}.",
            ],
            DocumentEventType::DOCUMENT_APPROVED => [
                'Documento aprobado',
                "{$identifier} fue aprobado.",
            ],
            DocumentEventType::DOCUMENT_RETURNED => [
                'Documento devuelto',
                "{$identifier} fue devuelto. Motivo: {$this->reason}",
            ],
            DocumentEventType::DOCUMENT_REJECTED => [
                'Documento rechazado',
                "{$identifier} fue rechazado. Motivo: {$this->reason}",
            ],
            default => [
                'Actualización documental',
                "{$identifier} recibió una actualización.",
            ],
        };

        return $this->documentData(
            $this->document,
            $title,
            $message,
            $this->eventType->value,
            $this->occurredAt,
            $this->reason,
        );
    }
}
