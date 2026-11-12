<?php

namespace App\Models;

use App\Enums\DelegationState;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'delegator_user_id', 'delegate_user_id', 'organizational_unit_id', 'authorized_by',
    'starts_at', 'ends_at', 'reason', 'status', 'revoked_at', 'revoked_by', 'revocation_reason',
])]
class TemporaryDelegation extends Model
{
    use HasUuids;

    protected $table = 'delegaciones_temporales';

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_user_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permisos_delegaciones_temporales')
            ->withPivot('created_at');
    }

    public function state(?CarbonInterface $at = null): DelegationState
    {
        $at ??= now();

        if ($this->revoked_at !== null) {
            return DelegationState::REVOKED;
        }

        if ($at->lt($this->starts_at)) {
            return DelegationState::UPCOMING;
        }

        return $at->lt($this->ends_at) ? DelegationState::ACTIVE : DelegationState::EXPIRED;
    }

    /** @param Builder<TemporaryDelegation> $query */
    public function scopeEffective(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
