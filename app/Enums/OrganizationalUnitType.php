<?php

namespace App\Enums;

enum OrganizationalUnitType: string
{
    case FACULTY = 'faculty';
    case CAREER = 'career';
    case KARDEX = 'kardex';
    case DEAN_OFFICE = 'dean_office';
    case VICE_DEAN_OFFICE = 'vice_dean_office';
    case ADMIN_DIRECTION = 'admin_direction';
    case DEPARTMENT = 'department';
    case LIBRARY = 'library';
    case INSTITUTE = 'institute';
    case POSTGRADUATE_UNIT = 'postgraduate_unit';
    case PROGRAM = 'program';
    case OTHER = 'other';

    public function responsibleRole(): ?RoleName
    {
        return match ($this) {
            self::CAREER, self::KARDEX, self::DEAN_OFFICE, self::VICE_DEAN_OFFICE,
            self::ADMIN_DIRECTION, self::DEPARTMENT, self::LIBRARY, self::INSTITUTE,
            self::POSTGRADUATE_UNIT, self::PROGRAM, self::OTHER => RoleName::CAREER_HEAD,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::FACULTY => 'Facultad',
            self::CAREER => 'Carrera',
            self::KARDEX => 'Kardex',
            self::DEAN_OFFICE => 'Decanato',
            self::VICE_DEAN_OFFICE => 'Vicedecanato',
            self::ADMIN_DIRECTION => 'Dirección administrativa',
            self::DEPARTMENT => 'Departamento',
            self::LIBRARY => 'Biblioteca',
            self::INSTITUTE => 'Instituto',
            self::POSTGRADUATE_UNIT => 'Unidad de postgrado',
            self::PROGRAM => 'Programa',
            self::OTHER => 'Otra unidad',
        };
    }
}
