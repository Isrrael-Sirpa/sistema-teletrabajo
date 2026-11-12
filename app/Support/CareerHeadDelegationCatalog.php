<?php

namespace App\Support;

use App\Enums\PermissionName;

final class CareerHeadDelegationCatalog
{
    /** @return array<int, PermissionName> */
    public static function permissions(): array
    {
        return [
            PermissionName::UNITS_VIEW,
            PermissionName::USERS_VIEW,
            PermissionName::USERS_CREATE,
            PermissionName::USERS_UPDATE,
            PermissionName::USERS_ACTIVATE,
            PermissionName::USERS_DEACTIVATE,
            PermissionName::USERS_RESET_PASSWORD,
            PermissionName::USERS_ASSIGN_ROLES,
            PermissionName::DOCUMENT_TYPES_VIEW,
            PermissionName::DOCUMENTS_VIEW,
            PermissionName::DOCUMENTS_CREATE,
            PermissionName::DOCUMENTS_UPDATE_DRAFT,
            PermissionName::DOCUMENTS_DISCARD_DRAFT,
            PermissionName::DOCUMENTS_SEND,
            PermissionName::DOCUMENTS_ASSISTANT_REVIEW,
            PermissionName::DOCUMENTS_DOWNLOAD,
            PermissionName::DOCUMENTS_VERSION,
            PermissionName::DOCUMENTS_COMMENT,
            PermissionName::DOCUMENTS_GRANT_ACCESS,
            PermissionName::DOCUMENTS_VIEW_HISTORY,
            PermissionName::REPORTS_VIEW_UNIT,
            PermissionName::REPORTS_EXPORT,
        ];
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (PermissionName $permission): string => $permission->value, self::permissions());
    }

    public static function contains(string|PermissionName $permission): bool
    {
        $value = $permission instanceof PermissionName ? $permission->value : $permission;

        return in_array($value, self::values(), true);
    }
}
