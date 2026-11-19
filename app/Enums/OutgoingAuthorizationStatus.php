<?php

namespace App\Enums;

enum OutgoingAuthorizationStatus: string
{
    case NOT_REQUIRED = 'not_required';
    case NOT_REQUESTED = 'not_requested';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case RETURNED = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::NOT_REQUIRED => 'No requiere autorización',
            self::NOT_REQUESTED => 'Pendiente de solicitud',
            self::PENDING => 'Esperando autorización',
            self::APPROVED => 'Autorizado para enviar',
            self::RETURNED => 'Devuelto para corrección',
        };
    }
}
