<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\OrganizationalUnitType;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TemporaryDelegation;
use App\Models\User;
use App\Support\CareerHeadDelegationCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TemporaryDelegationService
{
    private const MAXIMUM_DAYS = 366;

    public function __construct(private readonly AuditLogService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): TemporaryDelegation
    {
        return DB::transaction(function () use ($actor, $data): TemporaryDelegation {
            // The career row is the common mutex and must be locked before overlap checks.
            $unit = OrganizationalUnit::query()
                ->whereKey((int) $data['organizational_unit_id'])
                ->where('type', OrganizationalUnitType::CAREER->value)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $unit) {
                throw ValidationException::withMessages(['organizational_unit_id' => 'Seleccione una carrera activa válida.']);
            }

            $delegator = User::query()->with('roles')->find((int) $data['delegator_user_id']);
            $delegate = User::query()->with('roles')->find((int) $data['delegate_user_id']);
            $this->assertDelegator($delegator, $unit);
            $this->assertDelegate($delegate, $unit);
            $this->authorizeCreation($actor, $delegator, $unit);

            $startsAt = CarbonImmutable::parse((string) $data['starts_at'], config('app.timezone'));
            $endsAt = CarbonImmutable::parse((string) $data['ends_at'], config('app.timezone'));
            $this->assertDates($startsAt, $endsAt);
            $permissions = $this->validatedPermissions((array) $data['permissions']);

            $overlaps = TemporaryDelegation::query()
                ->where('organizational_unit_id', $unit->getKey())
                ->whereNull('revoked_at')
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Ya existe una delegación de Jefatura que se superpone con el período solicitado.',
                ]);
            }

            $delegation = TemporaryDelegation::query()->create([
                'delegator_user_id' => $delegator->getKey(),
                'delegate_user_id' => $delegate->getKey(),
                'organizational_unit_id' => $unit->getKey(),
                'authorized_by' => $actor->getKey(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => trim((string) $data['reason']),
                'status' => 'scheduled',
            ]);

            $permissionModels = Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $permissions)
                ->get();
            $delegation->permissions()->attach($permissionModels->mapWithKeys(
                fn (Permission $permission): array => [$permission->getKey() => ['created_at' => now()]],
            ));

            $this->audit->record($actor, AuditAction::DELEGATION_CREATED, $delegation, newValues: [
                'delegation_public_id' => $delegation->public_id,
                'delegator_user_id' => $delegator->getKey(),
                'delegate_user_id' => $delegate->getKey(),
                'organizational_unit_id' => $unit->getKey(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'permissions' => $permissions,
                'reason' => $delegation->reason,
                'authorized_by' => $actor->getKey(),
            ]);

            return $delegation->load(['delegator', 'delegate', 'organizationalUnit', 'authorizer', 'permissions']);
        }, 3);
    }

    public function revoke(User $actor, TemporaryDelegation $delegation, string $reason): TemporaryDelegation
    {
        return DB::transaction(function () use ($actor, $delegation, $reason): TemporaryDelegation {
            $locked = TemporaryDelegation::query()->lockForUpdate()->findOrFail($delegation->getKey());
            OrganizationalUnit::query()->whereKey($locked->organizational_unit_id)->lockForUpdate()->firstOrFail();
            $locked->load(['delegator.roles', 'delegate.roles', 'permissions']);
            $this->authorizeRevocation($actor, $locked);

            if ($locked->revoked_at !== null || now()->gte($locked->ends_at)) {
                throw ValidationException::withMessages(['delegation' => 'La delegación ya no puede revocarse.']);
            }

            $cleanReason = trim($reason);
            if ($cleanReason === '') {
                throw ValidationException::withMessages(['revocation_reason' => 'Indique el motivo de la revocación.']);
            }

            $locked->forceFill([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_by' => $actor->getKey(),
                'revocation_reason' => $cleanReason,
            ])->save();

            $this->audit->record($actor, AuditAction::DELEGATION_REVOKED, $locked,
                ['revoked_at' => null],
                ['revoked_at' => $locked->revoked_at, 'revoked_by' => $actor->getKey(), 'reason' => $cleanReason],
            );

            return $locked->refresh();
        }, 3);
    }

    /** @return array<int, string> */
    private function validatedPermissions(array $requested): array
    {
        $permissions = array_values(array_unique(array_filter($requested, 'is_string')));
        $allowed = CareerHeadDelegationCatalog::values();

        if ($permissions === [] || array_diff($permissions, $allowed) !== []) {
            throw ValidationException::withMessages(['permissions' => 'Seleccione únicamente capacidades delegables de Jefatura.']);
        }

        $headRole = Role::query()->where('name', RoleName::CAREER_HEAD->value)->where('guard_name', 'web')->firstOrFail();
        $headPermissions = $headRole->permissions()->pluck('name')->all();

        if (array_diff($permissions, $headPermissions) !== []) {
            throw ValidationException::withMessages(['permissions' => 'La delegación no puede superar las capacidades del Jefe titular.']);
        }

        if (Permission::query()->where('guard_name', 'web')->whereIn('name', $permissions)->count() !== count($permissions)) {
            throw ValidationException::withMessages(['permissions' => 'Una capacidad seleccionada no existe en el catálogo RBAC.']);
        }

        return $permissions;
    }

    private function assertDelegator(?User $delegator, OrganizationalUnit $unit): void
    {
        if (! $delegator
            || $delegator->status !== UserStatus::ACTIVE
            || $delegator->organizational_unit_id !== $unit->getKey()
            || ! $delegator->hasRole(RoleName::CAREER_HEAD->value)) {
            throw ValidationException::withMessages(['delegator_user_id' => 'Seleccione al Jefe titular activo de la carrera.']);
        }
    }

    private function assertDelegate(?User $delegate, OrganizationalUnit $unit): void
    {
        if (! $delegate
            || $delegate->status !== UserStatus::ACTIVE
            || $delegate->organizational_unit_id !== $unit->getKey()
            || ! $delegate->hasRole(RoleName::SECRETARY->value)) {
            throw ValidationException::withMessages(['delegate_user_id' => 'El delegado debe ser una Secretaria activa de la misma carrera.']);
        }
    }

    private function authorizeCreation(User $actor, User $delegator, OrganizationalUnit $unit): void
    {
        if ($actor->status === UserStatus::ACTIVE
            && $actor->hasInstitutionalGlobalScope()
            && $actor->can(PermissionName::DELEGATIONS_MANAGE_GLOBAL->value)) {
            return;
        }

        if ($actor->is($delegator)
            && $actor->status === UserStatus::ACTIVE
            && $actor->organizational_unit_id === $unit->getKey()
            && $actor->can(PermissionName::DELEGATIONS_MANAGE_UNIT->value)) {
            return;
        }

        abort(403);
    }

    private function authorizeRevocation(User $actor, TemporaryDelegation $delegation): void
    {
        if ($actor->status === UserStatus::ACTIVE
            && $actor->hasInstitutionalGlobalScope()
            && $actor->can(PermissionName::DELEGATIONS_MANAGE_GLOBAL->value)) {
            return;
        }

        if ($actor->getKey() === $delegation->delegator_user_id
            && $actor->status === UserStatus::ACTIVE
            && $actor->organizational_unit_id === $delegation->organizational_unit_id
            && $actor->can(PermissionName::DELEGATIONS_MANAGE_UNIT->value)) {
            return;
        }

        abort(403);
    }

    private function assertDates(CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        if (! $startsAt->lt($endsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'La fecha final debe ser posterior al inicio.']);
        }

        if ($endsAt->lte(now())) {
            throw ValidationException::withMessages(['ends_at' => 'La delegación debe finalizar en el futuro.']);
        }

        if ($startsAt->diffInDays($endsAt) > self::MAXIMUM_DAYS) {
            throw ValidationException::withMessages(['ends_at' => 'La delegación no puede superar 366 días.']);
        }
    }
}
