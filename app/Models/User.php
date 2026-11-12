<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OrganizationalUnitType;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['organizational_unit_id', 'name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    protected $table = 'usuarios';

    protected string $guard_name = 'web';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\Notification, static>
     */
    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function roleName(): ?RoleName
    {
        $roleName = $this->getRoleNames()->first();

        return is_string($roleName) ? RoleName::tryFrom($roleName) : null;
    }

    public function hasInstitutionalGlobalScope(): bool
    {
        return $this->roleName()?->isGlobal() === true;
    }

    public function isResponsibleForUnit(int $unitId): bool
    {
        $role = $this->roleName();

        return $this->status === UserStatus::ACTIVE
            && $this->organizational_unit_id === $unitId
            && $role?->isUnitResponsible()
            && $this->organizationalUnit?->is_active
            && $this->organizationalUnit->type->responsibleRole() === $role;
    }

    /** @param Builder<User> $query */
    public function scopeDocumentResponsibles(Builder $query): Builder
    {
        return $query->where('status', UserStatus::ACTIVE->value)
            ->whereHas('organizationalUnit', fn (Builder $unit) => $unit->active())
            ->where(function (Builder $responsibles): void {
                foreach ([OrganizationalUnitType::CAREER, OrganizationalUnitType::KARDEX] as $type) {
                    $responsibles->orWhere(fn (Builder $member) => $member
                        ->whereRelation('organizationalUnit', 'type', $type->value)
                        ->role($type->responsibleRole()->value));
                }
            });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }
}
