<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(
        private readonly AdministratorAccountGuard $administrators,
        private readonly AuditLogService $audit,
        private readonly DelegatedAuthorizationService $delegations,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, status: string, role: string, organizational_unit_id?: int|string|null}  $data
     */
    public function create(User $actor, array $data): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        return DB::transaction(function () use ($actor, $data): User {
            $role = RoleName::from($data['role']);
            $unitId = $this->validatedUnitId($actor, $role, $data['organizational_unit_id'] ?? null);

            $this->lockSensitiveScope($role, $unitId);

            $this->ensureActiveResponsibleIsUnique($role, $unitId);

            $user = User::query()->create([
                'name' => Str::squish($data['name']),
                'academic_title' => $data['academic_title'] ?? null,
                'email' => Str::lower(trim($data['email'])),
                'password' => $data['password'],
                'status' => UserStatus::ACTIVE,
                'organizational_unit_id' => $unitId,
            ]);

            $user->syncRoles([$role->value]);

            $this->audit->record($actor, AuditAction::USER_CREATED, $user, newValues: [
                'public_id' => $user->public_id,
                'name' => $user->name,
                'academic_title' => $user->academic_title,
                'email' => $user->email,
                'status' => $user->status,
                'role' => $role,
                'organizational_unit_id' => $unitId,
            ]);

            return $user->load(['organizationalUnit', 'roles']);
        });
    }

    /**
     * @param  array{name: string, email: string, status: string, role: string, organizational_unit_id?: int|string|null}  $data
     */
    public function update(User $actor, User $target, array $data): User
    {
        Gate::forUser($actor)->authorize('update', $target);

        return DB::transaction(function () use ($actor, $target, $data): User {
            /** @var User $lockedTarget */
            $lockedTarget = User::query()->lockForUpdate()->findOrFail($target->getKey());
            $lockedTarget->load('roles');

            $oldRole = $lockedTarget->roleName();
            $oldValues = [
                'name' => $lockedTarget->name,
                'academic_title' => $lockedTarget->academic_title,
                'email' => $lockedTarget->email,
                'status' => $lockedTarget->status,
                'role' => $oldRole,
                'organizational_unit_id' => $lockedTarget->organizational_unit_id,
            ];
            $newRole = RoleName::from($data['role']);
            $newStatus = UserStatus::from($data['status']);
            $unitId = $this->validatedUnitId($actor, $newRole, $data['organizational_unit_id'] ?? null);

            if ($actor->is($lockedTarget)
                && ($oldRole !== $newRole || $newStatus !== $lockedTarget->status)) {
                throw ValidationException::withMessages([
                    'status' => 'No puede cambiar su propio rol ni desactivar su propia cuenta desde esta pantalla.',
                ]);
            }

            if ($oldRole === RoleName::ADMINISTRATOR && $newRole !== RoleName::ADMINISTRATOR) {
                $this->administrators->lockRole();
            }

            $this->lockSensitiveScope($newRole, $unitId);

            if ($oldRole === RoleName::ADMINISTRATOR
                && ($newRole !== RoleName::ADMINISTRATOR || $newStatus === UserStatus::INACTIVE)) {
                $this->administrators->ensureAnotherActiveExists($lockedTarget);
            }

            if ($newStatus === UserStatus::ACTIVE) {
                $this->ensureActiveResponsibleIsUnique($newRole, $unitId, $lockedTarget);
            }

            $lockedTarget->update([
                'name' => Str::squish($data['name']),
                'academic_title' => $data['academic_title'] ?? null,
                'email' => Str::lower(trim($data['email'])),
                'status' => $newStatus,
                'organizational_unit_id' => $unitId,
            ]);
            $lockedTarget->syncRoles([$newRole->value]);

            $newValues = [
                'name' => $lockedTarget->name,
                'academic_title' => $lockedTarget->academic_title,
                'email' => $lockedTarget->email,
                'status' => $lockedTarget->status,
                'role' => $newRole,
                'organizational_unit_id' => $unitId,
            ];
            $this->audit->record($actor, AuditAction::USER_UPDATED, $lockedTarget, $oldValues, $newValues);

            if ($oldRole !== $newRole) {
                $this->audit->record($actor, AuditAction::USER_ROLE_CHANGED, $lockedTarget,
                    ['role' => $oldRole], ['role' => $newRole]);
            }

            if ($oldValues['organizational_unit_id'] !== $unitId) {
                $this->audit->record($actor, AuditAction::USER_UNIT_CHANGED, $lockedTarget,
                    ['organizational_unit_id' => $oldValues['organizational_unit_id']],
                    ['organizational_unit_id' => $unitId]);
            }

            return $lockedTarget->load(['organizationalUnit', 'roles']);
        });
    }

    public function setActive(User $actor, User $target, bool $active): User
    {
        Gate::forUser($actor)->authorize($active ? 'activate' : 'deactivate', $target);

        return DB::transaction(function () use ($actor, $target, $active): User {
            /** @var User $lockedTarget */
            $lockedTarget = User::query()->lockForUpdate()->findOrFail($target->getKey());
            $lockedTarget->load('roles');
            $role = $lockedTarget->roleName();

            if (! $role) {
                throw ValidationException::withMessages(['role' => 'El usuario no tiene un rol institucional válido.']);
            }

            $this->lockSensitiveScope($role, $lockedTarget->organizational_unit_id);

            if (! $active && $role === RoleName::ADMINISTRATOR) {
                $this->administrators->ensureAnotherActiveExists($lockedTarget);
            }

            if ($active) {
                $this->ensureActiveResponsibleIsUnique($role, $lockedTarget->organizational_unit_id, $lockedTarget);
            }

            $oldStatus = $lockedTarget->status;
            $lockedTarget->update([
                'status' => $active ? UserStatus::ACTIVE : UserStatus::INACTIVE,
            ]);

            $this->audit->record(
                $actor,
                $active ? AuditAction::USER_ACTIVATED : AuditAction::USER_DEACTIVATED,
                $lockedTarget,
                ['status' => $oldStatus],
                ['status' => $active ? UserStatus::ACTIVE : UserStatus::INACTIVE],
            );

            return $lockedTarget->refresh();
        });
    }

    public function resetPassword(User $actor, User $target, string $password): void
    {
        Gate::forUser($actor)->authorize('resetPassword', $target);

        DB::transaction(function () use ($actor, $target, $password): void {
            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $lockedTarget->update(['password' => $password]);
            $this->audit->record($actor, AuditAction::USER_PASSWORD_RESET, $lockedTarget);
        });
    }

    /**
     * @return array<int, RoleName>
     */
    public function allowedRolesFor(User $actor): array
    {
        if ($actor->hasRole(RoleName::ADMINISTRATOR->value)) {
            return array_values(array_filter(RoleName::cases(), fn (RoleName $role): bool => $role->isAssignable()));
        }

        if ($actor->hasRole(RoleName::CO_ADMINISTRATOR->value)) {
            return array_values(array_filter(
                RoleName::cases(),
                fn (RoleName $role): bool => $role->isSecondary(),
            ));
        }

        if ($actor->isResponsibleForUnit((int) $actor->organizational_unit_id)) {
            return array_values(array_filter(
                RoleName::cases(),
                fn (RoleName $role): bool => $role->isSecondary(),
            ));
        }

        if ($this->delegations->hasAny($actor, PermissionName::USERS_ASSIGN_ROLES)) {
            return array_values(array_filter(
                RoleName::cases(),
                fn (RoleName $role): bool => $role->isSecondary(),
            ));
        }

        return [];
    }

    private function validatedUnitId(User $actor, RoleName $role, mixed $requestedUnitId): ?int
    {
        if (! in_array($role, $this->allowedRolesFor($actor), true)) {
            throw ValidationException::withMessages([
                'role' => 'No está autorizado para asignar el rol seleccionado.',
            ]);
        }

        $unitId = filter_var($requestedUnitId, FILTER_VALIDATE_INT);

        if (! $actor->hasInstitutionalGlobalScope()
            && $actor->isResponsibleForUnit((int) $actor->organizational_unit_id)
            && $unitId !== $actor->organizational_unit_id) {
            throw ValidationException::withMessages([
                'organizational_unit_id' => 'Solo puede asignar usuarios a su propia unidad.',
            ]);
        }

        if (! $actor->hasInstitutionalGlobalScope()
            && ! $actor->isResponsibleForUnit((int) $actor->organizational_unit_id)
            && ! $this->delegations->can($actor, PermissionName::USERS_ASSIGN_ROLES, (int) $unitId)) {
            throw ValidationException::withMessages([
                'organizational_unit_id' => 'Solo puede asignar usuarios dentro de la carrera delegada.',
            ]);
        }

        $unit = OrganizationalUnit::query()
            ->whereKey($unitId ?: 0)
            ->availableAsDocumentDestination()
            ->when($role === RoleName::STUDENT, fn ($query) => $query->where('type', 'career'))
            ->first();

        if (! $unit || ! $role->acceptsUnit($unit->type, $unit->acronym)) {
            throw ValidationException::withMessages([
                'organizational_unit_id' => 'Seleccione una unidad activa válida para este rol.',
            ]);
        }

        return $unit->getKey();
    }

    private function lockSensitiveScope(RoleName $role, ?int $unitId): void
    {
        if ($role === RoleName::ADMINISTRATOR) {
            $this->administrators->lockRole();
        }

        if ($role->isUnitResponsible() && $unitId !== null) {
            OrganizationalUnit::query()->whereKey($unitId)->lockForUpdate()->firstOrFail();
        }
    }

    private function ensureActiveResponsibleIsUnique(RoleName $role, ?int $unitId, ?User $excluded = null): void
    {
        if (! $role->isUnitResponsible() || $unitId === null) {
            return;
        }

        $query = User::query()
            ->where('organizational_unit_id', $unitId)
            ->where('status', UserStatus::ACTIVE->value)
            ->role($role->value);

        if ($excluded) {
            $query->whereKeyNot($excluded->getKey());
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'role' => 'La unidad ya tiene un responsable activo para el rol «'.$role->label().'».',
            ]);
        }
    }
}
