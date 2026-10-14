<?php

namespace App\Enums;

enum AuditResult: string
{
    case SUCCESS = 'success';
    case DENIED = 'denied';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => 'Exitoso',
            self::DENIED => 'Denegado',
            self::FAILED => 'Fallido',
        };
    }
}
