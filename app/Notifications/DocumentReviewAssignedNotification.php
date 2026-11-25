<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentReviewAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Document $document)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Revisión interna asignada',
            'message' => 'Tiene una revisión pendiente en '.($this->document->code ?? 'un documento recibido').'.',
            'event_type' => 'document_review_assigned',
            'occurred_at' => now()->toIso8601String(),
            'route' => ['name' => 'documents.show', 'parameters' => ['document' => $this->document->public_id]],
        ];
    }
}
