<?php

namespace App\Enums;

enum PermissionName: string
{
    case USERS_VIEW = 'users.view';
    case USERS_CREATE = 'users.create';
    case USERS_UPDATE = 'users.update';
    case USERS_ACTIVATE = 'users.activate';
    case USERS_DEACTIVATE = 'users.deactivate';
    case USERS_RESET_PASSWORD = 'users.reset_password';
    case USERS_ASSIGN_ROLES = 'users.assign_roles';

    case UNITS_VIEW = 'units.view';
    case UNITS_CREATE = 'units.create';
    case UNITS_UPDATE = 'units.update';
    case UNITS_ACTIVATE = 'units.activate';
    case UNITS_DEACTIVATE = 'units.deactivate';
    case UNITS_MANAGE_KARDEX = 'units.manage_kardex';

    case DOCUMENT_TYPES_VIEW = 'document_types.view';
    case DOCUMENT_TYPES_MANAGE = 'document_types.manage';

    case DOCUMENTS_VIEW = 'documents.view';
    case DOCUMENTS_CREATE = 'documents.create';
    case DOCUMENTS_UPDATE_DRAFT = 'documents.update_draft';
    case DOCUMENTS_DISCARD_DRAFT = 'documents.discard_draft';
    case DOCUMENTS_SEND = 'documents.send';
    case DOCUMENTS_RECEIVE = 'documents.receive';
    case DOCUMENTS_REVIEW = 'documents.review';
    case DOCUMENTS_ASSISTANT_REVIEW = 'documents.assistant_review';
    case DOCUMENTS_APPROVE = 'documents.approve';
    case DOCUMENTS_RETURN = 'documents.return';
    case DOCUMENTS_REJECT = 'documents.reject';
    case DOCUMENTS_DOWNLOAD = 'documents.download';
    case DOCUMENTS_VERSION = 'documents.version';
    case DOCUMENTS_COMMENT = 'documents.comment';
    case DOCUMENTS_GRANT_ACCESS = 'documents.grant_access';
    case DOCUMENTS_ANNUL = 'documents.annul';
    case DOCUMENTS_VIEW_HISTORY = 'documents.view_history';

    case REPORTS_VIEW_GLOBAL = 'reports.view_global';
    case REPORTS_VIEW_UNIT = 'reports.view_unit';
    case REPORTS_EXPORT = 'reports.export';

    case AUDIT_VIEW = 'audit.view';
    case ROLES_MANAGE = 'roles.manage';
    case PERMISSIONS_MANAGE = 'permissions.manage';
    case SETTINGS_MANAGE = 'settings.manage';
    case NOTIFICATIONS_SEND = 'notifications.send';
    case DELEGATIONS_MANAGE_GLOBAL = 'delegations.manage_global';
    case DELEGATIONS_MANAGE_UNIT = 'delegations.manage_unit';

    public function label(): string
    {
        return match ($this) {
            self::USERS_VIEW => 'Ver usuarios',
            self::USERS_CREATE => 'Crear usuarios',
            self::USERS_UPDATE => 'Actualizar usuarios',
            self::USERS_ACTIVATE => 'Activar usuarios',
            self::USERS_DEACTIVATE => 'Desactivar usuarios',
            self::USERS_RESET_PASSWORD => 'Restablecer contraseñas',
            self::USERS_ASSIGN_ROLES => 'Asignar roles',
            self::UNITS_VIEW => 'Ver unidades',
            self::UNITS_CREATE => 'Crear unidades',
            self::UNITS_UPDATE => 'Actualizar unidades',
            self::UNITS_ACTIVATE => 'Activar unidades',
            self::UNITS_DEACTIVATE => 'Desactivar unidades',
            self::UNITS_MANAGE_KARDEX => 'Gestionar Kardex',
            self::DOCUMENT_TYPES_VIEW => 'Ver tipos documentales',
            self::DOCUMENT_TYPES_MANAGE => 'Gestionar tipos documentales',
            self::DOCUMENTS_VIEW => 'Ver documentos autorizados',
            self::DOCUMENTS_CREATE => 'Crear documentos',
            self::DOCUMENTS_UPDATE_DRAFT => 'Editar borradores',
            self::DOCUMENTS_DISCARD_DRAFT => 'Descartar borradores',
            self::DOCUMENTS_SEND => 'Enviar documentos',
            self::DOCUMENTS_RECEIVE => 'Recibir documentos',
            self::DOCUMENTS_REVIEW => 'Revisar documentos',
            self::DOCUMENTS_ASSISTANT_REVIEW => 'Marcar revisión auxiliar',
            self::DOCUMENTS_APPROVE => 'Aprobar documentos',
            self::DOCUMENTS_RETURN => 'Devolver documentos',
            self::DOCUMENTS_REJECT => 'Rechazar documentos',
            self::DOCUMENTS_DOWNLOAD => 'Descargar documentos autorizados',
            self::DOCUMENTS_VERSION => 'Crear versiones documentales',
            self::DOCUMENTS_COMMENT => 'Agregar observaciones',
            self::DOCUMENTS_GRANT_ACCESS => 'Conceder acceso documental',
            self::DOCUMENTS_ANNUL => 'Anular documentos',
            self::DOCUMENTS_VIEW_HISTORY => 'Ver historial documental',
            self::REPORTS_VIEW_GLOBAL => 'Ver reportes globales',
            self::REPORTS_VIEW_UNIT => 'Ver reportes de unidad',
            self::REPORTS_EXPORT => 'Exportar reportes',
            self::AUDIT_VIEW => 'Ver auditoría',
            self::ROLES_MANAGE => 'Gestionar roles',
            self::PERMISSIONS_MANAGE => 'Gestionar permisos',
            self::SETTINGS_MANAGE => 'Gestionar configuración institucional',
            self::NOTIFICATIONS_SEND => 'Emitir notificaciones internas',
            self::DELEGATIONS_MANAGE_GLOBAL => 'Gestionar delegaciones globales',
            self::DELEGATIONS_MANAGE_UNIT => 'Gestionar delegaciones de unidad',
        };
    }
}
