<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionalExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, InstitutionalStructureSeeder::class, DocumentTypeSeeder::class]);
    }

    public function test_all_roles_have_the_same_institutional_shell_without_new_authority(): void
    {
        $career = OrganizationalUnit::query()->where('acronym', 'AERO')->sole();
        $kardex = OrganizationalUnit::query()->where('type', 'kardex')->sole();
        foreach (RoleName::cases() as $role) {
            $unit = $role === RoleName::KARDEX_HEAD ? $kardex : $career;
            $user = User::factory()->create(['organizational_unit_id' => $role->requiresUnit() ? $unit->id : null]);
            $user->assignRole($role->value);
            $response = $this->actingAs($user)->get(route('dashboard'));
            $response->assertOk()->assertSee('Facultad de Tecnología')->assertSee('UMSA')
                ->assertSee('data-workspace-content', false)->assertSee('data-sidebar-open', false)
                ->assertSee('id="contenido-principal"', false)->assertSee($role->label());
            if ($role === RoleName::EXTRA) {
                $response->assertDontSee('href="'.route('users.index').'"', false)
                    ->assertDontSee('href="'.route('documents.create').'"', false);
            }
        }
    }

    public function test_module_entries_have_one_title_breadcrumbs_and_accessible_table_contracts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::ADMINISTRATOR->value);
        foreach (['documents.index', 'users.index', 'units.index', 'document-types.index', 'delegations.index', 'notifications.index', 'reports.documents.index', 'audit.index'] as $route) {
            $response = $this->actingAs($admin)->get(route($route))->assertOk();
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(1, $xpath->query('//h1')->length, $route);
            $this->assertSame(1, $xpath->query('//nav[@aria-label="Ubicación actual"]')->length, $route);
            foreach ($xpath->query('//table') as $table) {
                if (str_contains($table->getAttribute('class'), 'ft-data-table')) {
                    $this->assertSame('table', $table->getAttribute('role'), $route);
                }
                $this->assertSame(1, $xpath->query('caption', $table)->length, $route);
                foreach ($xpath->query('.//td[not(@colspan)]', $table) as $cell) {
                    $this->assertNotSame('', $cell->getAttribute('data-label'), $route);
                }
            }
            $this->assertDescriptionsResolve($xpath);
        }
    }

    public function test_success_is_displayed_once_in_shared_feedback(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::ADMINISTRATOR->value);
        $response = $this->actingAs($admin)->withSession(['status' => 'Comprobación única.'])->get(route('users.index'));
        $response->assertOk()->assertSee('data-status-message', false);
        $this->assertSame(1, substr_count($response->getContent(), 'Comprobación única.'));
    }

    public function test_secondary_search_controls_open_only_when_needed_and_are_not_duplicated(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::ADMINISTRATOR->value);
        foreach ([[[], false], [['sort' => 'created_at', 'direction' => 'desc'], false], [['priority' => 'urgent'], true], [['deadline' => 'overdue'], true], [['direction' => 'asc'], true]] as [$filters, $open]) {
            $response = $this->actingAs($admin)->get(route('documents.index', $filters))->assertOk();
            $xpath = $this->xpath($response->getContent());
            $details = $xpath->query('//details[.//select[@name="priority"]]')->item(0);
            $this->assertNotNull($details);
            $this->assertSame($open, $details->hasAttribute('open'));
            foreach (['priority', 'deadline', 'sort', 'direction'] as $name) {
                $this->assertSame(1, $xpath->query('//select[@name="'.$name.'"]')->length);
            }
            $this->assertSame(0, $xpath->query('.//button[@type="submit"]', $details)->length);
        }
    }

    public function test_form_errors_have_focus_target_and_resolving_field_descriptions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::ADMINISTRATOR->value);
        foreach (['users.create', 'units.create', 'document-types.create', 'documents.create', 'delegations.create'] as $route) {
            $this->actingAs($admin)->from(route($route))->post(route(str_replace('.create', '.store', $route)), [])
                ->assertRedirect(route($route));
            $response = $this->get(route($route))->assertOk();
            $this->assertTrue(str_contains($response->getContent(), 'data-form-errors'), $route.' no muestra el resumen');
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(1, $xpath->query('//*[@data-form-errors and @tabindex="-1" and @role="alert"]')->length, $route);
            $this->assertGreaterThan(0, $xpath->query('//*[@aria-invalid="true"]')->length, $route);
            $this->assertDescriptionsResolve($xpath);
        }
    }

    public function test_login_password_is_hidden_and_never_repopulated(): void
    {
        $response = $this->withSession(['_old_input' => ['password' => 'DO-NOT-RENDER']])->get(route('login'));
        $response->assertOk()->assertSee('data-password-toggle', false)->assertDontSee('DO-NOT-RENDER');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//input[@id="password" and @type="password" and not(@value)]')->length);
        $this->assertSame(1, $xpath->query('//button[@data-password-toggle and @hidden and @aria-controls="password"]')->length);
    }

    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function assertDescriptionsResolve(DOMXPath $xpath): void
    {
        foreach ($xpath->query('//*[@aria-describedby]') as $element) {
            foreach (preg_split('/\s+/', trim($element->getAttribute('aria-describedby'))) as $id) {
                $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length, 'Descripción faltante o duplicada: '.$id);
            }
        }
    }
}
