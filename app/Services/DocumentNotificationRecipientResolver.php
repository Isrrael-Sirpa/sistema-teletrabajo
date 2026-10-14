<?php

namespace App\Services;

use App\Enums\DocumentRecipientStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\User;
use Illuminate\Support\Collection;

class DocumentNotificationRecipientResolver
{
    /**
     * @param  array<int, int>  $recipientIds
     * @return Collection<int, User>
     */
    public function forDocumentRecipients(Document $document, array $recipientIds, int $actorId): Collection
    {
        $recipients = DocumentRecipient::query()
            ->with('user')
            ->where('document_id', $document->getKey())
            ->whereKey($recipientIds)
            ->get();
        $personalUsers = $recipients
            ->pluck('user')
            ->filter(fn (?User $user): bool => $user?->status === UserStatus::ACTIVE);
        $unitIds = $recipients
            ->whereNull('user_id')
            ->pluck('unit_id')
            ->unique()
            ->values();
        $unitUsers = $unitIds->isEmpty()
            ? collect()
            : User::query()
                ->where('status', UserStatus::ACTIVE->value)
                ->whereIn('organizational_unit_id', $unitIds)
                ->whereHas('roles', fn ($roles) => $roles->whereIn('name', [
                    RoleName::CAREER_HEAD->value,
                    RoleName::SECRETARY->value,
                ]))
                ->get();

        return $personalUsers
            ->merge($unitUsers)
            ->reject(fn (User $user): bool => $user->getKey() === $actorId)
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }

    /** @return Collection<int, User> */
    public function forOrigin(Document $document, int $actorId, bool $includeOriginLeaders): Collection
    {
        $directIds = collect([$document->created_by, $document->sender_user_id])
            ->filter()
            ->unique()
            ->values();
        $users = User::query()
            ->where('status', UserStatus::ACTIVE->value)
            ->whereIn('id', $directIds)
            ->get();

        if ($includeOriginLeaders) {
            $users = $users->merge(
                User::query()
                    ->where('status', UserStatus::ACTIVE->value)
                    ->where('organizational_unit_id', $document->origin_unit_id)
                    ->whereHas('roles', fn ($roles) => $roles->whereIn('name', [
                        RoleName::CAREER_HEAD->value,
                        RoleName::SECRETARY->value,
                    ]))
                    ->get(),
            );
        }

        return $users
            ->reject(fn (User $user): bool => $user->getKey() === $actorId)
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }

    /** @return Collection<int, User> */
    public function forAnnulment(Document $document, int $actorId): Collection
    {
        $recipientIds = $document->recipients()
            ->where('status', '!=', DocumentRecipientStatus::PREPARED->value)
            ->pluck('id')
            ->all();

        return $this->forOrigin($document, $actorId, true)
            ->merge($this->forDocumentRecipients($document, $recipientIds, $actorId))
            ->unique(fn (User $user): int => $user->getKey())
            ->values();
    }
}
