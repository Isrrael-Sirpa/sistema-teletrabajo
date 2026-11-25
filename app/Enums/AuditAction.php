<?php

namespace App\Enums;

enum AuditAction: string
{
    case AUTH_LOGIN_SUCCEEDED = 'auth.login_succeeded';
    case AUTH_LOGIN_FAILED = 'auth.login_failed';
    case AUTH_LOGIN_DENIED_INACTIVE = 'auth.login_denied_inactive';
    case AUTH_LOGIN_RATE_LIMITED = 'auth.login_rate_limited';
    case AUTH_LOGOUT = 'auth.logout';

    case USER_CREATED = 'users.created';
    case USER_UPDATED = 'users.updated';
    case USER_ACTIVATED = 'users.activated';
    case USER_DEACTIVATED = 'users.deactivated';
    case USER_ROLE_CHANGED = 'users.role_changed';
    case USER_UNIT_CHANGED = 'users.unit_changed';
    case USER_PASSWORD_RESET = 'users.password_reset';

    case UNIT_CREATED = 'units.created';
    case UNIT_UPDATED = 'units.updated';
    case UNIT_ACTIVATED = 'units.activated';
    case UNIT_DEACTIVATED = 'units.deactivated';

    case DOCUMENT_TYPE_CREATED = 'document_types.created';
    case DOCUMENT_TYPE_UPDATED = 'document_types.updated';
    case DOCUMENT_TYPE_ACTIVATED = 'document_types.activated';
    case DOCUMENT_TYPE_DEACTIVATED = 'document_types.deactivated';

    case DOCUMENT_SENT = 'documents.sent';
    case DOCUMENT_AUTHORIZATION_REQUESTED = 'documents.authorization_requested';
    case DOCUMENT_AUTHORIZATION_APPROVED = 'documents.authorization_approved';
    case DOCUMENT_AUTHORIZATION_RETURNED = 'documents.authorization_returned';
    case DOCUMENT_ANNULLED = 'documents.annulled';
    case REVIEW_TASK_ASSIGNED = 'review_tasks.assigned';
    case REVIEW_TASK_SUBMITTED = 'review_tasks.submitted';
    case FILE_DOWNLOADED = 'files.downloaded';

    case ACCESS_GRANT_CREATED = 'access_grants.created';
    case ACCESS_GRANT_LEVEL_CHANGED = 'access_grants.level_changed';
    case ACCESS_GRANT_REVOKED = 'access_grants.revoked';

    case DELEGATION_CREATED = 'delegations.created';
    case DELEGATION_REVOKED = 'delegations.revoked';

    case REPORT_EXPORTED = 'reports.exported';

    case ACTIVITY_CREATED = 'activities.created';
    case ACTIVITY_PROGRESS_RECORDED = 'activities.progress_recorded';
    case ACTIVITY_SUBMITTED = 'activities.submitted';
    case ACTIVITY_OBSERVED = 'activities.observed';
    case ACTIVITY_COMPLETED = 'activities.completed';
    case ACTIVITY_CANCELLED = 'activities.cancelled';

    public function result(): AuditResult
    {
        return match ($this) {
            self::AUTH_LOGIN_FAILED => AuditResult::FAILED,
            self::AUTH_LOGIN_DENIED_INACTIVE,
            self::AUTH_LOGIN_RATE_LIMITED => AuditResult::DENIED,
            default => AuditResult::SUCCESS,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AUTH_LOGIN_SUCCEEDED => 'Inicio de sesión exitoso',
            self::AUTH_LOGIN_FAILED => 'Inicio de sesión fallido',
            self::AUTH_LOGIN_DENIED_INACTIVE => 'Inicio denegado: cuenta inactiva',
            self::AUTH_LOGIN_RATE_LIMITED => 'Inicio denegado: límite de intentos',
            self::AUTH_LOGOUT => 'Cierre de sesión',
            self::USER_CREATED => 'Usuario creado',
            self::USER_UPDATED => 'Usuario editado',
            self::USER_ACTIVATED => 'Usuario activado',
            self::USER_DEACTIVATED => 'Usuario desactivado',
            self::USER_ROLE_CHANGED => 'Rol de usuario cambiado',
            self::USER_UNIT_CHANGED => 'Unidad de usuario cambiada',
            self::USER_PASSWORD_RESET => 'Contraseña restablecida',
            self::UNIT_CREATED => 'Carrera creada',
            self::UNIT_UPDATED => 'Carrera editada',
            self::UNIT_ACTIVATED => 'Carrera activada',
            self::UNIT_DEACTIVATED => 'Carrera desactivada',
            self::DOCUMENT_TYPE_CREATED => 'Tipo documental creado',
            self::DOCUMENT_TYPE_UPDATED => 'Tipo documental editado',
            self::DOCUMENT_TYPE_ACTIVATED => 'Tipo documental activado',
            self::DOCUMENT_TYPE_DEACTIVATED => 'Tipo documental desactivado',
            self::DOCUMENT_SENT => 'Documento enviado oficialmente',
            self::DOCUMENT_AUTHORIZATION_REQUESTED => 'Autorización de salida solicitada',
            self::DOCUMENT_AUTHORIZATION_APPROVED => 'Salida documental autorizada',
            self::DOCUMENT_AUTHORIZATION_RETURNED => 'Autorización devuelta para corrección',
            self::DOCUMENT_ANNULLED => 'Documento anulado',
            self::REVIEW_TASK_ASSIGNED => 'Revisión interna asignada',
            self::REVIEW_TASK_SUBMITTED => 'Revisión interna entregada',
            self::FILE_DOWNLOADED => 'Archivo privado descargado',
            self::ACCESS_GRANT_CREATED => 'Acceso explícito concedido',
            self::ACCESS_GRANT_LEVEL_CHANGED => 'Nivel de acceso cambiado',
            self::ACCESS_GRANT_REVOKED => 'Acceso explícito revocado',
            self::DELEGATION_CREATED => 'Delegación temporal creada',
            self::DELEGATION_REVOKED => 'Delegación temporal revocada',
            self::REPORT_EXPORTED => 'Reporte documental exportado',
            self::ACTIVITY_CREATED => 'Actividad asignada',
            self::ACTIVITY_PROGRESS_RECORDED => 'Avance de actividad registrado',
            self::ACTIVITY_SUBMITTED => 'Resultado de actividad presentado',
            self::ACTIVITY_OBSERVED => 'Actividad devuelta con observaciones',
            self::ACTIVITY_COMPLETED => 'Actividad concluida',
            self::ACTIVITY_CANCELLED => 'Actividad cancelada',
        };
    }

    /** @return array<int, string> */
    public static function valuesForResult(AuditResult $result): array
    {
        return array_values(array_map(
            fn (self $action): string => $action->value,
            array_filter(self::cases(), fn (self $action): bool => $action->result() === $result),
        ));
    }
}
