<?php

namespace App\Enums;

enum ActivityPriority: string
{
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::HIGH => 'Alta',
            self::URGENT => 'Urgente',
        };
    }
}
