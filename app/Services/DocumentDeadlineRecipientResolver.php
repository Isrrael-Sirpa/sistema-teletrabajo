<?php

namespace App\Services;

use App\Enums\DocumentDeadlineStatus;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Document;
use App\Models\DocumentRecipient;
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

        $recipients = DocumentRecipient::query()
            ->with(['user.roles'])
            ->where('document_id', $document->getKey())
            ->where('requires_response', true)
            ->whereIn('status', [
                DocumentRecipientStatus::SENT->value,
                DocumentRecipientStatus::RECEIVED->value,
                DocumentRecipientStatus::IN_REVIEW->value,
            ])
            ->get();

        $personalUsers = $recipients->pluck('user')->filter();
        $unitIds = $recipients
            ->whereNull('user_id')
            ->pluck('unit_id')
            ->unique()
            ->values();
        $unitUsers = $unitIds->isEmpty()
            ? collect()
            : User::query()
                ->with('roles')
                ->where('status', UserStatus::ACTIVE->value)
                ->whereIn('organizational_unit_id', $unitIds)
                ->whereHas('roles', fn ($roles) => $roles->whereIn('name', [
                    RoleName::CAREER_HEAD->value,
                    RoleName::SECRETARY->value,
                ]))
                ->get();
        $handlerIds = $recipients
            ->pluck('current_handler_id')
            ->filter()
            ->unique()
            ->values();
        $handlers = $handlerIds->isEmpty()
            ? collect()
            : User::query()
                ->with('roles')
                ->where('status', UserStatus::ACTIVE->value)
                ->whereIn('id', $handlerIds)
                ->whereHas('roles', fn ($roles) => $roles->whereIn('name', [
                    RoleName::CAREER_HEAD->value,
                    RoleName::SECRETARY->value,
                    RoleName::SECRETARY_ASSISTANT->value,
                ]))
                ->get();

        $users = $personalUsers->merge($unitUsers)->merge($handlers);

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
