<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DocumentTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<int, string>
     */
    private const INITIAL_NAMES = [
        'Carta',
        'Informe',
        'Memorándum',
        'Circular',
        'Solicitud',
        'Resolución',
        'Nota',
        'Formulario',
        'Certificación',
        'Correspondencia',
        'Documento de Kardex',
        'Otros',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AuthorizationSeeder::class,
            DocumentTypeSeeder::class,
        ]);
    }

    public function test_initial_document_types_are_seeded(): void
    {
        $this->assertSame(12, DocumentType::query()->count());

        foreach (self::INITIAL_NAMES as $name) {
            $this->assertDatabaseHas('tipos_documentos', [
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }

    public function test_seeder_is_idempotent_and_preserves_administrative_customizations(): void
    {
        $type = DocumentType::query()->where('code', 'informe')->sole();
        $type->update([
            'name' => 'Informe institucional personalizado',
            'description' => 'Descripción definida por administración.',
            'is_active' => false,
        ]);

        $this->seed(DocumentTypeSeeder::class);
        $this->seed(DocumentTypeSeeder::class);

        $type->refresh();
        $this->assertSame(12, DocumentType::query()->count());
        $this->assertSame('Informe institucional personalizado', $type->name);
        $this->assertSame('Descripción definida por administración.', $type->description);
        $this->assertFalse($type->is_active);
    }

    public function test_administrator_and_co_administrator_can_list_document_types(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $coAdministrator = $this->userWithRole(RoleName::CO_ADMINISTRATOR);

        $this->actingAs($administrator)->get(route('document-types.index'))
            ->assertOk()->assertSee('Carta')->assertSee('Documento de Kardex')->assertSee('Otros');

        $this->actingAs($coAdministrator)->get(route('document-types.index'))
            ->assertOk()->assertSee('Informe')->assertSee('Resolución');
    }

    public function test_user_without_document_type_permission_receives_forbidden(): void
    {
        $extra = $this->userWithRole(RoleName::EXTRA);

        $this->actingAs($extra)->get(route('document-types.index'))->assertForbidden();
        $this->actingAs($extra)->get(route('document-types.create'))->assertForbidden();
    }

    public function test_view_only_permission_does_not_allow_catalog_management(): void
    {
        $careerHead = $this->userWithRole(RoleName::CAREER_HEAD);

        $this->actingAs($careerHead)->get(route('document-types.index'))->assertOk();
        $this->actingAs($careerHead)->get(route('document-types.create'))->assertForbidden();
    }

    public function test_authorized_user_can_create_a_normalized_document_type(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        $response = $this->actingAs($administrator)->post(route('document-types.store'), [
            'name' => '  Acta   Institucional  ',
            'correlative_label' => 'ACTA',
            'description' => '  Documento   para registrar acuerdos.  ',
            'code' => 'codigo_manipulado',
            'is_active' => false,
            'created_by' => 999999,
        ]);

        $type = DocumentType::query()->where('code', 'acta_institucional')->sole();

        $response->assertRedirect(route('document-types.show', $type));
        $this->assertSame('Acta Institucional', $type->name);
        $this->assertSame('Documento para registrar acuerdos.', $type->description);
        $this->assertTrue($type->is_active);
        $this->assertSame($administrator->id, $type->created_by);
    }

    public function test_authorized_user_can_edit_without_changing_stable_code_or_status(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $type = DocumentType::query()->where('code', 'carta')->sole();

        $this->actingAs($administrator)->put(route('document-types.update', $type), [
            'name' => 'Carta institucional',
            'correlative_label' => $type->correlative_label,
            'description' => 'Descripción actualizada.',
            'code' => 'otro_codigo',
            'is_active' => false,
        ])->assertRedirect(route('document-types.show', $type));

        $type->refresh();
        $this->assertSame('Carta institucional', $type->name);
        $this->assertSame('carta', $type->code);
        $this->assertTrue($type->is_active);
    }

    public function test_type_can_be_deactivated_without_deletion_and_reactivated(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $type = DocumentType::query()->where('code', 'circular')->sole();

        $this->actingAs($administrator)->patch(route('document-types.deactivate', $type))->assertRedirect();

        $this->assertDatabaseHas('tipos_documentos', ['id' => $type->id, 'is_active' => false]);
        $this->assertNotNull(DocumentType::query()->find($type->id));

        $this->actingAs($administrator)->patch(route('document-types.activate', $type))->assertRedirect();
        $this->assertDatabaseHas('tipos_documentos', ['id' => $type->id, 'is_active' => true]);
        $this->assertFalse(Route::has('document-types.destroy'));
    }

    public function test_inactive_types_are_excluded_from_available_scope(): void
    {
        $inactive = DocumentType::query()->where('code', 'nota')->sole();
        $inactive->update(['is_active' => false]);

        $available = DocumentType::query()->availableForNewDocuments()->get();

        $this->assertFalse($available->contains($inactive));
        $this->assertTrue($available->contains(
            DocumentType::query()->where('code', 'otros')->sole(),
        ));
    }

    public function test_empty_and_duplicate_names_are_rejected_case_insensitively(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        $this->actingAs($administrator)->post(route('document-types.store'), [
            'name' => '   ',
        ])->assertSessionHasErrors('name');

        $this->actingAs($administrator)->post(route('document-types.store'), [
            'name' => 'resolución',
        ])->assertSessionHasErrors('name');

        $this->assertSame(12, DocumentType::query()->count());
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
