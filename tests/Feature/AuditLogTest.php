<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\DocumentAccessPermission;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DocumentAccessGrantService;
use App\Services\DocumentTypeService;
use App\Services\DocumentWorkflowService;
use App\Services\OrganizationalUnitService;
use App\Services\UserManagementService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private OrganizationalUnit $career;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AuthorizationSeeder::class,
            InstitutionalStructureSeeder::class,
            DocumentTypeSeeder::class,
        ]);

        $this->admin = $this->user(RoleName::ADMINISTRATOR);
        $this->career = $this->career('Auditoría', 'AUD');
    }

    public function test_successful_failed_inactive_login_and_logout_are_audited_without_credentials(): void
    {
        $active = User::factory()->create([
            'email' => 'activo@example.test',
            'password' => 'Clave-Segura-123!',
        ]);
        $inactive = User::factory()->inactive()->create([
            'email' => 'inactivo@example.test',
            'password' => 'Clave-Segura-123!',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.44'])
            ->post(route('login.store'), ['email' => 'ACTIVO@example.test', 'password' => 'Clave-Segura-123!'])
            ->assertRedirect(route('dashboard'));
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), ['email' => 'nadie@example.test', 'password' => 'No-es-la-clave'])
            ->assertSessionHasErrors('email');
        $this->post(route('login.store'), ['email' => $inactive->email, 'password' => 'Clave-Segura-123!'])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $active->id,
            'action' => AuditAction::AUTH_LOGIN_SUCCEEDED->value,
            'ip_address' => '192.0.2.44',
        ]);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $active->id, 'action' => AuditAction::AUTH_LOGOUT->value]);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => null, 'action' => AuditAction::AUTH_LOGIN_FAILED->value]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => null,
            'action' => AuditAction::AUTH_LOGIN_DENIED_INACTIVE->value,
            'auditable_id' => $inactive->id,
        ]);

        $serialized = AuditLog::query()->get()->toJson();
        $this->assertStringNotContainsString('Clave-Segura-123!', $serialized);
        $this->assertStringNotContainsString('No-es-la-clave', $serialized);
    }

    public function test_user_creation_edit_status_role_unit_and_password_reset_are_audited(): void
    {
        $service = app(UserManagementService::class);
        $target = $service->create($this->admin, [
            'name' => 'Usuaria auditada',
            'email' => 'auditada@example.test',
            'password' => 'Clave-Segura-123!',
            'status' => 'active',
            'role' => RoleName::SECRETARY->value,
            'organizational_unit_id' => $this->career->id,
        ]);
        $otherCareer = $this->career('Cambio', 'CAM');

        $service->update($this->admin, $target, [
            'name' => 'Usuaria actualizada',
            'email' => 'actualizada@example.test',
            'status' => 'active',
            'role' => RoleName::SECRETARY_ASSISTANT->value,
            'organizational_unit_id' => $otherCareer->id,
        ]);
        $service->setActive($this->admin, $target->fresh(), false);
        $service->setActive($this->admin, $target->fresh(), true);
        $service->resetPassword($this->admin, $target->fresh(), 'Otra-Clave-456!');

        foreach ([
            AuditAction::USER_CREATED,
            AuditAction::USER_UPDATED,
            AuditAction::USER_ROLE_CHANGED,
            AuditAction::USER_UNIT_CHANGED,
            AuditAction::USER_DEACTIVATED,
            AuditAction::USER_ACTIVATED,
            AuditAction::USER_PASSWORD_RESET,
        ] as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'actor_id' => $this->admin->id,
                'action' => $action->value,
                'auditable_id' => $target->id,
            ]);
        }

        $updated = AuditLog::query()->where('action', AuditAction::USER_UPDATED->value)->sole();
        $this->assertSame(RoleName::SECRETARY->value, $updated->old_values['role']);
        $this->assertSame(RoleName::SECRETARY_ASSISTANT->value, $updated->new_values['role']);
        $this->assertSame($this->career->id, $updated->old_values['organizational_unit_id']);
        $this->assertSame($otherCareer->id, $updated->new_values['organizational_unit_id']);

        $passwordReset = AuditLog::query()->where('action', AuditAction::USER_PASSWORD_RESET->value)->sole();
        $this->assertNull($passwordReset->old_values);
        $this->assertNull($passwordReset->new_values);
        $this->assertStringNotContainsString('Otra-Clave-456!', $passwordReset->toJson());
    }

    public function test_career_and_document_type_administration_are_audited_in_their_transactions(): void
    {
        $units = app(OrganizationalUnitService::class);
        $unit = $units->createCareer($this->admin, [
            'name' => 'Carrera Registrada',
            'acronym' => 'CRG',
            'description' => 'Inicial',
        ]);
        $units->updateCareer($this->admin, $unit, [
            'name' => 'Carrera Editada',
            'acronym' => 'CRE',
            'description' => 'Actualizada',
        ]);
        $units->setCareerActive($this->admin, $unit->fresh(), false);
        $units->setCareerActive($this->admin, $unit->fresh(), true);

        $types = app(DocumentTypeService::class);
        $type = $types->create($this->admin, ['name' => 'Tipo auditable', 'description' => 'Inicial']);
        $types->update($this->admin, $type, ['name' => 'Tipo auditable editado', 'description' => 'Nuevo']);
        $types->setActive($this->admin, $type->fresh(), false);
        $types->setActive($this->admin, $type->fresh(), true);

        foreach ([
            AuditAction::UNIT_CREATED,
            AuditAction::UNIT_UPDATED,
            AuditAction::UNIT_DEACTIVATED,
            AuditAction::UNIT_ACTIVATED,
            AuditAction::DOCUMENT_TYPE_CREATED,
            AuditAction::DOCUMENT_TYPE_UPDATED,
            AuditAction::DOCUMENT_TYPE_DEACTIVATED,
            AuditAction::DOCUMENT_TYPE_ACTIVATED,
        ] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action->value, 'actor_id' => $this->admin->id]);
        }
    }

    public function test_official_send_is_audited_but_ordinary_workflow_transitions_are_not_duplicated(): void
    {
        $secretary = $this->user(RoleName::SECRETARY, $this->career);
        $document = $this->document($secretary, DocumentConfidentiality::NORMAL);
        DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $this->career->id,
            'user_id' => null,
            'requires_response' => true,
            'status' => 'prepared',
            'lock_version' => 0,
        ]);

        app(DocumentWorkflowService::class)->send($secretary, $document, 0);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $secretary->id,
            'action' => AuditAction::DOCUMENT_SENT->value,
            'auditable_id' => $document->id,
        ]);
        $this->assertSame(1, AuditLog::query()->where('auditable_type', Document::class)->count());
    }

    public function test_private_download_is_audited_without_storage_paths_or_restricted_content(): void
    {
        Storage::fake('documents');
        $document = $this->document($this->admin, DocumentConfidentiality::RESTRICTED, 'ASUNTO-MUY-SECRETO');
        $file = $this->file($document, $this->admin, 'ruta/interna/secreta.pdf', 'nombre-secreto.pdf');
        Storage::disk('documents')->put($file->path, 'contenido');

        $this->actingAs($this->admin)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.18'])
            ->get(route('documents.files.download', [$document, $file]))
            ->assertOk();

        $log = AuditLog::query()->where('action', AuditAction::FILE_DOWNLOADED->value)->sole();
        $this->assertSame('198.51.100.18', $log->ip_address);
        $this->assertSame('restricted', $log->new_values['confidentiality']);
        $serialized = $log->toJson();
        $this->assertStringNotContainsString('ASUNTO-MUY-SECRETO', $serialized);
        $this->assertStringNotContainsString('ruta/interna', $serialized);
        $this->assertStringNotContainsString('nombre-secreto.pdf', $serialized);
    }

    public function test_access_grant_creation_level_change_and_revocation_are_audited(): void
    {
        $extra = $this->user(RoleName::EXTRA, $this->career);
        $document = $this->document($this->admin, DocumentConfidentiality::RESTRICTED);
        $service = app(DocumentAccessGrantService::class);

        $grant = $service->grant($this->admin, $document, [
            'user_id' => $extra->id,
            'permission' => DocumentAccessPermission::VIEW->value,
            'ends_at' => null,
        ]);
        $replacement = $service->changeLevel($this->admin, $document, $grant, DocumentAccessPermission::DOWNLOAD);
        $service->revoke($this->admin, $document, $replacement, 'Fin de la necesidad operativa.');

        foreach ([
            AuditAction::ACCESS_GRANT_CREATED,
            AuditAction::ACCESS_GRANT_LEVEL_CHANGED,
            AuditAction::ACCESS_GRANT_REVOKED,
        ] as $action) {
            $this->assertDatabaseHas('audit_logs', ['actor_id' => $this->admin->id, 'action' => $action->value]);
        }

        $change = AuditLog::query()->where('action', AuditAction::ACCESS_GRANT_LEVEL_CHANGED->value)->sole();
        $this->assertSame('view', $change->old_values['permission']);
        $this->assertSame('download', $change->new_values['permission']);
        $this->assertStringNotContainsString($document->subject, $change->toJson());
    }

    public function test_rolled_back_transaction_does_not_keep_success_audit_log(): void
    {
        try {
            DB::transaction(function (): void {
                app(AuditLogService::class)->record($this->admin, AuditAction::USER_UPDATED, $this->admin);
                throw new RuntimeException('Forzar rollback');
            });
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertDatabaseMissing('audit_logs', ['action' => AuditAction::USER_UPDATED->value]);
    }

    public function test_only_administrator_and_co_administrator_can_view_audit_module(): void
    {
        $this->actingAs($this->admin)->get(route('audit.index'))->assertOk();
        $this->actingAs($this->user(RoleName::CO_ADMINISTRATOR))->get(route('audit.index'))->assertOk();

        foreach ([RoleName::CAREER_HEAD, RoleName::SECRETARY, RoleName::SECRETARY_ASSISTANT, RoleName::EXTRA] as $role) {
            $this->actingAs($this->user($role, $this->career))->get(route('audit.index'))->assertForbidden();
        }
    }

    public function test_filters_and_configurable_pagination_work_without_n_plus_one_actor_loading(): void
    {
        config(['audit.per_page' => 2]);
        $audit = app(AuditLogService::class);
        $audit->record($this->admin, AuditAction::USER_UPDATED, $this->admin);
        $audit->record($this->admin, AuditAction::AUTH_LOGIN_FAILED);
        $audit->record($this->admin, AuditAction::AUTH_LOGIN_DENIED_INACTIVE, $this->admin);

        $this->actingAs($this->admin)
            ->get(route('audit.index', [
                'actor' => $this->admin->id,
                'action' => AuditAction::USER_UPDATED->value,
                'resource' => 'user',
                'result' => 'success',
                'from' => now()->format('Y-m-d'),
                'to' => now()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1);

        $this->actingAs($this->admin)->get(route('audit.index'))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->perPage() === 2 && $logs->hasPages());
    }

    public function test_central_sanitizer_excludes_passwords_tokens_secrets_sessions_and_binary_data(): void
    {
        $log = app(AuditLogService::class)->record(
            $this->admin,
            AuditAction::USER_UPDATED,
            $this->admin,
            newValues: [
                'safe' => 'visible',
                'password' => 'oculta',
                'password_confirmation' => 'oculta',
                'remember_token' => 'oculto',
                'api_token' => 'oculto',
                'client_secret' => 'oculto',
                'authorization_header' => 'oculto',
                'cookies' => 'oculto',
                'session_id' => 'oculto',
            ],
        );

        $this->assertSame(['safe' => 'visible'], $log->new_values);
    }

    public function test_audit_logs_cannot_be_updated_or_deleted_through_eloquent(): void
    {
        $log = app(AuditLogService::class)->record($this->admin, AuditAction::USER_UPDATED, $this->admin);

        try {
            $log->update(['action' => AuditAction::USER_CREATED]);
            $this->fail('El registro de auditoría pudo editarse.');
        } catch (LogicException $exception) {
            $this->assertSame('Los registros de auditoría son inmutables.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    private function user(RoleName $role, ?OrganizationalUnit $unit = null): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit?->id]);
        $user->assignRole($role->value);

        return $user;
    }

    private function career(string $name, string $acronym): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();

        return OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => $name,
            'acronym' => $acronym,
            'is_active' => true,
        ]);
    }

    private function document(
        User $creator,
        DocumentConfidentiality $confidentiality,
        string $subject = 'Documento auditable',
    ): Document {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $this->career->id,
            'created_by' => $creator->id,
            'subject' => $subject,
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => $confidentiality,
            'lock_version' => 0,
        ]);
    }

    private function file(Document $document, User $uploader, string $path, string $name): DocumentFile
    {
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'uploaded_by' => $uploader->id,
        ]);

        return DocumentFile::query()->create([
            'document_version_id' => $version->id,
            'disk' => 'documents',
            'path' => $path,
            'original_name' => $name,
            'stored_name' => 'interno.pdf',
            'extension' => 'pdf',
            'detected_mime_type' => 'application/pdf',
            'size_bytes' => 9,
            'sha256' => hash('sha256', 'contenido'),
            'uploaded_by' => $uploader->id,
            'created_at' => now(),
        ]);
    }
}
