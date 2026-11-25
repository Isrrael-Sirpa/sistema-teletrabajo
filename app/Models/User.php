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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['organizational_unit_id', 'name', 'academic_title', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    protected $table = 'usuarios';

    protected string $guard_name = 'web';

    /**
     * @return MorphMany<Notification, static>
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

    public function assignedActivities(): BelongsToMany
    {
        return $this->belongsToMany(WorkActivity::class, 'responsables_actividades', 'user_id', 'activity_id')
            ->withPivot('responsibility')->withTimestamps();
    }

    public function roleName(): ?RoleName
    {
        $roleName = $this->getRoleNames()->first();

        return is_string($roleName) ? RoleName::tryFrom($roleName) : null;
    }

    public function displayName(): string
    {
        return $this->academic_title ? $this->academic_title.' '.$this->name : $this->name;
    }

    public function institutionalPosition(): string
    {
        $role = $this->roleName();
        $unit = $this->organizationalUnit;

        if (! $role) {
            return 'Usuario institucional';
        }

        return match ($role) {
            RoleName::ADMINISTRATOR => 'Jefe del Departamento de Computación',
            RoleName::CO_ADMINISTRATOR => match ($unit?->type) {
                OrganizationalUnitType::DEAN_OFFICE => 'Decano',
                OrganizationalUnitType::VICE_DEAN_OFFICE => 'Vicedecano',
                default => 'Autoridad facultativa',
            },
            RoleName::CAREER_HEAD => match ($unit?->type) {
                OrganizationalUnitType::CAREER => 'Director de la carrera de '.$unit->name,
                OrganizationalUnitType::DEPARTMENT => 'Jefe de '.$unit->name,
                OrganizationalUnitType::INSTITUTE => 'Director de '.$unit->name,
                default => 'Responsable de '.($unit?->name ?? 'unidad'),
            },
            RoleName::KARDEX_HEAD => 'Responsable de Kardex',
            RoleName::SECRETARY => 'Secretaria de '.($unit?->name ?? 'la unidad'),
            RoleName::SECRETARY_ASSISTANT => 'Auxiliar de Secretaría de '.($unit?->name ?? 'la unidad'),
            RoleName::EXTRA => 'Solicitante',
        };
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
            && $this->organizationalUnit?->is_active
            && (
                ($role?->isUnitResponsible()
                    && ($role === RoleName::KARDEX_HEAD
                        ? $this->organizationalUnit->type === OrganizationalUnitType::KARDEX
                        : $role === RoleName::CAREER_HEAD))
                || ($role === RoleName::ADMINISTRATOR
                    && $this->organizationalUnit->type === OrganizationalUnitType::DEPARTMENT
                    && $this->organizationalUnit->acronym === 'DCOMP')
                || ($role === RoleName::CO_ADMINISTRATOR
                    && in_array($this->organizationalUnit->type, [
                        OrganizationalUnitType::DEAN_OFFICE,
                        OrganizationalUnitType::VICE_DEAN_OFFICE,
                    ], true))
            );
    }

    /** @return array<int, int> */
    public function activityScopeUnitIds(): array
    {
        if ($this->hasInstitutionalGlobalScope()) {
            return OrganizationalUnit::query()->availableAsDocumentDestination()->pluck('id')
                ->map(static fn ($id): int => (int) $id)->all();
        }

        if (! $this->isResponsibleForUnit((int) $this->organizational_unit_id)) {
            return [];
        }

        if ($this->roleName() === RoleName::CO_ADMINISTRATOR) {
            return OrganizationalUnit::descendantIdsFor((int) $this->organizational_unit_id);
        }

        return [(int) $this->organizational_unit_id];
    }

    public function canAssignActivityToUnit(int $unitId): bool
    {
        return in_array($unitId, $this->activityScopeUnitIds(), true);
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
