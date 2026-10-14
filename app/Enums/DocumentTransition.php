<?php

namespace App\Enums;

enum DocumentTransition: string
{
    case RECEIVE = 'receive';
    case START_REVIEW = 'start_review';
    case APPROVE = 'approve';
    case RETURN = 'return';
    case REJECT = 'reject';

    public function policyAbility(): string
    {
        return match ($this) {
            self::RECEIVE => 'receiveRecipient',
            self::START_REVIEW => 'reviewRecipient',
            self::APPROVE => 'approveRecipient',
            self::RETURN => 'returnRecipient',
            self::REJECT => 'rejectRecipient',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::RECEIVE => 'Recibir documento',
            self::START_REVIEW => 'Iniciar revisión',
            self::APPROVE => 'Aprobar',
            self::RETURN => 'Devolver',
            self::REJECT => 'Rechazar',
        };
    }
}
