<?php

namespace App\Enums;

enum DocumentAccessPermission: string
{
    case VIEW = 'view';
    case DOWNLOAD = 'download';

    public function label(): string
    {
        return match ($this) {
            self::VIEW => 'Ver',
            self::DOWNLOAD => 'Ver y descargar',
        };
    }
}
