<?php

namespace App\Notifications;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentDeadlineStatus;
use App\Models\Document;
use App\Notifications\Concerns\BuildsDocumentNotificationData;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentDeadlineNotification extends Notification
{
    use BuildsDocumentNotificationData, Queueable;

    public function __construct(
        private readonly Document $document,
        private readonly DocumentDeadlineStatus $milestone,
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
        $restricted = $this->document->confidentiality === DocumentConfidentiality::RESTRICTED;
        $eventType = $this->milestone === DocumentDeadlineStatus::DUE_SOON
            ? 'document_due_soon'
            : 'document_overdue';

        if ($restricted) {
            [$title, $message] = $this->milestone === DocumentDeadlineStatus::DUE_SOON
                ? ['Documento confidencial próximo a vencer', 'Un documento confidencial está próximo a vencer.']
                : ['Documento confidencial vencido', 'Un documento confidencial superó su fecha límite.'];
        } else {
            $identifier = $this->document->code ?? 'El documento';
            $dueDate = CarbonImmutable::instance($this->document->due_at)
                ->setTimezone((string) config('app.timezone'))
                ->format('d/m/Y');
            [$title, $message] = $this->milestone === DocumentDeadlineStatus::DUE_SOON
                ? ['Documento próximo a vencer', "{$identifier} vence el {$dueDate}."]
                : ['Documento vencido', "{$identifier} superó su fecha límite."];
        }

        return [
            ...$this->documentData(
                $this->document,
                $title,
                $message,
                $eventType,
                $this->occurredAt,
                includeCode: ! $restricted,
            ),
            'deadline_milestone' => $this->milestone->value,
            'deadline_due_at' => $this->document->due_at?->toIso8601String(),
        ];
    }
}
