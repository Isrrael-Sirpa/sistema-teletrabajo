<?php

namespace App\Support;

use App\Enums\PermissionName;
use App\Enums\RoleName;

final class RbacCatalog
{
    public const GUARD = 'web';

    /**
     * @return array<string, array{display_name: string}>
     */
    public static function permissions(): array
    {
        return collect(PermissionName::cases())
            ->mapWithKeys(fn (PermissionName $permission): array => [
                $permission->value => ['display_name' => $permission->label()],
            ])
            ->all();
    }

    /**
     * @return array<string, array{display_name: string, description: string}>
     */
    public static function roles(): array
    {
        return collect(RoleName::cases())
            ->mapWithKeys(fn (RoleName $role): array => [
                $role->value => [
                    'display_name' => $role->label(),
                    'description' => $role->description(),
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rolePermissions(): array
    {
        $allPermissions = array_column(PermissionName::cases(), 'value');

        $roles = [
            RoleName::ADMINISTRATOR->value => $allPermissions,
            RoleName::CO_ADMINISTRATOR->value => $allPermissions,
            RoleName::CAREER_HEAD->value => self::values([
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
                PermissionName::DOCUMENTS_RECEIVE,
                PermissionName::DOCUMENTS_REVIEW,
                PermissionName::DOCUMENTS_ASSISTANT_REVIEW,
                PermissionName::DOCUMENTS_APPROVE,
                PermissionName::DOCUMENTS_RETURN,
                PermissionName::DOCUMENTS_REJECT,
                PermissionName::DOCUMENTS_DOWNLOAD,
                PermissionName::DOCUMENTS_VERSION,
                PermissionName::DOCUMENTS_COMMENT,
                PermissionName::DOCUMENTS_GRANT_ACCESS,
                PermissionName::DOCUMENTS_VIEW_HISTORY,
                PermissionName::REPORTS_VIEW_UNIT,
                PermissionName::REPORTS_EXPORT,
                PermissionName::NOTIFICATIONS_SEND,
                PermissionName::DELEGATIONS_MANAGE_UNIT,
            ]),
            RoleName::SECRETARY->value => self::values([
                PermissionName::UNITS_VIEW,
                PermissionName::DOCUMENT_TYPES_VIEW,
                PermissionName::DOCUMENTS_VIEW,
                PermissionName::DOCUMENTS_CREATE,
                PermissionName::DOCUMENTS_UPDATE_DRAFT,
                PermissionName::DOCUMENTS_DISCARD_DRAFT,
                PermissionName::DOCUMENTS_SEND,
                PermissionName::DOCUMENTS_RECEIVE,
                PermissionName::DOCUMENTS_REVIEW,
                PermissionName::DOCUMENTS_DOWNLOAD,
                PermissionName::DOCUMENTS_VERSION,
                PermissionName::DOCUMENTS_COMMENT,
                PermissionName::DOCUMENTS_GRANT_ACCESS,
                PermissionName::DOCUMENTS_VIEW_HISTORY,
                PermissionName::REPORTS_VIEW_UNIT,
                PermissionName::REPORTS_EXPORT,
                PermissionName::NOTIFICATIONS_SEND,
            ]),
            RoleName::SECRETARY_ASSISTANT->value => self::values([
                PermissionName::UNITS_VIEW,
                PermissionName::DOCUMENT_TYPES_VIEW,
                PermissionName::DOCUMENTS_VIEW,
                PermissionName::DOCUMENTS_REVIEW,
                PermissionName::DOCUMENTS_ASSISTANT_REVIEW,
                PermissionName::DOCUMENTS_DOWNLOAD,
                PermissionName::DOCUMENTS_COMMENT,
                PermissionName::REPORTS_VIEW_UNIT,
                PermissionName::REPORTS_EXPORT,
                PermissionName::NOTIFICATIONS_SEND,
            ]),
            RoleName::EXTRA->value => self::values([
                PermissionName::DOCUMENTS_VIEW,
                PermissionName::DOCUMENTS_DOWNLOAD,
            ]),
            RoleName::STUDENT->value => self::values([
                PermissionName::DOCUMENTS_VIEW,
                PermissionName::DOCUMENTS_CREATE,
                PermissionName::DOCUMENTS_UPDATE_DRAFT,
                PermissionName::DOCUMENTS_DISCARD_DRAFT,
                PermissionName::DOCUMENTS_SEND,
                PermissionName::DOCUMENTS_DOWNLOAD,
                PermissionName::DOCUMENTS_VERSION,
            ]),
        ];

        $roles[RoleName::KARDEX_HEAD->value] = array_values(array_diff(
            $roles[RoleName::CAREER_HEAD->value],
            [PermissionName::DELEGATIONS_MANAGE_UNIT->value],
        ));

        return $roles;
    }

    /**
     * @param  array<int, PermissionName>  $permissions
     * @return array<int, string>
     */
    private static function values(array $permissions): array
    {
        return array_map(
            fn (PermissionName $permission): string => $permission->value,
            $permissions,
        );
    }
}
