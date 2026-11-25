<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\OrganizationalUnitType;
use App\Enums\RoleName;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class OrganizationalUnitService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{name: string, acronym: string, description?: string|null}  $data
     */
    public function createCareer(User $actor, array $data): OrganizationalUnit
    {
        return DB::transaction(function () use ($actor, $data): OrganizationalUnit {
            $type = OrganizationalUnitType::from($data['type']);
            $parent = OrganizationalUnit::query()->active()->findOrFail($data['parent_id']);

            if ($type === OrganizationalUnitType::FACULTY) {
                throw new LogicException('La Facultad es la única unidad raíz y no puede crearse desde esta pantalla.');
            }

            $unit = OrganizationalUnit::query()->create([
                ...$data,
                'type' => $type,
                'correlative_prefix' => $type === OrganizationalUnitType::CAREER
                    ? 'FT C.'.Str::upper($data['acronym']).'.'
                    : null,
                'is_active' => true,
            ]);

            $this->audit->record($actor, AuditAction::UNIT_CREATED, $unit, newValues: [
                'public_id' => $unit->public_id,
                'name' => $unit->name,
                'acronym' => $unit->acronym,
                'type' => $unit->type,
                'parent_id' => $unit->parent_id,
                'is_active' => $unit->is_active,
            ]);

            return $unit;
        });
    }

    /**
     * @param  array{name: string, acronym: string, description?: string|null}  $data
     */
    public function updateCareer(User $actor, OrganizationalUnit $unit, array $data): OrganizationalUnit
    {
        $this->ensureEditable($actor, $unit);

        return DB::transaction(function () use ($actor, $unit, $data): OrganizationalUnit {
            $parent = OrganizationalUnit::query()->active()->findOrFail($data['parent_id']);
            if ($parent->id === $unit->id || $this->isDescendantOf($parent, $unit)) {
                throw new LogicException('Una unidad no puede depender de sí misma ni de una unidad subordinada.');
            }
            if ($unit->type === OrganizationalUnitType::CAREER && blank($unit->correlative_prefix)) {
                $data['correlative_prefix'] = 'FT C.'.Str::upper($data['acronym']).'.';
            }

            $oldValues = $unit->only(['name', 'acronym', 'correlative_prefix', 'description', 'is_active']);
            $unit->update($data);

            $this->audit->record(
                $actor,
                AuditAction::UNIT_UPDATED,
                $unit,
                $oldValues,
                $unit->only(['name', 'acronym', 'correlative_prefix', 'description', 'is_active']),
            );

            return $unit->refresh();
        });
    }

    public function setCareerActive(User $actor, OrganizationalUnit $unit, bool $active): OrganizationalUnit
    {
        $this->ensureEditable($actor, $unit);

        return DB::transaction(function () use ($actor, $unit, $active): OrganizationalUnit {
            $oldActive = $unit->is_active;
            $unit->update(['is_active' => $active]);

            $this->audit->record(
                $actor,
                $active ? AuditAction::UNIT_ACTIVATED : AuditAction::UNIT_DEACTIVATED,
                $unit,
                ['is_active' => $oldActive],
                ['is_active' => $active],
            );

            return $unit->refresh();
        });
    }

    private function ensureEditable(User $actor, OrganizationalUnit $unit): void
    {
        if ($unit->type === OrganizationalUnitType::FACULTY
            || ($unit->type === OrganizationalUnitType::KARDEX
                && ! $actor->hasRole(RoleName::ADMINISTRATOR->value))) {
            throw new LogicException('Esta unidad institucional está protegida.');
        }
    }

    private function isDescendantOf(OrganizationalUnit $candidate, OrganizationalUnit $ancestor): bool
    {
        $current = $candidate;
        while ($current->parent_id !== null) {
            if ($current->parent_id === $ancestor->id) {
                return true;
            }
            $current = $current->parent()->firstOrFail();
        }

        return false;
    }
}
