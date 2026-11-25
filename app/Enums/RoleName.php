<?php

namespace App\Enums;

enum RoleName: string
{
    case ADMINISTRATOR = 'administrator';
    case CO_ADMINISTRATOR = 'co_administrator';
    case CAREER_HEAD = 'career_head';
    case KARDEX_HEAD = 'kardex_head';
    case SECRETARY = 'secretary';
    case SECRETARY_ASSISTANT = 'secretary_assistant';
    case EXTRA = 'extra';

    public function isGlobal(): bool
    {
        return $this === self::ADMINISTRATOR;
    }

    public function requiresUnit(): bool
    {
        return $this !== self::KARDEX_HEAD;
    }

    public function isUnitResponsible(): bool
    {
        return in_array($this, [self::CAREER_HEAD, self::KARDEX_HEAD], true);
    }

    public function canReviewInternally(): bool
    {
        return in_array($this, [self::SECRETARY, self::SECRETARY_ASSISTANT], true);
    }

    public function isSecondary(): bool
    {
        return in_array($this, [self::SECRETARY, self::SECRETARY_ASSISTANT, self::EXTRA], true);
    }

    public function canBePersonalDocumentRecipient(): bool
    {
        return in_array($this, [
            self::CAREER_HEAD,
            self::KARDEX_HEAD,
            self::SECRETARY,
            self::SECRETARY_ASSISTANT,
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Administrador',
            self::CO_ADMINISTRATOR => 'Co-administrador',
            self::CAREER_HEAD => 'Jefe o responsable de unidad',
            self::KARDEX_HEAD => 'Responsable de unidad (rol anterior)',
            self::SECRETARY => 'Secretaria',
            self::SECRETARY_ASSISTANT => 'Auxiliar de Secretaría',
            self::EXTRA => 'Solicitante',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Administración técnica global desde el Departamento de Computación.',
            self::CO_ADMINISTRATOR => 'Administración facultativa desde Decanato o Vicedecanato.',
            self::CAREER_HEAD => 'Gestión y decisiones dentro de una carrera, departamento, instituto u otra unidad.',
            self::KARDEX_HEAD => 'Rol anterior conservado únicamente para compatibilidad.',
            self::SECRETARY => 'Gestión documental operativa dentro de su ámbito.',
            self::SECRETARY_ASSISTANT => 'Apoyo de revisión y seguimiento sin decisiones definitivas.',
            self::EXTRA => 'Docente, estudiante u otra persona que presenta correspondencia y recibe accesos autorizados.',
        };
    }

    public function isAssignable(): bool
    {
        return $this !== self::KARDEX_HEAD;
    }

    public function acceptsUnit(OrganizationalUnitType $type, ?string $acronym = null): bool
    {
        return match ($this) {
            self::ADMINISTRATOR => $type === OrganizationalUnitType::DEPARTMENT && $acronym === 'DCOMP',
            self::CO_ADMINISTRATOR => in_array($type, [OrganizationalUnitType::DEAN_OFFICE, OrganizationalUnitType::VICE_DEAN_OFFICE], true),
            self::KARDEX_HEAD => $type === OrganizationalUnitType::KARDEX,
            self::CAREER_HEAD => ! in_array($type, [OrganizationalUnitType::FACULTY, OrganizationalUnitType::DEAN_OFFICE, OrganizationalUnitType::VICE_DEAN_OFFICE], true),
            self::EXTRA => $type === OrganizationalUnitType::CAREER,
            default => $type !== OrganizationalUnitType::FACULTY,
        };
    }
}
