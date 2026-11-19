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

    /** @return array<string, array<int, PermissionName>> */
    public static function groups(): array
    {
        return [
            'Gestión de usuarios' => [
                PermissionName::USERS_VIEW,
                PermissionName::USERS_CREATE,
                PermissionName::USERS_UPDATE,
                PermissionName::USERS_ACTIVATE,
                PermissionName::USERS_DEACTIVATE,
                PermissionName::USERS_RESET_PASSWORD,
                PermissionName::USERS_ASSIGN_ROLES,
            ],
            'Documentos' => [
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
            ],
            'Reportes' => [
                PermissionName::REPORTS_VIEW_UNIT,
                PermissionName::REPORTS_EXPORT,
            ],
            'Estructura institucional' => [
                PermissionName::UNITS_VIEW,
                PermissionName::DOCUMENT_TYPES_VIEW,
            ],
        ];
    }

    public static function groupFor(string $permission): string
    {
        foreach (self::groups() as $group => $permissions) {
            if (in_array($permission, array_map(fn (PermissionName $item): string => $item->value, $permissions), true)) {
                return $group;
            }
        }

        return 'Otras funciones';
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
