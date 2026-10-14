<?php

namespace Database\Seeders;

use App\Enums\OrganizationalUnitType;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Seeder;

class InstitutionalStructureSeeder extends Seeder
{
    public function run(): void
    {
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
    }
}
