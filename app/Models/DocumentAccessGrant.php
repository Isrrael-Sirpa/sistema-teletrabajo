<?php

namespace App\Models;

use App\Enums\DocumentAccessPermission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id', 'user_id', 'granted_by', 'permission', 'starts_at', 'ends_at',
    'reason', 'revoked_at', 'revoked_by', 'revocation_reason',
])]
class DocumentAccessGrant extends Model
{
    protected $table = 'concesiones_acceso_documentos';
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /** @param Builder<DocumentAccessGrant> $query */
    public function scopeEffective(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function (Builder $starts): void {
                $starts->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $ends): void {
                $ends->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
            'permission' => DocumentAccessPermission::class,
        ];
    }
}
