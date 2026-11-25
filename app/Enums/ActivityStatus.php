<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case SUBMITTED = 'submitted';
    case OBSERVED = 'observed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ASSIGNED => 'Asignada',
            self::IN_PROGRESS => 'En ejecución',
            self::SUBMITTED => 'Presentada para revisión',
            self::OBSERVED => 'Con observaciones',
            self::COMPLETED => 'Concluida',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }
}
