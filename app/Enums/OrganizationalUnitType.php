<?php

namespace App\Enums;

enum OrganizationalUnitType: string
{
    case FACULTY = 'faculty';
    case CAREER = 'career';
    case KARDEX = 'kardex';

    public function responsibleRole(): ?RoleName
    {
        return match ($this) {
            self::CAREER => RoleName::CAREER_HEAD,
            self::KARDEX => RoleName::KARDEX_HEAD,
            self::FACULTY => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::FACULTY => 'Facultad',
            self::CAREER => 'Carrera',
            self::KARDEX => 'Kardex',
        };
    }
}
