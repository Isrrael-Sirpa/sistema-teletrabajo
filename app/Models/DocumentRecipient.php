<?php

namespace App\Models;

use App\Enums\DocumentRecipientStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'document_id', 'unit_id', 'user_id', 'requires_response', 'status',
    'received_by', 'received_at', 'current_handler_id', 'decided_by',
    'decided_at', 'decision_reason', 'lock_version',
])]
class DocumentRecipient extends Model
{
    use SoftDeletes;

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'unit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function currentHandler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_handler_id');
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    protected function casts(): array
    {
        return [
            'requires_response' => 'boolean',
            'status' => DocumentRecipientStatus::class,
            'received_at' => 'datetime',
            'decided_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }
}
