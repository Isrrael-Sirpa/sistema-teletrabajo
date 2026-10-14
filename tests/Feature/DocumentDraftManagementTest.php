<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentDraftManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AuthorizationSeeder::class, DocumentTypeSeeder::class]);
    }

    public function test_authorized_roles_can_create_drafts_with_safe_initial_values(): void
    {
        $career = $this->career('Ingeniería de Sistemas', 'SIS');

        foreach ([RoleName::ADMINISTRATOR, RoleName::CO_ADMINISTRATOR, RoleName::CAREER_HEAD, RoleName::SECRETARY] as $index => $role) {
            $user = $this->userWithRole($role, $role->isGlobal() ? null : $career);
            $subject = "Borrador autorizado {$index}";

            $response = $this->actingAs($user)->post(route('documents.store'), [
                ...$this->validData($subject),
                'origin_unit_id' => $career->id,
                'general_status' => 'approved',
                'code' => 'CODIGO-MANIPULADO',
                'created_by' => 999999,
                'sender_user_id' => 999999,
                'lock_version' => 99,
            ]);

            $document = Document::query()->where('subject', $subject)->sole();
            $response->assertRedirect(route('documents.show', $document));
            $this->assertSame(DocumentStatus::DRAFT, $document->general_status);
            $this->assertNull($document->code);
            $this->assertNull($document->sender_user_id);
            $this->assertSame($user->id, $document->created_by);
            $this->assertSame($career->id, $document->origin_unit_id);
            $this->assertSame(0, $document->lock_version);
            $this->assertTrue(Str::isUuid($document->public_id));
        }
    }

    public function test_assistant_and_extra_can_open_scoped_inbox_but_cannot_create_drafts(): void
    {
        $career = $this->career('Ingeniería Civil', 'CIV');

        foreach ([RoleName::SECRETARY_ASSISTANT, RoleName::EXTRA] as $role) {
            $user = $this->userWithRole($role, $career);

            $this->actingAs($user)->get(route('documents.index'))->assertOk();
            $this->actingAs($user)->get(route('documents.create'))->assertForbidden();
            $this->actingAs($user)->post(route('documents.store'), $this->validData())->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login_from_document_routes(): void
    {
        $this->get(route('documents.index'))->assertRedirect(route('login'));
        $this->get(route('documents.create'))->assertRedirect(route('login'));
    }

    public function test_creation_validates_active_type_required_fields_and_allowed_values(): void
    {
        $career = $this->career('Ingeniería Industrial', 'IND');
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $inactiveType = DocumentType::query()->where('code', 'nota')->sole();
        $inactiveType->update(['is_active' => false]);

        $this->actingAs($administrator)->post(route('documents.store'), [
            'document_type_id' => $inactiveType->id,
            'origin_unit_id' => $career->id,
            'subject' => '   ',
            'priority' => 'critical',
            'confidentiality' => 'public',
            'due_at' => now()->subDay()->toDateString(),
        ])->assertSessionHasErrors([
            'document_type_id', 'subject', 'priority', 'confidentiality', 'due_at',
        ]);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_global_user_must_select_an_active_origin_unit(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $inactiveCareer = $this->career('Carrera inactiva', 'INA', false);

        $this->actingAs($administrator)->post(route('documents.store'), $this->validData())
            ->assertSessionHasErrors('origin_unit_id');

        $this->actingAs($administrator)->post(route('documents.store'), [
            ...$this->validData(),
            'origin_unit_id' => $inactiveCareer->id,
        ])->assertSessionHasErrors('origin_unit_id');
    }

    public function test_local_origin_is_derived_server_side_and_cannot_be_spoofed(): void
    {
        $ownCareer = $this->career('Ingeniería Ambiental', 'AMB');
        $otherCareer = $this->career('Ingeniería Química', 'QUI');
        $secretary = $this->userWithRole(RoleName::SECRETARY, $ownCareer);

        $this->actingAs($secretary)->post(route('documents.store'), [
            ...$this->validData('Origen protegido'),
            'origin_unit_id' => $otherCareer->id,
        ])->assertRedirect();

        $document = Document::query()->where('subject', 'Origen protegido')->sole();
        $this->assertSame($ownCareer->id, $document->origin_unit_id);

        $head = $this->userWithRole(RoleName::CAREER_HEAD, $ownCareer);
        $this->actingAs($head)->post(route('documents.store'), [
            ...$this->validData('Origen protegido para Jefe'),
            'origin_unit_id' => $otherCareer->id,
        ])->assertRedirect();

        $this->assertSame(
            $ownCareer->id,
            Document::query()->where('subject', 'Origen protegido para Jefe')->sole()->origin_unit_id,
        );
    }

    public function test_local_user_without_an_active_career_cannot_create_a_draft(): void
    {
        $inactiveCareer = $this->career('Ingeniería desactivada', 'DES', false);
        $secretary = $this->userWithRole(RoleName::SECRETARY, $inactiveCareer);

        $this->actingAs($secretary)->post(route('documents.store'), $this->validData())
            ->assertSessionHasErrors('origin_unit_id');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_document_visibility_respects_global_career_and_ownership_scopes(): void
    {
        $careerA = $this->career('Ingeniería Electrónica', 'ELE');
        $careerB = $this->career('Ingeniería Mecánica', 'MEC');
        $headA = $this->userWithRole(RoleName::CAREER_HEAD, $careerA);
        $secretaryA = $this->userWithRole(RoleName::SECRETARY, $careerA);
        $otherSecretaryA = $this->userWithRole(RoleName::SECRETARY, $careerA);
        $secretaryB = $this->userWithRole(RoleName::SECRETARY, $careerB);
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        $own = $this->draft($secretaryA, $careerA, 'Borrador propio');
        $sameUnit = $this->draft($otherSecretaryA, $careerA, 'Borrador de la misma carrera');
        $otherUnit = $this->draft($secretaryB, $careerB, 'Borrador de otra carrera');

        $this->actingAs($administrator)->get(route('documents.index'))
            ->assertOk()->assertSee($own->subject)->assertSee($sameUnit->subject)->assertSee($otherUnit->subject);

        $this->actingAs($headA)->get(route('documents.index'))
            ->assertOk()->assertSee($own->subject)->assertSee($sameUnit->subject)->assertDontSee($otherUnit->subject);

        $this->actingAs($secretaryA)->get(route('documents.index'))
            ->assertOk()->assertSee($own->subject)->assertDontSee($sameUnit->subject)->assertDontSee($otherUnit->subject);

        $this->actingAs($secretaryA)->get(route('documents.show', $sameUnit))->assertForbidden();
        $this->actingAs($headA)->get(route('documents.show', $otherUnit))->assertForbidden();
    }

    public function test_restricted_drafts_use_the_same_scope_and_do_not_expand_access(): void
    {
        $career = $this->career('Ingeniería Geográfica', 'GEO');
        $owner = $this->userWithRole(RoleName::SECRETARY, $career);
        $other = $this->userWithRole(RoleName::SECRETARY, $career);
        $document = $this->draft($owner, $career, 'Borrador reservado', [
            'confidentiality' => DocumentConfidentiality::RESTRICTED,
        ]);

        $this->actingAs($owner)->get(route('documents.show', $document))
            ->assertOk()->assertSee('Acceso restringido');
        $this->actingAs($other)->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_creator_can_update_draft_and_lock_version_increments_atomically(): void
    {
        $career = $this->career('Ingeniería Eléctrica', 'ELC');
        $secretary = $this->userWithRole(RoleName::SECRETARY, $career);
        $document = $this->draft($secretary, $career, 'Asunto inicial');

        $this->actingAs($secretary)->put(route('documents.update', $document), [
            ...$this->validData('Primera edición'),
            'lock_version' => 0,
        ])->assertRedirect(route('documents.show', $document));

        $document->refresh();
        $this->assertSame('Primera edición', $document->subject);
        $this->assertSame(1, $document->lock_version);

        $this->actingAs($secretary)->put(route('documents.update', $document), [
            ...$this->validData('Edición obsoleta'),
            'lock_version' => 0,
        ])->assertSessionHasErrors('lock_version');

        $this->assertSame('Primera edición', $document->fresh()->subject);
        $this->assertSame(1, $document->fresh()->lock_version);
    }

    public function test_due_date_is_optional(): void
    {
        $career = $this->career('Ingeniería Biomédica', 'BIO');
        $secretary = $this->userWithRole(RoleName::SECRETARY, $career);

        $this->actingAs($secretary)->post(route('documents.store'), [
            ...$this->validData('Sin fecha límite'),
            'due_at' => null,
        ])->assertRedirect();

        $this->assertNull(Document::query()->where('subject', 'Sin fecha límite')->sole()->due_at);
    }

    public function test_non_global_user_cannot_edit_another_creators_draft(): void
    {
        $career = $this->career('Ingeniería Petrolera', 'PET');
        $owner = $this->userWithRole(RoleName::SECRETARY, $career);
        $head = $this->userWithRole(RoleName::CAREER_HEAD, $career);
        $document = $this->draft($owner, $career, 'Borrador ajeno');

        $this->actingAs($head)->get(route('documents.edit', $document))->assertForbidden();
        $this->actingAs($head)->put(route('documents.update', $document), [
            ...$this->validData('Intento indebido'),
            'lock_version' => 0,
        ])->assertForbidden();
    }

    public function test_non_draft_document_cannot_be_edited_or_discarded(): void
    {
        $career = $this->career('Ingeniería de Alimentos', 'ALI');
        $secretary = $this->userWithRole(RoleName::SECRETARY, $career);
        $document = $this->draft($secretary, $career, 'Documento enviado');
        $document->forceFill([
            'general_status' => DocumentStatus::SENT,
            'code' => 'DOC-2026-0001',
            'sender_user_id' => $secretary->id,
            'sent_at' => now(),
        ])->save();

        $this->actingAs($secretary)->get(route('documents.edit', $document))->assertForbidden();
        $this->actingAs($secretary)->delete(route('documents.discard', $document))->assertForbidden();
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    public function test_creator_can_logically_discard_only_their_clean_never_sent_draft(): void
    {
        $career = $this->career('Ingeniería Forestal', 'FOR');
        $owner = $this->userWithRole(RoleName::SECRETARY, $career);
        $other = $this->userWithRole(RoleName::SECRETARY, $career);
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $document = $this->draft($owner, $career, 'Borrador descartable');

        $this->actingAs($other)->delete(route('documents.discard', $document))->assertForbidden();
        $this->actingAs($administrator)->delete(route('documents.discard', $document))->assertForbidden();

        $this->actingAs($owner)->delete(route('documents.discard', $document))
            ->assertRedirect(route('documents.index'));

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('document_events', [
            'document_id' => $document->id,
            'event_type' => 'draft_discarded',
            'actor_id' => $owner->id,
        ]);
        $this->actingAs($owner)->get(route('documents.index'))
            ->assertOk()
            ->assertDontSee('Borrador descartable');
        $this->actingAs($owner)->get('/documentos/'.$document->public_id)->assertNotFound();
        $this->assertNotNull(Document::withTrashed()->findOrFail($document->id)->deleted_at);
    }

    public function test_list_is_paginated_and_future_phase_routes_do_not_exist(): void
    {
        $career = $this->career('Ingeniería Agronómica', 'AGR');
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        for ($number = 1; $number <= 16; $number++) {
            $this->draft($administrator, $career, "Borrador {$number}");
        }

        $this->actingAs($administrator)->get(route('documents.index'))
            ->assertOk()
            ->assertViewHas('documents', fn (LengthAwarePaginator $documents): bool => $documents->perPage() === 15 && $documents->count() === 15);

        $this->assertTrue(Route::has('documents.send'));
    }

    /** @return array<string, mixed> */
    private function validData(string $subject = 'Solicitud institucional'): array
    {
        return [
            'document_type_id' => DocumentType::query()->where('code', 'solicitud')->sole()->id,
            'subject' => $subject,
            'description' => "Descripción del borrador.\nSegunda línea.",
            'priority' => DocumentPriority::NORMAL->value,
            'confidentiality' => DocumentConfidentiality::NORMAL->value,
            'due_at' => now()->addWeek()->toDateString(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function draft(User $creator, OrganizationalUnit $origin, string $subject, array $attributes = []): Document
    {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('code', 'informe')->sole()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => $subject,
            'description' => null,
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
            ...$attributes,
        ]);
    }

    private function userWithRole(RoleName $role, ?OrganizationalUnit $unit = null): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit?->id]);
        $user->assignRole($role->value);

        return $user;
    }

    private function career(string $name, string $acronym, bool $active = true): OrganizationalUnit
    {
        return OrganizationalUnit::query()->create([
            'type' => 'career',
            'name' => $name,
            'acronym' => $acronym,
            'is_active' => $active,
        ]);
    }
}
