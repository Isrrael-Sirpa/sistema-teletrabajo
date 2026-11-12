<?php

namespace App\Enums;

enum ReviewRecommendation: string
{
    case APPROVE = 'approve';
    case REJECT = 'reject';

    public function label(): string
    {
        return match ($this) {
            self::APPROVE => 'Recomendar aprobación',
            self::REJECT => 'Recomendar rechazo',
        };
    }
}
