<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentRecipientManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AuthorizationSeeder::class,
            InstitutionalStructureSeeder::class,
            DocumentTypeSeeder::class,
        ]);
    }

    public function test_creator_can_add_same_origin_unit_without_sending_document(): void
    {
        $career = $this->career('Ingeniería de Sistemas', 'SIS');
        $creator = $this->userWithRole(RoleName::SECRETARY, $career);
        $document = $this->draft($creator, $career);

        $this->actingAs($creator)->post(route('documents.recipients.store', $document), [
            'recipient_type' => 'unit',
            'unit_id' => $career->id,
            'requires_response' => true,
            'lock_version' => 0,
        ])->assertRedirect()->assertSessionHas('status');

        $recipient = DocumentRecipient::query()->sole();
        $document->refresh();

        $this->assertSame($career->id, $recipient->unit_id);
        $this->assertNull($recipient->user_id);
        $this->assertTrue($recipient->requires_response);
        $this->assertSame(DocumentRecipientStatus::PREPARED, $recipient->status);
        $this->assertSame(DocumentStatus::DRAFT, $document->general_status);
        $this->assertNull($document->code);
        $this->assertNull($document->sent_at);
        $this->assertSame(1, $document->lock_version);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseHas('document_events', [
            'document_id' => $document->id,
            'event_type' => 'recipient_added',
            'actor_id' => $creator->id,
        ]);
    }

    public function test_creator_can_add_person_and_unit_is_derived_from_that_user(): void
    {
        $origin = $this->career('Ingeniería Civil', 'CIV');
        $targetUnit = $this->career('Ingeniería Industrial', 'IND');
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $target = $this->userWithRole(RoleName::CAREER_HEAD, $targetUnit);
        $document = $this->draft($creator, $origin);

        $this->actingAs($creator)->post(route('documents.recipients.store', $document), [
            'recipient_type' => 'person',
            'user_id' => $target->id,
            'unit_id' => $origin->id,
            'requires_response' => true,
            'lock_version' => 0,
        ])->assertRedirect();

        $recipient = DocumentRecipient::query()->sole();
        $this->assertSame($target->id, $recipient->user_id);
        $this->assertSame($targetUnit->id, $recipient->unit_id);
    }

    public function test_secretary_can_be_a_personal_recipient_that_requires_response(): void
    {
        $origin = $this->career('Ingeniería Comercial', 'COM');
        $destination = $this->career('Ingeniería de Producción', 'PRO');
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $secretary = $this->userWithRole(RoleName::SECRETARY, $destination);
        $document = $this->draft($creator, $origin);

        $this->actingAs($creator)->post(
            route('documents.recipients.store', $document),
            $this->personPayload($secretary, true, 0),
        )->assertRedirect();

        $this->assertDatabaseHas('document_recipients', [
            'document_id' => $document->id,
            'unit_id' => $destination->id,
            'user_id' => $secretary->id,
            'requires_response' => true,
        ]);
    }

    public function test_assistant_is_only_allowed_as_a_personal_informational_copy(): void
    {
        $origin = $this->career('Ingeniería de Materiales', 'MAT');
        $destination = $this->career('Ingeniería de Procesos', 'PRC');
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $assistant = $this->userWithRole(RoleName::SECRETARY_ASSISTANT, $destination);
        $document = $this->draft($creator, $origin);

        $this->actingAs($creator)->post(
            route('documents.recipients.store', $document),
            $this->personPayload($assistant, true, 0),
        )->assertSessionHasErrors('requires_response');

        $this->assertDatabaseCount('document_recipients', 0);
        $this->assertSame(0, $document->fresh()->lock_version);

        $this->actingAs($creator)->post(
            route('documents.recipients.store', $document),
            $this->personPayload($assistant, false, 0),
        )->assertRedirect();

        $recipient = DocumentRecipient::query()->sole();
        $this->assertFalse($recipient->requires_response);

        $this->actingAs($creator)->patch(route('documents.recipients.update', $document), [
            'recipient_id' => $recipient->id,
            'requires_response' => true,
            'lock_version' => 1,
        ])->assertSessionHasErrors('requires_response');

        $this->assertFalse($recipient->fresh()->requires_response);
        $this->assertSame(1, $document->fresh()->lock_version);
    }

    public function test_extra_is_excluded_from_selector_and_rejected_by_backend(): void
    {
        $origin = $this->career('Ingeniería Sanitaria', 'SAN');
        $destination = $this->career('Ingeniería Energética', 'ENE');
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $assistant = $this->userWithRole(RoleName::SECRETARY_ASSISTANT, $destination);
        $extra = $this->userWithRole(RoleName::EXTRA, $destination);
        $document = $this->draft($creator, $origin);

        $this->actingAs($creator)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee($assistant->name)
            ->assertDontSee($extra->name);

        $this->actingAs($creator)->post(
            route('documents.recipients.store', $document),
            $this->personPayload($extra, false, 0),
        )->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('document_recipients', 0);
        $this->assertSame(0, $document->fresh()->lock_version);
    }

    public function test_creator_can_add_multiple_mixed_recipients_and_kardex(): void
    {
        $origin = $this->career('Ingeniería Ambiental', 'AMB');
        $secondCareer = $this->career('Ingeniería Química', 'QUI');
        $kardex = OrganizationalUnit::query()->where('type', 'kardex')->sole();
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $person = $this->userWithRole(RoleName::SECRETARY, $secondCareer);
        $document = $this->draft($creator, $origin);

        $this->actingAs($creator)->post(route('documents.recipients.store', $document), $this->unitPayload($origin, true, 0))->assertRedirect();
        $this->actingAs($creator)->post(route('documents.recipients.store', $document), $this->unitPayload($secondCareer, true, 1))->assertRedirect();
        $this->actingAs($creator)->post(route('documents.recipients.store', $document), $this->unitPayload($kardex, false, 2))->assertRedirect();
        $this->actingAs($creator)->post(route('documents.recipients.store', $document), [
            'recipient_type' => 'person',
            'user_id' => $person->id,
            'requires_response' => false,
            'lock_version' => 3,
        ])->assertRedirect();

        $this->assertDatabaseCount('document_recipients', 4);
        $this->assertDatabaseHas('document_recipients', [
            'document_id' => $document->id,
            'unit_id' => $kardex->id,
            'user_id' => null,
            'requires_response' => false,
            'status' => 'prepared',
        ]);
        $this->assertSame(4, $document->fresh()->lock_version);
    }

    public function test_inactive_unit_and_faculty_are_rejected_as_destinations(): void
    {
        $origin = $this->career('Ingeniería Mecánica', 'MEC');
        $inactive = $this->career('Carrera inactiva', 'INA', false);
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->sole();
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $document = $this->draft($creator, $origin);

        $this->actingAs($creator)->post(route('documents.recipients.store', $document), $this->unitPayload($inactive, true, 0))
            ->assertSessionHasErrors('unit_id');
        $this->actingAs($creator)->post(route('documents.recipients.store', $document), $this->unitPayload($faculty, true, 0))
            ->assertSessionHasErrors('unit_id');

        $this->assertDatabaseCount('document_recipients', 0);
        $this->assertSame(0, $document->fresh()->lock_version);
    }

    public function test_inactive_or_global_user_without_unit_is_rejected_as_personal_recipient(): void
    {
        $career = $this->career('Ingeniería Eléctrica', 'ELC');
        $creator = $this->userWithRole(RoleName::SECRETARY, $career);
        $inactive = User::factory()->inactive()->create(['organizational_unit_id' => $career->id]);
        $global = $this->userWithRole(RoleName::ADMINISTRATOR);
        $document = $this->draft($creator, $career);

        foreach ([$inactive, $global] as $target) {
            $this->actingAs($creator)->post(route('documents.recipients.store', $document), [
                'recipient_type' => 'person',
                'user_id' => $target->id,
                'requires_response' => true,
                'lock_version' => 0,
            ])->assertSessionHasErrors('user_id');
        }

        $this->assertDatabaseCount('document_recipients', 0);
    }

    public function test_extra_and_unrelated_user_cannot_manage_recipients(): void
    {
        $career = $this->career('Ingeniería Petrolera', 'PET');
        $creator = $this->userWithRole(RoleName::SECRETARY, $career);
        $unrelated = $this->userWithRole(RoleName::SECRETARY, $career);
        $extra = $this->userWithRole(RoleName::EXTRA, $career);
        $document = $this->draft($creator, $career);
        $payload = $this->unitPayload($career, true, 0);

        $this->actingAs($unrelated)->post(route('documents.recipients.store', $document), $payload)->assertForbidden();
        $this->actingAs($extra)->post(route('documents.recipients.store', $document), $payload)->assertForbidden();
        $this->assertDatabaseCount('document_recipients', 0);
    }

    public function test_global_administrators_can_manage_recipients_of_supervised_draft(): void
    {
        $origin = $this->career('Ingeniería Textil', 'TEX');
        $targetA = $this->career('Destino global A', 'DGA');
        $targetB = $this->career('Destino global B', 'DGB');
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $coAdministrator = $this->userWithRole(RoleName::CO_ADMINISTRATOR);
        $document = $this->draft($creator, $origin);

        $this->actingAs($administrator)->post(
            route('documents.recipients.store', $document),
            $this->unitPayload($targetA, true, 0),
        )->assertRedirect();

        $this->actingAs($coAdministrator)->post(
            route('documents.recipients.store', $document),
            $this->unitPayload($targetB, false, 1),
        )->assertRedirect();

        $this->assertDatabaseCount('document_recipients', 2);
        $this->assertSame(2, $document->fresh()->lock_version);
    }

    public function test_duplicate_unit_and_person_targets_are_rejected_without_incrementing_version(): void
    {
        $origin = $this->career('Ingeniería Forestal', 'FOR');
        $targetUnit = $this->career('Ingeniería Agronómica', 'AGR');
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $person = $this->userWithRole(RoleName::SECRETARY, $targetUnit);
        $document = $this->draft($creator, $origin);

        $this->actingAs($creator)->post(route('documents.recipients.store', $document), $this->unitPayload($targetUnit, true, 0))->assertRedirect();
        $this->actingAs($creator)->post(route('documents.recipients.store', $document), $this->unitPayload($targetUnit, false, 1))->assertSessionHasErrors('recipient');

        $this->actingAs($creator)->post(route('documents.recipients.store', $document), $this->personPayload($person, true, 1))->assertRedirect();
        $this->actingAs($creator)->post(route('documents.recipients.store', $document), $this->personPayload($person, false, 2))->assertSessionHasErrors('recipient');

        $this->assertDatabaseCount('document_recipients', 2);
        $this->assertSame(2, $document->fresh()->lock_version);
    }

    public function test_response_requirement_can_be_updated_for_prepared_recipient(): void
    {
        $career = $this->career('Ingeniería Geográfica', 'GEO');
        $creator = $this->userWithRole(RoleName::SECRETARY, $career);
        $document = $this->draft($creator, $career);
        $recipient = $this->preparedUnitRecipient($document, $career, true);

        $this->actingAs($creator)->patch(route('documents.recipients.update', $document), [
            'recipient_id' => $recipient->id,
            'requires_response' => false,
            'lock_version' => 0,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertFalse($recipient->fresh()->requires_response);
        $this->assertSame(1, $document->fresh()->lock_version);
    }

    public function test_creator_can_soft_remove_and_safely_readd_a_prepared_recipient(): void
    {
        $career = $this->career('Ingeniería Biomédica', 'BIO');
        $creator = $this->userWithRole(RoleName::SECRETARY, $career);
        $document = $this->draft($creator, $career);
        $this->actingAs($creator)->post(
            route('documents.recipients.store', $document),
            $this->unitPayload($career, true, 0),
        )->assertRedirect();
        $recipient = DocumentRecipient::query()->sole();

        $this->actingAs($creator)->delete(route('documents.recipients.destroy', $document), [
            'recipient_id' => $recipient->id,
            'lock_version' => 1,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSoftDeleted('document_recipients', ['id' => $recipient->id]);
        $this->assertCount(0, $document->fresh()->recipients);
        $this->assertDatabaseHas('document_events', [
            'document_id' => $document->id,
            'document_recipient_id' => $recipient->id,
            'event_type' => 'recipient_removed',
        ]);

        $this->actingAs($creator)->post(
            route('documents.recipients.store', $document),
            $this->unitPayload($career, false, 2),
        )->assertRedirect();

        $restored = DocumentRecipient::query()->sole();
        $this->assertSame($recipient->id, $restored->id);
        $this->assertFalse($restored->requires_response);
        $this->assertNull($restored->deleted_at);
        $this->assertSame(DocumentRecipientStatus::PREPARED, $restored->status);
        $this->assertSame(1, DocumentRecipient::withTrashed()->count());

        $this->actingAs($creator)->post(
            route('documents.recipients.store', $document),
            $this->unitPayload($career, true, 3),
        )->assertSessionHasErrors('recipient');

        $this->assertSame(1, DocumentRecipient::withTrashed()->count());
        $this->assertSame(3, $document->fresh()->lock_version);
    }

    public function test_non_draft_document_rejects_all_recipient_management(): void
    {
        $career = $this->career('Ingeniería de Alimentos', 'ALI');
        $creator = $this->userWithRole(RoleName::SECRETARY, $career);
        $document = $this->draft($creator, $career);
        $document->forceFill([
            'general_status' => DocumentStatus::SENT,
            'code' => 'DOC-2026-0002',
            'sender_user_id' => $creator->id,
            'sent_at' => now(),
        ])->save();

        $this->actingAs($creator)->post(route('documents.recipients.store', $document), $this->unitPayload($career, true, 0))
            ->assertForbidden();
        $this->assertDatabaseCount('document_recipients', 0);
    }

    public function test_stale_tab_cannot_add_a_second_recipient_or_lose_first_change(): void
    {
        $origin = $this->career('Ingeniería Electrónica', 'ELE');
        $targetA = $this->career('Destino A', 'DTA');
        $targetB = $this->career('Destino B', 'DTB');
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $document = $this->draft($creator, $origin);

        $this->actingAs($creator)->post(route('documents.recipients.store', $document), $this->unitPayload($targetA, true, 0))->assertRedirect();
        $this->actingAs($creator)->post(route('documents.recipients.store', $document), $this->unitPayload($targetB, true, 0))
            ->assertSessionHasErrors('lock_version');

        $this->assertDatabaseCount('document_recipients', 1);
        $this->assertDatabaseHas('document_recipients', ['unit_id' => $targetA->id]);
        $this->assertDatabaseMissing('document_recipients', ['unit_id' => $targetB->id]);
        $this->assertSame(1, $document->fresh()->lock_version);
    }

    public function test_authorized_view_shows_recipients_but_horizontal_access_is_denied(): void
    {
        $origin = $this->career('Ingeniería Metalúrgica', 'MET');
        $otherCareer = $this->career('Otra carrera', 'OTR');
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $other = $this->userWithRole(RoleName::SECRETARY, $otherCareer);
        $target = $this->userWithRole(RoleName::CAREER_HEAD, $otherCareer);
        $document = $this->draft($creator, $origin);
        DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $otherCareer->id,
            'user_id' => $target->id,
            'requires_response' => true,
            'status' => DocumentRecipientStatus::PREPARED,
            'lock_version' => 0,
        ]);

        $this->actingAs($creator)->get(route('documents.show', $document))
            ->assertOk()->assertSee($target->name)->assertSee('Requiere respuesta');
        $this->actingAs($other)->get(route('documents.show', $document))
            ->assertForbidden()->assertDontSee($target->name);
    }

    /** @return array<string, mixed> */
    private function unitPayload(OrganizationalUnit $unit, bool $requiresResponse, int $lockVersion): array
    {
        return [
            'recipient_type' => 'unit',
            'unit_id' => $unit->id,
            'requires_response' => $requiresResponse,
            'lock_version' => $lockVersion,
        ];
    }

    /** @return array<string, mixed> */
    private function personPayload(User $user, bool $requiresResponse, int $lockVersion): array
    {
        return [
            'recipient_type' => 'person',
            'user_id' => $user->id,
            'requires_response' => $requiresResponse,
            'lock_version' => $lockVersion,
        ];
    }

    private function preparedUnitRecipient(Document $document, OrganizationalUnit $unit, bool $requiresResponse): DocumentRecipient
    {
        return DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'user_id' => null,
            'requires_response' => $requiresResponse,
            'status' => DocumentRecipientStatus::PREPARED,
            'lock_version' => 0,
        ]);
    }

    private function draft(User $creator, OrganizationalUnit $origin): Document
    {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('code', 'informe')->sole()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => 'Borrador con destinatarios',
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
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
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->sole();

        return OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => $name,
            'acronym' => $acronym,
            'is_active' => $active,
        ]);
    }
}
