<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\AuditResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'actor_id', 'action', 'auditable_type', 'auditable_id', 'ip_address',
    'user_agent', 'old_values', 'new_values', 'request_id', 'occurred_at',
])]
class AuditLog extends Model
{
    public $timestamps = false;

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function result(): AuditResult
    {
        return $this->action->result();
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Los registros de auditoría son inmutables.');
        });

        static::deleting(function (): never {
            throw new LogicException('Los registros de auditoría no pueden eliminarse.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
