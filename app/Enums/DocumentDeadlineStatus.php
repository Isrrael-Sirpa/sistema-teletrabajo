<?php

namespace App\Enums;

enum DocumentDeadlineStatus: string
{
    case NO_DUE_DATE = 'no_due_date';
    case CURRENT = 'current';
    case DUE_SOON = 'due_soon';
    case OVERDUE = 'overdue';
    case NOT_APPLICABLE = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::NO_DUE_DATE => 'Sin fecha límite',
            self::CURRENT => 'Vigente',
            self::DUE_SOON => 'Próximo a vencer',
            self::OVERDUE => 'Vencido',
            self::NOT_APPLICABLE => 'No aplicable',
        };
    }

    public function requiresReminder(): bool
    {
        return in_array($this, [self::DUE_SOON, self::OVERDUE], true);
    }
}
