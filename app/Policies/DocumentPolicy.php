<?php

namespace App\Policies;

use App\Enums\ActivityStatus;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\OutgoingAuthorizationStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Document;
use App\Models\DocumentAccessGrant;
use App\Models\DocumentFile;
use App\Models\DocumentRecipient;
use App\Models\DocumentReviewTask;
use App\Models\User;
use App\Services\DelegatedAuthorizationService;

class DocumentPolicy
{
    public function __construct(private readonly DelegatedAuthorizationService $delegations) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::DOCUMENTS_VIEW->value)
            || $this->delegations->hasAny($user, PermissionName::DOCUMENTS_VIEW);
    }

    public function view(User $user, Document $document): bool
    {
        if (! $this->hasCapability($user, PermissionName::DOCUMENTS_VIEW, $document->origin_unit_id)
            && ! $document->recipients()->whereIn(
                'unit_id',
                $this->delegations->unitIdsFor($user, PermissionName::DOCUMENTS_VIEW),
            )->exists()) {
            return false;
        }

        return Document::query()
            ->whereKey($document->getKey())
            ->visibleTo($user)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::DOCUMENTS_CREATE->value)
            || $this->delegations->hasAny($user, PermissionName::DOCUMENTS_CREATE);
    }

    public function update(User $user, Document $document): bool
    {
        if ($document->general_status !== DocumentStatus::DRAFT
            || $document->authorization_status === OutgoingAuthorizationStatus::PENDING
            || $document->authorization_status === OutgoingAuthorizationStatus::APPROVED
            || ! $this->hasCapability($user, PermissionName::DOCUMENTS_UPDATE_DRAFT, $document->origin_unit_id)) {
            return false;
        }

        if ($user->hasInstitutionalGlobalScope()) {
            return true;
        }

        return $document->created_by === $user->getKey()
            && $document->origin_unit_id === $user->organizational_unit_id;
    }

    public function discardDraft(User $user, Document $document): bool
    {
        if ($document->general_status !== DocumentStatus::DRAFT
            || ! $this->hasCapability($user, PermissionName::DOCUMENTS_DISCARD_DRAFT, $document->origin_unit_id)) {
            return false;
        }

        if ($document->created_by !== $user->getKey()) {
            return false;
        }

        return $user->hasInstitutionalGlobalScope()
            || $document->origin_unit_id === $user->organizational_unit_id;
    }

    public function manageRecipients(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    public function manageFiles(User $user, Document $document): bool
    {
        return $this->hasCapability($user, PermissionName::DOCUMENTS_VERSION, $document->origin_unit_id)
            && $this->update($user, $document);
    }

    public function downloadFile(User $user, Document $document, ?DocumentFile $file = null): bool
    {
        if (! $this->hasCapability($user, PermissionName::DOCUMENTS_DOWNLOAD, $document->origin_unit_id)
            || ! $this->view($user, $document)) {
            return false;
        }

        if (Document::query()
            ->whereKey($document->getKey())
            ->visibleTo($user, includeGrants: false, includeTasks: false)
            ->exists()) {
            return true;
        }

        return $document->accessGrants()
            ->effective()
            ->where('user_id', $user->getKey())
            ->where('permission', 'download')
            ->exists()
            || $document->reviewVersionIdsFor($user)
                ->when($file, fn ($versions) => $versions->where('document_version_id', $file->document_version_id))
                ->exists()
            || $document->workActivities()
                ->whereNotIn('status', [ActivityStatus::COMPLETED->value, ActivityStatus::CANCELLED->value])
                ->whereHas('assignees', fn ($assignees) => $assignees->whereKey($user->getKey()))
                ->exists();
    }

    public function send(User $user, Document $document): bool
    {
        if (! $this->hasCapability($user, PermissionName::DOCUMENTS_SEND, $document->origin_unit_id)
            || $document->general_status !== DocumentStatus::DRAFT) {
            return false;
        }

        if ($user->hasRole(RoleName::SECRETARY->value)) {
            return $document->created_by === $user->getKey()
                && $document->origin_unit_id === $user->organizational_unit_id
                && ($document->authorization_status === null || in_array($document->authorization_status, [
                    OutgoingAuthorizationStatus::NOT_REQUIRED,
                    OutgoingAuthorizationStatus::APPROVED,
                ], true));
        }

        return $this->update($user, $document);
    }

    public function requestAuthorization(User $user, Document $document): bool
    {
        return $document->general_status === DocumentStatus::DRAFT
            && in_array($document->authorization_status, [
                OutgoingAuthorizationStatus::NOT_REQUESTED,
                OutgoingAuthorizationStatus::RETURNED,
            ], true)
            && $user->hasRole(RoleName::SECRETARY->value)
            && $document->created_by === $user->getKey()
            && $document->origin_unit_id === $user->organizational_unit_id;
    }

    public function decideAuthorization(User $user, Document $document): bool
    {
        return $document->general_status === DocumentStatus::DRAFT
            && $document->authorization_status === OutgoingAuthorizationStatus::PENDING
            && $user->isResponsibleForUnit($document->origin_unit_id);
    }

    public function createCorrectionVersion(User $user, Document $document): bool
    {
        return $document->general_status === DocumentStatus::RETURNED
            && $this->hasCapability($user, PermissionName::DOCUMENTS_VERSION, $document->origin_unit_id)
            && $this->canManageCorrectionFromOrigin($user, $document, PermissionName::DOCUMENTS_VERSION);
    }

    public function sendCorrection(User $user, Document $document): bool
    {
        return $document->general_status === DocumentStatus::RETURNED
            && $this->hasCapability($user, PermissionName::DOCUMENTS_SEND, $document->origin_unit_id)
            && $this->canManageCorrectionFromOrigin($user, $document, PermissionName::DOCUMENTS_SEND);
    }

    public function annul(User $user, Document $document): bool
    {
        return $user->can(PermissionName::DOCUMENTS_ANNUL->value)
            && $user->hasAnyRole([
                RoleName::ADMINISTRATOR->value,
                RoleName::CO_ADMINISTRATOR->value,
            ])
            && $document->general_status !== DocumentStatus::DRAFT
            && $document->general_status !== DocumentStatus::ANNULLED
            && $document->code !== null
            && $document->sent_at !== null
            && $document->deleted_at === null;
    }

    public function manageAccessGrants(User $user, Document $document): bool
    {
        if (! $this->hasCapability($user, PermissionName::DOCUMENTS_GRANT_ACCESS, $document->origin_unit_id)
            || ! $this->view($user, $document)) {
            return false;
        }

        if ($user->hasInstitutionalGlobalScope()) {
            return true;
        }

        if ($user->isResponsibleForUnit((int) $user->organizational_unit_id)) {
            return $document->origin_unit_id === $user->organizational_unit_id
                || $document->recipients()
                    ->where('unit_id', $user->organizational_unit_id)
                    ->exists();
        }

        $delegatedUnits = $this->delegations->unitIdsFor($user, PermissionName::DOCUMENTS_GRANT_ACCESS);
        if ($delegatedUnits->contains($document->origin_unit_id)
            || $document->recipients()->whereIn('unit_id', $delegatedUnits)->exists()) {
            return true;
        }

        return $user->hasRole(RoleName::SECRETARY->value)
            && $document->origin_unit_id === $user->organizational_unit_id
            && $document->created_by === $user->getKey();
    }

    public function grantAccess(User $user, Document $document): bool
    {
        return $this->manageAccessGrants($user, $document);
    }

    public function updateAccessGrant(User $user, Document $document, DocumentAccessGrant $grant): bool
    {
        return $grant->document_id === $document->getKey()
            && $this->manageAccessGrants($user, $document);
    }

    public function revokeAccessGrant(User $user, Document $document, DocumentAccessGrant $grant): bool
    {
        return $this->updateAccessGrant($user, $document, $grant);
    }

    public function receiveRecipient(User $user, Document $document, DocumentRecipient $recipient): bool
    {
        return $document->general_status !== DocumentStatus::ANNULLED
            && $recipient->document_id === $document->getKey()
            && $recipient->status === DocumentRecipientStatus::SENT
            && $user->can(PermissionName::DOCUMENTS_RECEIVE->value)
            && (($user->isResponsibleForUnit($recipient->unit_id) && ($recipient->user_id === null || $recipient->requires_response))
                || $this->canAcknowledge($user, $recipient));
    }

    public function reviewRecipient(User $user, Document $document, DocumentRecipient $recipient): bool
    {
        return $document->general_status !== DocumentStatus::ANNULLED
            && $recipient->document_id === $document->getKey()
            && $recipient->requires_response
            && $recipient->status === DocumentRecipientStatus::RECEIVED
            && $user->can(PermissionName::DOCUMENTS_REVIEW->value)
            && $user->isResponsibleForUnit($recipient->unit_id);
    }

    public function approveRecipient(User $user, Document $document, DocumentRecipient $recipient): bool
    {
        return $this->canDecide(
            $user,
            $document,
            $recipient,
            PermissionName::DOCUMENTS_APPROVE,
            [DocumentRecipientStatus::IN_REVIEW],
        );
    }

    public function returnRecipient(User $user, Document $document, DocumentRecipient $recipient): bool
    {
        return $this->canDecide(
            $user,
            $document,
            $recipient,
            PermissionName::DOCUMENTS_RETURN,
            [DocumentRecipientStatus::RECEIVED, DocumentRecipientStatus::IN_REVIEW],
        );
    }

    public function rejectRecipient(User $user, Document $document, DocumentRecipient $recipient): bool
    {
        return $this->canDecide(
            $user,
            $document,
            $recipient,
            PermissionName::DOCUMENTS_REJECT,
            [DocumentRecipientStatus::RECEIVED, DocumentRecipientStatus::IN_REVIEW],
        );
    }

    /** @param array<int, DocumentRecipientStatus> $allowedStatuses */
    private function canDecide(
        User $user,
        Document $document,
        DocumentRecipient $recipient,
        PermissionName $permission,
        array $allowedStatuses,
    ): bool {
        return $document->general_status !== DocumentStatus::ANNULLED
            && $recipient->document_id === $document->getKey()
            && $recipient->requires_response
            && in_array($recipient->status, $allowedStatuses, true)
            && $user->can($permission->value)
            && $user->isResponsibleForUnit($recipient->unit_id);
    }

    private function canAcknowledge(User $user, DocumentRecipient $recipient): bool
    {
        return $user->status === UserStatus::ACTIVE
            && $recipient->unit_id === $user->organizational_unit_id
            && ($user->roleName() === RoleName::SECRETARY
                || $user->isResponsibleForUnit($recipient->unit_id))
            && ($recipient->user_id === $user->getKey()
                || ($recipient->user_id === null && ! $recipient->requires_response));
    }

    public function assignReview(User $user, Document $document, DocumentRecipient $recipient): bool
    {
        return $this->hasOpenReview($document, $recipient)
            && $user->isResponsibleForUnit($recipient->unit_id);
    }

    public function submitReview(User $user, Document $document, DocumentRecipient $recipient, DocumentReviewTask $task): bool
    {
        return $this->hasOpenReview($document, $recipient)
            && $task->document_recipient_id === $recipient->getKey()
            && $task->assigned_to === $user->getKey()
            && $recipient->unit_id === $user->organizational_unit_id
            && DocumentReviewTask::canBeAssignedTo($user);
    }

    private function hasOpenReview(Document $document, DocumentRecipient $recipient): bool
    {
        return $document->general_status !== DocumentStatus::ANNULLED
            && $recipient->document_id === $document->getKey()
            && $recipient->requires_response
            && in_array($recipient->status, [DocumentRecipientStatus::RECEIVED, DocumentRecipientStatus::IN_REVIEW], true);
    }

    private function canManageCorrectionFromOrigin(User $user, Document $document, PermissionName $permission): bool
    {
        if ($user->hasInstitutionalGlobalScope()) {
            return true;
        }

        return ($document->origin_unit_id === $user->organizational_unit_id
            && ($user->isResponsibleForUnit($document->origin_unit_id)
                || $user->hasRole(RoleName::SECRETARY->value)))
            || $this->delegations->can($user, $permission, $document->origin_unit_id);
    }

    private function hasCapability(User $user, PermissionName $permission, int $unitId): bool
    {
        return $user->can($permission->value)
            || $this->delegations->can($user, $permission, $unitId);
    }
}
