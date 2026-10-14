<?php

namespace App\Enums;

enum DocumentRecipientStatus: string
{
    case PREPARED = 'prepared';
    case SENT = 'sent';
    case RECEIVED = 'received';
    case IN_REVIEW = 'in_review';
    case APPROVED = 'approved';
    case RETURNED = 'returned';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PREPARED => 'Preparado',
            self::SENT => 'Enviado',
            self::RECEIVED => 'Recibido',
            self::IN_REVIEW => 'En revisión',
            self::APPROVED => 'Aprobado',
            self::RETURNED => 'Devuelto',
            self::REJECTED => 'Rechazado',
        };
    }
}
