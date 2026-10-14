<?php

namespace App\Enums;

enum DocumentPriority: string
{
    case NORMAL = 'normal';
    case IMPORTANT = 'important';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::IMPORTANT => 'Importante',
            self::URGENT => 'Urgente',
        };
    }
}
