<?php

namespace Tests\PostgreSQL;

use App\Enums\OrganizationalUnitType;
use App\Models\OrganizationalUnit;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgreSqlOrganizationalUnitTest extends TestCase
{
    use DatabaseTransactions;

    public function test_postgresql_contains_one_faculty_and_one_kardex_with_valid_hierarchy(): void
    {
        $faculty = OrganizationalUnit::query()->where('type', OrganizationalUnitType::FACULTY)->sole();
        $kardex = OrganizationalUnit::query()->where('type', OrganizationalUnitType::KARDEX)->sole();

        $this->assertNull($faculty->parent_id);
        $this->assertSame($faculty->id, $kardex->parent_id);
        $this->assertTrue($faculty->is_active);
        $this->assertTrue($kardex->is_active);
    }

    public function test_postgresql_rejects_a_second_active_kardex(): void
    {
        $faculty = OrganizationalUnit::query()->where('type', OrganizationalUnitType::FACULTY)->sole();

        $this->assertConstraintViolation(function () use ($faculty): void {
            $this->insertUnit([
                'parent_id' => $faculty->id,
                'type' => OrganizationalUnitType::KARDEX->value,
                'name' => 'Segundo Kardex no permitido',
                'acronym' => 'KARDEX2',
                'is_active' => true,
            ]);
        }, '23505');
    }

    public function test_postgresql_rejects_a_second_faculty(): void
    {
        $this->assertConstraintViolation(function (): void {
            $this->insertUnit([
                'parent_id' => null,
                'type' => OrganizationalUnitType::FACULTY->value,
                'name' => 'Segunda Facultad no permitida',
                'acronym' => 'FAC2',
                'is_active' => true,
            ]);
        }, '23505');
    }

    public function test_postgresql_rejects_a_career_whose_parent_is_not_the_faculty(): void
    {
        $faculty = OrganizationalUnit::query()->where('type', OrganizationalUnitType::FACULTY)->sole();
        $parentCareer = OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => OrganizationalUnitType::CAREER,
            'name' => 'Carrera padre inválida',
            'acronym' => 'CPI',
            'is_active' => true,
        ]);

        $this->assertConstraintViolation(function () use ($parentCareer): void {
            $this->insertUnit([
                'parent_id' => $parentCareer->id,
                'type' => OrganizationalUnitType::CAREER->value,
                'name' => 'Carrera hija inválida',
                'acronym' => 'CHI',
                'is_active' => true,
            ]);
        }, '23514');
    }

    public function test_postgresql_enforces_case_insensitive_names_and_acronyms(): void
    {
        $faculty = OrganizationalUnit::query()->where('type', OrganizationalUnitType::FACULTY)->sole();
        OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => OrganizationalUnitType::CAREER,
            'name' => 'Carrera de Validación',
            'acronym' => 'CDV',
            'is_active' => true,
        ]);

        $this->assertConstraintViolation(function () use ($faculty): void {
            $this->insertUnit([
                'parent_id' => $faculty->id,
                'type' => OrganizationalUnitType::CAREER->value,
                'name' => 'carrera de validación',
                'acronym' => 'OTRA',
                'is_active' => true,
            ]);
        }, '23505');

        $this->assertConstraintViolation(function () use ($faculty): void {
            $this->insertUnit([
                'parent_id' => $faculty->id,
                'type' => OrganizationalUnitType::CAREER->value,
                'name' => 'Otra carrera válida',
                'acronym' => 'cdv',
                'is_active' => true,
            ]);
        }, '23505');
    }

    public function test_the_institutional_seeder_is_idempotent_in_postgresql(): void
    {
        $before = OrganizationalUnit::query()->count();

        $this->seed(InstitutionalStructureSeeder::class);
        $this->seed(InstitutionalStructureSeeder::class);

        $this->assertSame($before, OrganizationalUnit::query()->count());
        $this->assertSame(1, OrganizationalUnit::query()->where('type', OrganizationalUnitType::FACULTY)->count());
        $this->assertSame(1, OrganizationalUnit::query()->where('type', OrganizationalUnitType::KARDEX)->count());
    }

    /**
     * @param  callable(): void  $callback
     */
    private function assertConstraintViolation(callable $callback, string $sqlState): void
    {
        DB::beginTransaction();

        try {
            $callback();
            $this->fail('PostgreSQL accepted an invalid organizational unit.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        } finally {
            if (DB::transactionLevel() > 1) {
                DB::rollBack();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertUnit(array $attributes): void
    {
        DB::table('organizational_units')->insert([
            'public_id' => (string) Str::uuid(),
            ...$attributes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
