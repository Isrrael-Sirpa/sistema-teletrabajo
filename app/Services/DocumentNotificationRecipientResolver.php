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
            ->filter(fn (DocumentRecipient $recipient): bool => $recipient->user?->status === UserStatus::ACTIVE
                && $recipient->user->organizational_unit_id === $recipient->unit_id)
            ->pluck('user');
        $unitIds = $recipients
            ->filter(fn (DocumentRecipient $recipient): bool => $recipient->user_id === null || $recipient->requires_response)
            ->pluck('unit_id')
            ->unique()
            ->values();
        $unitUsers = $unitIds->isEmpty()
            ? collect()
            : User::query()
                ->where('status', UserStatus::ACTIVE->value)
                ->whereIn('organizational_unit_id', $unitIds)
                ->whereHas('roles', fn ($roles) => $roles->whereIn('name', [
                    RoleName::ADMINISTRATOR->value,
                    RoleName::CO_ADMINISTRATOR->value,
                    RoleName::CAREER_HEAD->value,
                    RoleName::KARDEX_HEAD->value,
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
                        RoleName::KARDEX_HEAD->value,
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

    /** @return Collection<int, User> */
    public function forAuthorizationResponsibles(Document $document, int $actorId): Collection
    {
        return User::query()
            ->documentResponsibles()
            ->where('organizational_unit_id', $document->origin_unit_id)
            ->whereKeyNot($actorId)
            ->get();
    }

    /** @return Collection<int, User> */
    public function forAuthorizationRequester(Document $document, int $actorId): Collection
    {
        if ($document->authorization_requested_by === null) {
            return collect();
        }

        return User::query()
            ->whereKey($document->authorization_requested_by)
            ->where('status', UserStatus::ACTIVE->value)
            ->where('organizational_unit_id', $document->origin_unit_id)
            ->whereKeyNot($actorId)
            ->get();
    }
}
