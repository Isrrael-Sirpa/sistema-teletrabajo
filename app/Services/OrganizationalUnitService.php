<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\OrganizationalUnitType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
            $faculty = OrganizationalUnit::query()
                ->where('type', OrganizationalUnitType::FACULTY->value)
                ->where('is_active', true)
                ->firstOrFail();

            $unit = OrganizationalUnit::query()->create([
                ...$data,
                'parent_id' => $faculty->id,
                'type' => OrganizationalUnitType::CAREER,
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
        $this->ensureCareer($unit);

        return DB::transaction(function () use ($actor, $unit, $data): OrganizationalUnit {
            $oldValues = $unit->only(['name', 'acronym', 'description', 'is_active']);
            $unit->update($data);

            $this->audit->record(
                $actor,
                AuditAction::UNIT_UPDATED,
                $unit,
                $oldValues,
                $unit->only(['name', 'acronym', 'description', 'is_active']),
            );

            return $unit->refresh();
        });
    }

    public function setCareerActive(User $actor, OrganizationalUnit $unit, bool $active): OrganizationalUnit
    {
        $this->ensureCareer($unit);

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

    private function ensureCareer(OrganizationalUnit $unit): void
    {
        if ($unit->type !== OrganizationalUnitType::CAREER) {
            throw new LogicException('Only career units can be managed through this service.');
        }
    }
}
