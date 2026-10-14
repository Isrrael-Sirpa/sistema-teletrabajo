<?php

namespace App\Services;

use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DocumentWorkQueryService
{
    public function __construct(private readonly DelegatedAuthorizationService $delegations) {}

    /** @return Builder<Document> */
    public function visibleTo(User $user): Builder
    {
        return Document::query()->visibleTo($user);
    }

    /** @param Builder<Document> $query */
    public function apply(Builder $query, User $user, string $work): bool
    {
        return match ($work) {
            'pending_receive' => $this->recipientWork($query, $user, [DocumentRecipientStatus::SENT]),
            'in_review' => $this->recipientWork($query, $user, [DocumentRecipientStatus::IN_REVIEW]),
            'decision' => $this->recipientWork(
                $query,
                $user,
                [DocumentRecipientStatus::RECEIVED, DocumentRecipientStatus::IN_REVIEW],
                true,
            ),
            'collaboration' => $this->recipientWork(
                $query,
                $user,
                [DocumentRecipientStatus::RECEIVED, DocumentRecipientStatus::IN_REVIEW],
            ),
            'assigned' => $this->assignedTo($query, $user),
            'returned_correction' => $this->returnedToOrigin($query, $user),
            'my_drafts' => $this->ownDrafts($query, $user),
            'authorized' => true,
            default => false,
        };
    }

    public function label(string $work): ?string
    {
        return match ($work) {
            'pending_receive' => 'Pendientes de recibir',
            'in_review' => 'En revisión',
            'decision' => 'Requieren decisión',
            'collaboration' => 'Trabajo para colaborar',
            'assigned' => 'Asignados a mí',
            'returned_correction' => 'Devueltos para corregir',
            'my_drafts' => 'Mis borradores',
            'authorized' => 'Documentos autorizados',
            default => null,
        };
    }

    /** @param Builder<Document> $query */
    public function attention(Builder $query, User $user): void
    {
        $role = $user->roleName();

        if ($role?->isGlobal()) {
            $query->whereIn('general_status', DocumentStatus::deadlineActionableValues());

            return;
        }

        if ($role === RoleName::EXTRA) {
            return;
        }

        $query->where('general_status', '!=', DocumentStatus::ANNULLED->value)
            ->where(function (Builder $attention) use ($user, $role): void {
                $statuses = $role === RoleName::SECRETARY_ASSISTANT
                    ? [DocumentRecipientStatus::RECEIVED, DocumentRecipientStatus::IN_REVIEW]
                    : [DocumentRecipientStatus::SENT, DocumentRecipientStatus::RECEIVED, DocumentRecipientStatus::IN_REVIEW];

                $attention->whereHas('recipients', function (Builder $recipients) use ($user, $statuses): void {
                    $recipients->whereIn('status', array_column($statuses, 'value'));
                    $this->targetedTo($recipients, $user);
                });

                if (in_array($role, [RoleName::CAREER_HEAD, RoleName::SECRETARY], true)) {
                    $attention->orWhere(function (Builder $returned) use ($user, $role): void {
                        $returned
                            ->where('general_status', DocumentStatus::RETURNED->value)
                            ->where('origin_unit_id', $user->organizational_unit_id);

                        if ($role === RoleName::SECRETARY
                            && ! $this->delegations->can($user, PermissionName::DOCUMENTS_VERSION, (int) $user->organizational_unit_id)) {
                            $returned->where('created_by', $user->getKey());
                        }
                    });
                }
            });
    }

    /** @param Builder<Document> $query */
    private function recipientWork(
        Builder $query,
        User $user,
        array $statuses,
        bool $requiresResponse = false,
    ): bool {
        $query->where('general_status', '!=', DocumentStatus::ANNULLED->value)
            ->whereHas('recipients', function (Builder $recipients) use ($user, $statuses, $requiresResponse): void {
                $recipients->whereIn('status', array_map(
                    fn (DocumentRecipientStatus $status): string => $status->value,
                    $statuses,
                ));

                if ($requiresResponse) {
                    $recipients->where('requires_response', true);
                }

                $this->targetedTo($recipients, $user);
            });

        return true;
    }

    /** @param Builder<Document> $query */
    private function assignedTo(Builder $query, User $user): bool
    {
        $query->where('general_status', '!=', DocumentStatus::ANNULLED->value)
            ->whereHas('recipients', fn (Builder $recipients) => $recipients
                ->where('status', DocumentRecipientStatus::IN_REVIEW->value)
                ->where('current_handler_id', $user->getKey()));

        return true;
    }

    /** @param Builder<Document> $query */
    private function returnedToOrigin(Builder $query, User $user): bool
    {
        $query->where('general_status', DocumentStatus::RETURNED->value);

        if (! $user->hasInstitutionalGlobalScope()) {
            $query->where('origin_unit_id', $user->organizational_unit_id);

            if ($user->hasRole(RoleName::SECRETARY->value)
                && ! $this->delegations->can($user, PermissionName::DOCUMENTS_VERSION, (int) $user->organizational_unit_id)) {
                $query->where('created_by', $user->getKey());
            }
        }

        return true;
    }

    /** @param Builder<Document> $query */
    private function ownDrafts(Builder $query, User $user): bool
    {
        $query
            ->where('general_status', DocumentStatus::DRAFT->value)
            ->where('created_by', $user->getKey());

        return true;
    }

    /** @param Builder<DocumentRecipient> $recipients */
    private function targetedTo(Builder $recipients, User $user): void
    {
        if ($user->hasInstitutionalGlobalScope()) {
            return;
        }

        $recipients->where(function (Builder $target) use ($user): void {
            $target->where('user_id', $user->getKey());

            if ($user->hasAnyRole([
                RoleName::CAREER_HEAD->value,
                RoleName::SECRETARY->value,
                RoleName::SECRETARY_ASSISTANT->value,
            ])) {
                $target->orWhere(function (Builder $unit) use ($user): void {
                    $unit->whereNull('user_id')
                        ->where('unit_id', $user->organizational_unit_id);
                });
            }
        });
    }
}
