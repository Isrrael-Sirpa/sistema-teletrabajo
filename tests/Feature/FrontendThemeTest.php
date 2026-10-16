<?php

namespace Tests\Feature;

use App\Enums\OrganizationalUnitType;
use App\Enums\RoleName;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
    }

    public function test_nine_career_acronyms_apply_their_presentation_theme_without_affecting_routes(): void
    {
        $faculty = OrganizationalUnit::query()->create([
            'name' => 'Facultad de Tecnología',
            'acronym' => 'FTEC',
            'type' => OrganizationalUnitType::FACULTY,
            'is_active' => true,
        ]);

        foreach (['AERO', 'CC', 'EI', 'ET', 'EM', 'MA', 'MI', 'QI', 'GTG'] as $acronym) {
            $career = OrganizationalUnit::query()->create([
                'parent_id' => $faculty->id,
                'name' => 'Carrera '.$acronym,
                'acronym' => $acronym,
                'type' => OrganizationalUnitType::CAREER,
                'is_active' => true,
            ]);
            $secretary = User::factory()->create(['organizational_unit_id' => $career->id]);
            $secretary->assignRole(RoleName::SECRETARY->value);

            $this->actingAs($secretary)->get(route('dashboard'))
                ->assertOk()
                ->assertSee('data-career="'.$acronym.'"', false)
                ->assertSee('Carrera de '.$career->name)
                ->assertSee(route('documents.index'), false);
        }

        $styles = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($styles);
        foreach (['AERO', 'CC', 'EI', 'ET', 'EM', 'MA', 'MI', 'QI', 'GTG'] as $acronym) {
            $this->assertStringContainsString("[data-career='".$acronym."']", $styles);
        }
    }

    public function test_global_admin_keeps_institutional_theme_and_extra_has_no_administrative_navigation(): void
    {
        $admin = User::factory()->create(['organizational_unit_id' => null]);
        $admin->assignRole(RoleName::ADMINISTRATOR->value);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-career=""', false)
            ->assertSee(route('users.index'), false);

        $career = OrganizationalUnit::query()->create([
            'name' => 'Carrera de prueba',
            'acronym' => 'PRU',
            'type' => OrganizationalUnitType::CAREER,
            'is_active' => true,
        ]);
        $extra = User::factory()->create(['organizational_unit_id' => $career->id]);
        $extra->assignRole(RoleName::EXTRA->value);

        $this->actingAs($extra)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('users.index'), false)
            ->assertDontSee(route('reports.documents.index'), false)
            ->assertDontSee(route('documents.create'), false);
    }
}
