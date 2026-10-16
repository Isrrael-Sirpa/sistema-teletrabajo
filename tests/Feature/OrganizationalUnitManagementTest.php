<?php

namespace Tests\Feature;

use App\Enums\OrganizationalUnitType;
use App\Enums\RoleName;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OrganizationalUnitManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AuthorizationSeeder::class,
            InstitutionalStructureSeeder::class,
        ]);
    }

    public function test_the_institutional_faculty_and_its_kardex_exist_once(): void
    {
        $faculty = OrganizationalUnit::query()->where('type', OrganizationalUnitType::FACULTY)->sole();
        $kardex = OrganizationalUnit::query()->where('type', OrganizationalUnitType::KARDEX)->sole();

        $this->assertSame('Facultad de Tecnología', $faculty->name);
        $this->assertSame('Kardex de la Facultad de Tecnología', $kardex->name);
        $this->assertSame($faculty->id, $kardex->parent_id);
        $this->assertTrue($faculty->is_active);
        $this->assertTrue($kardex->is_active);
    }

    public function test_the_institutional_structure_seeder_is_idempotent(): void
    {
        $before = OrganizationalUnit::query()->count();

        $this->seed(InstitutionalStructureSeeder::class);
        $this->seed(InstitutionalStructureSeeder::class);

        $this->assertSame($before, OrganizationalUnit::query()->count());
        $this->assertSame(1, OrganizationalUnit::query()->where('type', OrganizationalUnitType::FACULTY)->count());
        $this->assertSame(1, OrganizationalUnit::query()->where('type', OrganizationalUnitType::KARDEX)->count());
        $this->assertSame(9, OrganizationalUnit::query()->careers()->count());
    }

    public function test_rerunning_the_institutional_seeder_preserves_an_administrator_edited_acronym(): void
    {
        $career = OrganizationalUnit::query()->where('name', 'Mecánica Automotriz')->sole();
        $publicId = $career->public_id;
        $career->update(['acronym' => 'MEC-AUT']);

        $this->seed(InstitutionalStructureSeeder::class);

        $this->assertSame(9, OrganizationalUnit::query()->careers()->count());
        $this->assertSame('MEC-AUT', $career->fresh()->acronym);
        $this->assertSame($publicId, $career->fresh()->public_id);
    }

    public function test_an_authorized_user_can_open_the_units_crud(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $coAdministrator = $this->userWithRole(RoleName::CO_ADMINISTRATOR);

        $this->actingAs($administrator)
            ->get(route('units.index'))
            ->assertOk()
            ->assertSee('Carreras y unidades')
            ->assertSee('Facultad de Tecnología')
            ->assertSee('Kardex de la Facultad de Tecnología');

        $this->actingAs($coAdministrator)
            ->get(route('units.create'))
            ->assertOk()
            ->assertSee('Agregar carrera');
    }

    public function test_a_user_without_unit_permissions_receives_forbidden(): void
    {
        $extra = $this->userWithRole(RoleName::EXTRA);

        $this->actingAs($extra)
            ->get(route('units.index'))
            ->assertForbidden();
    }

    public function test_an_authorized_user_can_create_a_valid_career_with_normalized_data(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        $response = $this->actingAs($administrator)->post(route('units.store'), [
            'name' => '  Carrera de Prueba Institucional  ',
            'acronym' => '  cpi  ',
            'description' => '  Carrera creada para validar el flujo.  ',
            'type' => OrganizationalUnitType::FACULTY->value,
            'parent_id' => null,
            'is_active' => false,
        ]);

        $career = OrganizationalUnit::query()->where('acronym', 'CPI')->sole();

        $response->assertRedirect(route('units.show', $career));
        $this->assertSame('Carrera de Prueba Institucional', $career->name);
        $this->assertSame(OrganizationalUnitType::CAREER, $career->type);
        $this->assertSame($this->faculty()->id, $career->parent_id);
        $this->assertTrue($career->is_active);
    }

    public function test_career_detail_and_edit_views_use_existing_routes(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $career = $this->createCareer('Carrera Visible', 'CV');

        $this->actingAs($administrator)
            ->get(route('units.show', $career))
            ->assertOk()
            ->assertSee('Carrera Visible')
            ->assertSee(route('units.edit', $career), escape: false);

        $this->actingAs($administrator)
            ->get(route('units.edit', $career))
            ->assertOk()
            ->assertSee('Editar carrera')
            ->assertSee(route('units.update', $career), escape: false);
    }

    public function test_a_career_name_cannot_be_duplicated_ignoring_case(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $this->createCareer('Carrera Única', 'CU');

        $this->actingAs($administrator)
            ->from(route('units.create'))
            ->post(route('units.store'), [
                'name' => 'carrera única',
                'acronym' => 'OTRA',
            ])
            ->assertRedirect(route('units.create'))
            ->assertSessionHasErrors('name');
    }

    public function test_a_career_acronym_cannot_be_duplicated_ignoring_case(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $this->createCareer('Primera Carrera', 'SIGLA');

        $this->actingAs($administrator)
            ->from(route('units.create'))
            ->post(route('units.store'), [
                'name' => 'Segunda Carrera',
                'acronym' => 'sigla',
            ])
            ->assertRedirect(route('units.create'))
            ->assertSessionHasErrors('acronym');
    }

    public function test_an_authorized_user_can_edit_a_career_without_changing_its_type_or_parent(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $career = $this->createCareer('Nombre Inicial', 'INI');
        $parentId = $career->parent_id;

        $this->actingAs($administrator)
            ->put(route('units.update', $career), [
                'name' => 'Nombre Actualizado',
                'acronym' => 'act',
                'description' => 'Descripción actualizada.',
                'type' => OrganizationalUnitType::KARDEX->value,
                'parent_id' => null,
            ])
            ->assertRedirect(route('units.show', $career));

        $career->refresh();

        $this->assertSame('Nombre Actualizado', $career->name);
        $this->assertSame('ACT', $career->acronym);
        $this->assertSame(OrganizationalUnitType::CAREER, $career->type);
        $this->assertSame($parentId, $career->parent_id);
    }

    public function test_deactivating_a_career_preserves_the_record_and_history_ready_relations(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $career = $this->createCareer('Carrera Desactivable', 'CD');

        $this->actingAs($administrator)
            ->patch(route('units.deactivate', $career))
            ->assertRedirect();

        $this->assertDatabaseHas('organizational_units', [
            'id' => $career->id,
            'is_active' => false,
        ]);
        $this->assertNotNull(OrganizationalUnit::query()->find($career->id));
    }

    public function test_inactive_careers_are_excluded_from_future_document_destinations(): void
    {
        $activeCareer = $this->createCareer('Carrera Activa', 'CA');
        $inactiveCareer = $this->createCareer('Carrera Inactiva', 'CI', false);

        $availableIds = OrganizationalUnit::query()
            ->availableAsDocumentDestination()
            ->pluck('id');

        $this->assertTrue($availableIds->contains($activeCareer->id));
        $this->assertFalse($availableIds->contains($inactiveCareer->id));
        $this->assertTrue($availableIds->contains(
            OrganizationalUnit::query()->where('type', OrganizationalUnitType::KARDEX)->sole()->id,
        ));
    }

    public function test_faculty_and_kardex_cannot_be_edited_or_deactivated_through_normal_routes(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        foreach ([$this->faculty(), $this->kardex()] as $protectedUnit) {
            $this->actingAs($administrator)
                ->get(route('units.edit', $protectedUnit))
                ->assertForbidden();

            $this->actingAs($administrator)
                ->patch(route('units.deactivate', $protectedUnit))
                ->assertForbidden();
        }

        $this->assertFalse(Route::has('units.destroy'));
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function faculty(): OrganizationalUnit
    {
        return OrganizationalUnit::query()->where('type', OrganizationalUnitType::FACULTY)->sole();
    }

    private function kardex(): OrganizationalUnit
    {
        return OrganizationalUnit::query()->where('type', OrganizationalUnitType::KARDEX)->sole();
    }

    private function createCareer(string $name, string $acronym, bool $active = true): OrganizationalUnit
    {
        return OrganizationalUnit::query()->create([
            'parent_id' => $this->faculty()->id,
            'type' => OrganizationalUnitType::CAREER,
            'name' => $name,
            'acronym' => $acronym,
            'is_active' => $active,
        ]);
    }
}
