<?php

namespace App\Services;

use App\Enums\DocumentDeadlineStatus;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Collection;

class DocumentDeadlineRecipientResolver
{
    public function __construct(
        private readonly DocumentNotificationRecipientResolver $notificationRecipients,
    ) {}

    /** @return Collection<int, User> */
    public function resolve(Document $document, DocumentDeadlineStatus $milestone): Collection
    {
        if ($document->general_status === DocumentStatus::RETURNED) {
            return $this->eligible(
                $this->notificationRecipients->forOrigin($document, 0, true),
            );
        }

        $recipientIds = $document->recipients()
            ->where('requires_response', true)
            ->whereIn('status', [
                DocumentRecipientStatus::SENT->value,
                DocumentRecipientStatus::RECEIVED->value,
                DocumentRecipientStatus::IN_REVIEW->value,
            ])
            ->pluck('id')
            ->all();

        $users = $this->notificationRecipients->forDocumentRecipients($document, $recipientIds, 0);

        if ($milestone === DocumentDeadlineStatus::OVERDUE) {
            $users = $users->merge(
                $this->notificationRecipients->forOrigin($document, 0, false),
            );
        }

        return $this->eligible($users);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    private function eligible(Collection $users): Collection
    {
        return $users
            ->filter(fn (User $user): bool => $user->status === UserStatus::ACTIVE
                && ! $user->hasRole(RoleName::EXTRA->value))
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }
}
