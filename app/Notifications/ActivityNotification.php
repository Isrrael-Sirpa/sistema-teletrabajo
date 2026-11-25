<?php

namespace App\Notifications;

use App\Models\WorkActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly WorkActivity $activity,
        private readonly string $title,
        private readonly string $message,
        private readonly string $eventType,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'activity_public_id' => $this->activity->public_id,
            'event_type' => $this->eventType,
            'occurred_at' => now()->toIso8601String(),
            'route' => [
                'name' => 'activities.show',
                'parameters' => ['activity' => $this->activity->public_id],
            ],
        ];
    }
}
