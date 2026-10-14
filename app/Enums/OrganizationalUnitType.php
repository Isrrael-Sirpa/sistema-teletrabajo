<?php

namespace App\Enums;

enum OrganizationalUnitType: string
{
    case FACULTY = 'faculty';
    case CAREER = 'career';
    case KARDEX = 'kardex';

    public function label(): string
    {
        return match ($this) {
            self::FACULTY => 'Facultad',
            self::CAREER => 'Carrera',
            self::KARDEX => 'Kardex',
        };
    }
}
