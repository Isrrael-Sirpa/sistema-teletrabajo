<?php

namespace App\Services;

use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\OutgoingAuthorizationStatus;
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
            'unit_pending' => $this->unitPending($query, $user),
            'my_unit' => $this->unitDocuments($query, $user),
            'pending_receive' => $this->recipientWork($query, $user, [DocumentRecipientStatus::SENT]),
            'in_review' => $this->recipientWork($query, $user, [DocumentRecipientStatus::IN_REVIEW]),
            'decision' => $this->recipientWork(
                $query,
                $user,
                [DocumentRecipientStatus::RECEIVED, DocumentRecipientStatus::IN_REVIEW],
                true,
            ),
            'collaboration', 'assigned' => $this->assignedTo($query, $user),
            'returned_correction' => $this->returnedToOrigin($query, $user),
            'my_drafts' => $this->ownDrafts($query, $user),
            'authorized' => true,
            default => false,
        };
    }

    public function label(string $work): ?string
    {
        return match ($work) {
            'unit_pending' => 'Documentos pendientes de mi unidad',
            'my_unit' => 'Documentos de mi unidad',
            'pending_receive' => 'Pendientes de recibir',
            'in_review' => 'En revisión',
            'decision' => 'Requieren decisión',
            'collaboration', 'assigned' => 'Mis revisiones pendientes',
            'returned_correction' => 'Devueltos para corregir',
            'my_drafts' => 'Mis borradores',
            'authorized' => 'Documentos autorizados',
            default => null,
        };
    }

    private function unitPending(Builder $query, User $user): bool
    {
        if (! $user->isResponsibleForUnit((int) $user->organizational_unit_id)) {
            $query->whereRaw('1 = 0');

            return true;
        }

        $query->where(function (Builder $documents) use ($user): void {
            $documents->where(function (Builder $received) use ($user): void {
                $received->whereNotIn('general_status', [DocumentStatus::DRAFT->value, DocumentStatus::ANNULLED->value])
                    ->whereHas('recipients', fn (Builder $recipients) => $this->pendingUnitRecipients($recipients, $user));
            })->orWhere(function (Builder $authorization) use ($user): void {
                $authorization->where('general_status', DocumentStatus::DRAFT->value)
                    ->where('authorization_status', OutgoingAuthorizationStatus::PENDING->value)
                    ->where('origin_unit_id', $user->organizational_unit_id);
            });
        });

        return true;
    }

    /** @param Builder<Document> $query */
    private function unitDocuments(Builder $query, User $user): bool
    {
        $unitId = (int) $user->organizational_unit_id;
        if ($unitId < 1) {
            $query->whereRaw('1 = 0');

            return true;
        }

        $query->where(function (Builder $documents) use ($unitId): void {
            $documents->where('origin_unit_id', $unitId)
                ->orWhereHas('recipients', fn (Builder $recipients) => $recipients->where('unit_id', $unitId));
        });

        return true;
    }

    /** @param Builder<DocumentRecipient> $recipients */
    public function pendingUnitRecipients(Builder $recipients, User $user): void
    {
        $this->targetedTo($recipients, $user);
        $recipients->where(fn (Builder $pending) => $pending
            ->where('status', DocumentRecipientStatus::SENT->value)
            ->orWhere(fn (Builder $formal) => $formal->where('requires_response', true)
                ->whereIn('status', [DocumentRecipientStatus::RECEIVED->value, DocumentRecipientStatus::IN_REVIEW->value])));
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

                if ($role?->canReviewInternally()) {
                    $attention->orWhereHas('recipients.reviewTasks', fn (Builder $tasks) => $tasks->assignedTo($user)->pending());
                }

                if ($role?->isUnitResponsible() || $role === RoleName::SECRETARY) {
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

                if ($user->isResponsibleForUnit((int) $user->organizational_unit_id)) {
                    $attention->orWhere(function (Builder $authorization) use ($user): void {
                        $authorization->where('general_status', DocumentStatus::DRAFT->value)
                            ->where('authorization_status', OutgoingAuthorizationStatus::PENDING->value)
                            ->where('origin_unit_id', $user->organizational_unit_id);
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
        if ($requiresResponse && ! $user->hasInstitutionalGlobalScope()
            && ! $user->isResponsibleForUnit((int) $user->organizational_unit_id)) {
            $query->whereRaw('1 = 0');

            return true;
        }

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
            ->whereHas('recipients.reviewTasks', fn (Builder $tasks) => $tasks->assignedTo($user)->pending());

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

        $recipients->where('unit_id', $user->organizational_unit_id)
            ->where(function (Builder $destinations) use ($user): void {
                $destinations->where('user_id', $user->getKey());
                if ($user->isResponsibleForUnit((int) $user->organizational_unit_id)) {
                    $destinations->orWhereNull('user_id')->orWhere('requires_response', true);
                } elseif ($user->roleName() === RoleName::SECRETARY) {
                    $destinations->orWhere(fn (Builder $copy) => $copy
                        ->whereNull('user_id')->where('requires_response', false));
                }
            });
    }
}
