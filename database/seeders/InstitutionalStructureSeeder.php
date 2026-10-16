<?php

namespace Database\Seeders;

use App\Enums\OrganizationalUnitType;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InstitutionalStructureSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $careers = config('institutional_careers');

            if (! is_array($careers) || count($careers) !== 9) {
                throw new RuntimeException('El catálogo institucional debe contener exactamente nueve carreras.');
            }

            $faculty = OrganizationalUnit::query()->updateOrCreate(
                ['type' => OrganizationalUnitType::FACULTY->value],
                [
                    'parent_id' => null,
                    'name' => config('institution.faculty'),
                    'acronym' => config('institution.acronym'),
                    'description' => 'Unidad raíz institucional de la Universidad Mayor de San Andrés.',
                    'is_active' => true,
                ],
            );

            OrganizationalUnit::query()->updateOrCreate(
                ['type' => OrganizationalUnitType::KARDEX->value],
                [
                    'parent_id' => $faculty->id,
                    'name' => 'Kardex de la Facultad de Tecnología',
                    'acronym' => 'KARDEX',
                    'description' => 'Kardex institucional único de la Facultad de Tecnología.',
                    'is_active' => true,
                ],
            );

            foreach ($careers as $career) {
                $name = $career['name'] ?? null;
                $acronym = $career['acronym'] ?? null;

                if (! is_string($name) || trim($name) === '' || ! is_string($acronym)
                    || trim($acronym) === '' || mb_strlen($acronym) > 20) {
                    throw new RuntimeException('El catálogo contiene una carrera sin nombre o sigla válida.');
                }

                $existing = OrganizationalUnit::query()
                    ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
                    ->first();

                if ($existing) {
                    if ($existing->type !== OrganizationalUnitType::CAREER
                        || $existing->parent_id !== $faculty->id) {
                        throw new RuntimeException("La unidad {$name} ya existe con un tipo o padre incompatible.");
                    }

                    // La sigla (y el estado) pueden haber sido cambiados por el Administrador.
                    continue;
                }

                if (OrganizationalUnit::query()
                    ->whereRaw('LOWER(acronym) = ?', [Str::lower($acronym)])
                    ->exists()) {
                    throw new RuntimeException("La sigla {$acronym} ya pertenece a otra unidad.");
                }

                OrganizationalUnit::query()->create([
                    'parent_id' => $faculty->id,
                    'type' => OrganizationalUnitType::CAREER,
                    'name' => $name,
                    'acronym' => $acronym,
                    'description' => null,
                    'is_active' => true,
                ]);
            }
        });
    }
}
