<?php

namespace App\Enums;

enum DelegationState: string
{
    case UPCOMING = 'upcoming';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::UPCOMING => 'Próxima',
            self::ACTIVE => 'Activa',
            self::EXPIRED => 'Expirada',
            self::REVOKED => 'Revocada',
        };
    }
}
