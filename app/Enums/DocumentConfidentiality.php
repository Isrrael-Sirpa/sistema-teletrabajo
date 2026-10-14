<?php

namespace App\Enums;

enum DocumentConfidentiality: string
{
    case NORMAL = 'normal';
    case RESTRICTED = 'restricted';

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::RESTRICTED => 'Restringida',
        };
    }
}
