<?php

namespace App\Enums;

enum RoleName: string
{
    case ADMINISTRATOR = 'administrator';
    case CO_ADMINISTRATOR = 'co_administrator';
    case CAREER_HEAD = 'career_head';
    case SECRETARY = 'secretary';
    case SECRETARY_ASSISTANT = 'secretary_assistant';
    case EXTRA = 'extra';

    public function isGlobal(): bool
    {
        return in_array($this, [self::ADMINISTRATOR, self::CO_ADMINISTRATOR], true);
    }

    public function requiresCareer(): bool
    {
        return ! $this->isGlobal();
    }

    public function isSecondary(): bool
    {
        return in_array($this, [self::SECRETARY, self::SECRETARY_ASSISTANT, self::EXTRA], true);
    }

    public function canBePersonalDocumentRecipient(): bool
    {
        return in_array($this, [
            self::CAREER_HEAD,
            self::SECRETARY,
            self::SECRETARY_ASSISTANT,
        ], true);
    }

    public function canBeFormalPersonalDocumentRecipient(): bool
    {
        return in_array($this, [self::CAREER_HEAD, self::SECRETARY], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Administrador',
            self::CO_ADMINISTRATOR => 'Co-administrador',
            self::CAREER_HEAD => 'Jefe de Carrera',
            self::SECRETARY => 'Secretaria',
            self::SECRETARY_ASSISTANT => 'Auxiliar de Secretaría',
            self::EXTRA => 'Extra',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Administración institucional global.',
            self::CO_ADMINISTRATOR => 'Administración operativa global sujeta a restricciones sensibles futuras.',
            self::CAREER_HEAD => 'Gestión y decisiones dentro del ámbito de una carrera.',
            self::SECRETARY => 'Gestión documental operativa dentro de su ámbito.',
            self::SECRETARY_ASSISTANT => 'Apoyo de revisión y seguimiento sin decisiones definitivas.',
            self::EXTRA => 'Consulta y descarga únicamente mediante autorización explícita.',
        };
    }
}
