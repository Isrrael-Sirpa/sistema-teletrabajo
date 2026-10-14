<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\DocumentType;
use App\Models\User;

class DocumentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::DOCUMENT_TYPES_VIEW->value);
    }

    public function view(User $user, DocumentType $documentType): bool
    {
        return $user->can(PermissionName::DOCUMENT_TYPES_VIEW->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::DOCUMENT_TYPES_MANAGE->value);
    }

    public function update(User $user, DocumentType $documentType): bool
    {
        return $user->can(PermissionName::DOCUMENT_TYPES_MANAGE->value);
    }

    public function activate(User $user, DocumentType $documentType): bool
    {
        return ! $documentType->is_active
            && $user->can(PermissionName::DOCUMENT_TYPES_MANAGE->value);
    }

    public function deactivate(User $user, DocumentType $documentType): bool
    {
        return $documentType->is_active
            && $user->can(PermissionName::DOCUMENT_TYPES_MANAGE->value);
    }

    public function delete(User $user, DocumentType $documentType): bool
    {
        return false;
    }
}
