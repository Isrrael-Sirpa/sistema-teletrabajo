<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case RECEIVED = 'received';
    case IN_REVIEW = 'in_review';
    case APPROVED = 'approved';
    case RETURNED = 'returned';
    case REJECTED = 'rejected';
    case ANNULLED = 'annulled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::SENT => 'Enviado',
            self::RECEIVED => 'Recibido',
            self::IN_REVIEW => 'En revisión',
            self::APPROVED => 'Aprobado',
            self::RETURNED => 'Devuelto',
            self::REJECTED => 'Rechazado',
            self::ANNULLED => 'Anulado',
        };
    }

    public function hasPendingDeadlineWork(): bool
    {
        return in_array($this, [
            self::SENT,
            self::RECEIVED,
            self::IN_REVIEW,
            self::RETURNED,
        ], true);
    }

    /** @return array<int, string> */
    public static function deadlineActionableValues(): array
    {
        return array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status->hasPendingDeadlineWork()),
        );
    }
}
