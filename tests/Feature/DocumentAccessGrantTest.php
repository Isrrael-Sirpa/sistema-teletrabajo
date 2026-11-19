<?php

namespace Tests\Feature;

use App\Enums\DocumentAccessPermission;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentAccessGrant;
use App\Models\DocumentFile;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentAccessGrantService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentAccessGrantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AuthorizationSeeder::class, DocumentTypeSeeder::class]);
        Storage::fake('documentos');
        config(['documents.disk' => 'documentos']);
    }

    public function test_admin_can_grant_view_or_download_and_co_admin_can_grant(): void
    {
        $career = $this->career('Sistemas', 'SIS');
        $creator = $this->user(RoleName::SECRETARY, $career);
        $document = $this->document($creator, $career);
        $viewExtra = $this->user(RoleName::EXTRA, $career);
        $downloadExtra = $this->user(RoleName::EXTRA, $career);
        $coExtra = $this->user(RoleName::EXTRA, $career);
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $coAdmin = $this->user(RoleName::CO_ADMINISTRATOR);

        $this->actingAs($admin)->post(route('documents.access-grants.store', $document), [
            'user_id' => $viewExtra->id,
            'permission' => 'view',
        ])->assertRedirect(route('documents.show', $document));
        $this->actingAs($admin)->post(route('documents.access-grants.store', $document), [
            'user_id' => $downloadExtra->id,
            'permission' => 'download',
        ])->assertRedirect();
        $this->actingAs($coAdmin)->post(route('documents.access-grants.store', $document), [
            'user_id' => $coExtra->id,
            'permission' => 'view',
        ])->assertRedirect();

        $this->assertDatabaseHas('concesiones_acceso_documentos', ['user_id' => $viewExtra->id, 'permission' => 'view']);
        $this->assertDatabaseHas('concesiones_acceso_documentos', ['user_id' => $downloadExtra->id, 'permission' => 'download']);
        $this->assertDatabaseHas('concesiones_acceso_documentos', ['user_id' => $coExtra->id, 'granted_by' => $coAdmin->id]);
    }

    public function test_head_can_grant_only_to_extra_in_own_career_and_related_document(): void
    {
        $own = $this->career('Electrónica', 'ELE');
        $other = $this->career('Mecánica', 'MEC');
        $head = $this->user(RoleName::CAREER_HEAD, $own);
        $creator = $this->user(RoleName::SECRETARY, $own);
        $document = $this->document($creator, $own);
        $ownExtra = $this->user(RoleName::EXTRA, $own);
        $otherExtra = $this->user(RoleName::EXTRA, $other);

        $this->actingAs($head)->post(route('documents.access-grants.store', $document), [
            'user_id' => $ownExtra->id,
            'permission' => 'view',
        ])->assertRedirect();
        $this->actingAs($head)->post(route('documents.access-grants.store', $document), [
            'user_id' => $otherExtra->id,
            'permission' => 'view',
        ])->assertSessionHasErrors('user_id');

        $foreignCreator = $this->user(RoleName::SECRETARY, $other);
        $foreignDocument = $this->document($foreignCreator, $other);
        $this->actingAs($head)->post(route('documents.access-grants.store', $foreignDocument), [
            'user_id' => $ownExtra->id,
            'permission' => 'view',
        ])->assertForbidden();
    }

    public function test_secretary_can_grant_only_on_own_document_and_within_own_career(): void
    {
        $own = $this->career('Industrial', 'IND');
        $other = $this->career('Química', 'QUI');
        $secretary = $this->user(RoleName::SECRETARY, $own);
        $colleague = $this->user(RoleName::SECRETARY, $own);
        $extra = $this->user(RoleName::EXTRA, $own);
        $foreignExtra = $this->user(RoleName::EXTRA, $other);
        $ownDocument = $this->document($secretary, $own);
        $colleagueDocument = $this->document($colleague, $own);

        $this->actingAs($secretary)->post(route('documents.access-grants.store', $ownDocument), [
            'user_id' => $extra->id,
            'permission' => 'view',
        ])->assertRedirect();
        $this->actingAs($secretary)->post(route('documents.access-grants.store', $ownDocument), [
            'user_id' => $foreignExtra->id,
            'permission' => 'view',
        ])->assertSessionHasErrors('user_id');
        $this->actingAs($secretary)->post(route('documents.access-grants.store', $colleagueDocument), [
            'user_id' => $extra->id,
            'permission' => 'view',
        ])->assertForbidden();
    }

    public function test_assistant_and_extra_cannot_manage_grants(): void
    {
        $career = $this->career('Civil', 'CIV');
        $creator = $this->user(RoleName::SECRETARY, $career);
        $document = $this->document($creator, $career);
        $beneficiary = $this->user(RoleName::EXTRA, $career);

        foreach ([RoleName::SECRETARY_ASSISTANT, RoleName::EXTRA] as $role) {
            $actor = $this->user($role, $career);
            $this->actingAs($actor)->post(route('documents.access-grants.store', $document), [
                'user_id' => $beneficiary->id,
                'permission' => 'view',
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('concesiones_acceso_documentos', 0);
    }

    public function test_inactive_user_cannot_receive_a_new_grant(): void
    {
        [$admin, $document, $career] = $this->adminContext();
        $inactive = $this->user(RoleName::EXTRA, $career, false);

        $this->actingAs($admin)->post(route('documents.access-grants.store', $document), [
            'user_id' => $inactive->id,
            'permission' => 'view',
        ])->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('concesiones_acceso_documentos', 0);
    }

    public function test_view_and_download_have_distinct_private_file_authority(): void
    {
        [$admin, $document, $career] = $this->adminContext();
        $viewExtra = $this->user(RoleName::EXTRA, $career);
        $downloadExtra = $this->user(RoleName::EXTRA, $career);
        $file = $this->file($document, $admin);
        $this->grant($admin, $document, $viewExtra, DocumentAccessPermission::VIEW);
        $this->grant($admin, $document, $downloadExtra, DocumentAccessPermission::DOWNLOAD);

        $this->actingAs($viewExtra)->get(route('documents.show', $document))->assertOk();
        $this->actingAs($viewExtra)->get(route('documents.files.download', [$document, $file]))->assertForbidden();
        $this->actingAs($downloadExtra)->get(route('documents.show', $document))->assertOk();
        $this->actingAs($downloadExtra)->get(route('documents.files.download', [$document, $file]))->assertDownload('informe.pdf');
    }

    public function test_absent_expired_and_revoked_grants_do_not_provide_visibility(): void
    {
        [$admin, $document, $career] = $this->adminContext();
        $without = $this->user(RoleName::EXTRA, $career);
        $expired = $this->user(RoleName::EXTRA, $career);
        $revoked = $this->user(RoleName::EXTRA, $career);
        $this->rawGrant($document, $expired, $admin, 'view', now()->subHour());
        $revokedGrant = $this->rawGrant($document, $revoked, $admin);
        $revokedGrant->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $admin->id,
            'revocation_reason' => 'Acceso ya no requerido.',
        ])->save();

        foreach ([$without, $expired, $revoked] as $extra) {
            $this->actingAs($extra)->get(route('documents.show', $document))->assertForbidden();
            $this->assertFalse(Document::query()->visibleTo($extra)->whereKey($document->id)->exists());
        }
    }

    public function test_revocation_preserves_the_historical_record_and_reason(): void
    {
        [$admin, $document, $career] = $this->adminContext();
        $extra = $this->user(RoleName::EXTRA, $career);
        $grant = $this->grant($admin, $document, $extra, DocumentAccessPermission::VIEW);

        $this->actingAs($admin)->delete(route('documents.access-grants.destroy', [$document, $grant]), [
            'revocation_reason' => 'Finalizó la necesidad institucional.',
        ])->assertRedirect();

        $grant->refresh();
        $this->assertNotNull($grant->revoked_at);
        $this->assertSame($admin->id, $grant->revoked_by);
        $this->assertSame('Finalizó la necesidad institucional.', $grant->revocation_reason);
        $this->assertDatabaseCount('concesiones_acceso_documentos', 1);
        $this->actingAs($extra)->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_restricted_document_is_visible_only_to_the_explicitly_granted_extra(): void
    {
        [$admin, , $career, $creator] = $this->adminContext();
        $document = $this->document($creator, $career, DocumentConfidentiality::RESTRICTED);
        $granted = $this->user(RoleName::EXTRA, $career);
        $other = $this->user(RoleName::EXTRA, $career);
        $this->grant($admin, $document, $granted, DocumentAccessPermission::VIEW);

        $this->actingAs($granted)->get(route('documents.show', $document))->assertOk()->assertSee('Acceso restringido');
        $this->actingAs($other)->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_changing_access_level_creates_a_new_cycle_in_both_directions(): void
    {
        [$admin, $document, $career] = $this->adminContext();
        $extra = $this->user(RoleName::EXTRA, $career);
        $view = $this->grant($admin, $document, $extra, DocumentAccessPermission::VIEW);

        $this->actingAs($admin)->patch(route('documents.access-grants.update', [$document, $view]), [
            'permission' => 'download',
        ])->assertRedirect();
        $view->refresh();
        $download = DocumentAccessGrant::query()->effective()->sole();
        $this->assertNotNull($view->revoked_at);
        $this->assertSame(DocumentAccessPermission::DOWNLOAD, $download->permission);

        $this->actingAs($admin)->patch(route('documents.access-grants.update', [$document, $download]), [
            'permission' => 'view',
        ])->assertRedirect();
        $this->assertDatabaseCount('concesiones_acceso_documentos', 3);
        $this->assertSame(DocumentAccessPermission::VIEW, DocumentAccessGrant::query()->effective()->sole()->permission);
    }

    public function test_active_duplicate_is_rejected_without_creating_another_row(): void
    {
        [$admin, $document, $career] = $this->adminContext();
        $extra = $this->user(RoleName::EXTRA, $career);
        $this->grant($admin, $document, $extra, DocumentAccessPermission::VIEW);

        $this->actingAs($admin)->post(route('documents.access-grants.store', $document), [
            'user_id' => $extra->id,
            'permission' => 'download',
        ])->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('concesiones_acceso_documentos', 1);
    }

    public function test_discarded_document_cannot_be_revived_by_a_grant(): void
    {
        [$admin, $document, $career] = $this->adminContext();
        $extra = $this->user(RoleName::EXTRA, $career);
        $grant = $this->grant($admin, $document, $extra, DocumentAccessPermission::VIEW);
        $document->delete();

        $this->actingAs($extra)->get('/documentos/'.$document->public_id)->assertNotFound();
        $this->actingAs($admin)->post('/documentos/'.$document->public_id.'/accesos', [
            'user_id' => $extra->id,
            'permission' => 'download',
        ])->assertNotFound();
        $this->assertFalse(Document::query()->visibleTo($extra)->whereKey($document->id)->exists());
        $this->assertDatabaseHas('concesiones_acceso_documentos', ['id' => $grant->id]);
    }

    public function test_grant_from_another_document_cannot_be_changed_or_revoked(): void
    {
        [$admin, $documentA, $career, $creator] = $this->adminContext();
        $documentB = $this->document($creator, $career);
        $extra = $this->user(RoleName::EXTRA, $career);
        $grant = $this->grant($admin, $documentA, $extra, DocumentAccessPermission::VIEW);

        $this->actingAs($admin)->patch(route('documents.access-grants.update', [$documentB, $grant]), [
            'permission' => 'download',
        ])->assertForbidden();
        $this->actingAs($admin)->delete(route('documents.access-grants.destroy', [$documentB, $grant]), [
            'revocation_reason' => 'Intento cruzado',
        ])->assertForbidden();
        $this->assertNull($grant->fresh()->revoked_at);
    }

    public function test_grant_never_turns_extra_into_a_workflow_participant(): void
    {
        [$admin, $document, $career] = $this->adminContext();
        $extra = $this->user(RoleName::EXTRA, $career);
        $this->grant($admin, $document, $extra, DocumentAccessPermission::DOWNLOAD);

        $this->assertTrue(Gate::forUser($extra)->allows('view', $document));
        $this->assertTrue(Gate::forUser($extra)->allows('downloadFile', $document));
        $this->assertFalse(Gate::forUser($extra)->allows('update', $document));
        $this->assertFalse(Gate::forUser($extra)->allows('send', $document));
        $this->assertFalse(Gate::forUser($extra)->allows('manageRecipients', $document));
        $this->assertFalse(Gate::forUser($extra)->allows('manageFiles', $document));
        $this->assertDatabaseCount('destinatarios_documentos', 0);
    }

    public function test_detail_selector_shows_only_eligible_extras_for_local_manager(): void
    {
        $career = $this->career('Ambiental', 'AMB');
        $other = $this->career('Alimentos', 'ALI');
        $secretary = $this->user(RoleName::SECRETARY, $career);
        $document = $this->document($secretary, $career);
        $eligible = $this->user(RoleName::EXTRA, $career);
        $foreign = $this->user(RoleName::EXTRA, $other);
        $inactive = $this->user(RoleName::EXTRA, $career, false);

        $this->actingAs($secretary)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('Accesos explícitos')
            ->assertSee($eligible->name)
            ->assertDontSee($foreign->name)
            ->assertDontSee($inactive->name);
    }

    /** @return array{User, Document, OrganizationalUnit, User} */
    private function adminContext(): array
    {
        $career = $this->career('Gestión', 'GES');
        $creator = $this->user(RoleName::SECRETARY, $career);

        return [$this->user(RoleName::ADMINISTRATOR), $this->document($creator, $career), $career, $creator];
    }

    private function career(string $name, string $acronym): OrganizationalUnit
    {
        return OrganizationalUnit::query()->create([
            'type' => 'career',
            'name' => $name.' '.Str::random(5),
            'acronym' => $acronym.Str::upper(Str::random(3)),
            'is_active' => true,
        ]);
    }

    private function user(RoleName $role, ?OrganizationalUnit $unit = null, bool $active = true): User
    {
        $user = User::factory()->create([
            'organizational_unit_id' => $unit?->id,
            'status' => $active ? 'active' : 'inactive',
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function document(
        User $creator,
        OrganizationalUnit $origin,
        DocumentConfidentiality $confidentiality = DocumentConfidentiality::NORMAL,
    ): Document {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => 'Documento para acceso '.Str::random(8),
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => $confidentiality,
            'lock_version' => 0,
        ]);
    }

    private function grant(
        User $actor,
        Document $document,
        User $extra,
        DocumentAccessPermission $permission,
    ): DocumentAccessGrant {
        return app(DocumentAccessGrantService::class)->grant($actor, $document, [
            'user_id' => $extra->id,
            'permission' => $permission->value,
            'ends_at' => null,
        ]);
    }

    private function rawGrant(
        Document $document,
        User $extra,
        User $grantor,
        string $permission = 'view',
        $endsAt = null,
    ): DocumentAccessGrant {
        return DocumentAccessGrant::query()->create([
            'document_id' => $document->id,
            'user_id' => $extra->id,
            'granted_by' => $grantor->id,
            'permission' => $permission,
            'starts_at' => now()->subDay(),
            'ends_at' => $endsAt,
            'reason' => 'Prueba de acceso explícito.',
        ]);
    }

    private function file(Document $document, User $uploader): DocumentFile
    {
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'uploaded_by' => $uploader->id,
        ]);
        Storage::disk('documentos')->put('tests/informe.pdf', 'contenido');

        return DocumentFile::query()->create([
            'document_version_id' => $version->id,
            'disk' => 'documentos',
            'path' => 'tests/informe.pdf',
            'original_name' => 'informe.pdf',
            'stored_name' => 'informe.pdf',
            'extension' => 'pdf',
            'detected_mime_type' => 'application/pdf',
            'size_bytes' => 9,
            'sha256' => hash('sha256', 'contenido'),
            'uploaded_by' => $uploader->id,
            'created_at' => now(),
        ]);
    }
}
