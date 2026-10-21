<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentTransition;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentCounter;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
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

        Storage::fake('documents');
    }

    public function test_draft_with_recipient_can_be_sent_without_file_and_gets_official_fields(): void
    {
        [$document, $origin, $creator] = $this->draftContext();
        $recipient = $this->recipient($document, $origin);

        $this->send($creator, $document)->assertRedirect()->assertSessionHas('status');

        $document->refresh();
        $recipient->refresh();

        $this->assertMatchesRegularExpression('/^FTEC-'.$origin->acronym.'-'.now()->year.'-000001$/', $document->code);
        $this->assertSame(DocumentStatus::SENT, $document->general_status);
        $this->assertSame($creator->id, $document->sender_user_id);
        $this->assertNotNull($document->sent_at);
        $this->assertSame(1, $document->lock_version);
        $this->assertSame(DocumentRecipientStatus::SENT, $recipient->status);
        $this->assertSame(1, $recipient->lock_version);
        $this->assertDatabaseCount('document_versions', 0);
        $this->assertDatabaseHas('document_events', [
            'document_id' => $document->id,
            'event_type' => 'document_sent',
            'actor_id' => $creator->id,
            'from_status' => 'draft',
            'to_status' => 'sent',
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_changing_a_career_acronym_keeps_sent_codes_and_only_affects_future_documents(): void
    {
        [$historical, $origin, $creator] = $this->draftContext();
        $this->recipient($historical, $origin);
        $this->send($creator, $historical)->assertRedirect();
        $oldCode = $historical->fresh()->code;
        $oldAcronym = $origin->acronym;

        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $this->actingAs($administrator)->put(route('units.update', $origin), [
            'name' => $origin->name,
            'acronym' => 'NUEVA-SIGLA',
            'description' => null,
        ])->assertRedirect(route('units.show', $origin));

        $this->assertSame($oldCode, $historical->fresh()->code);
        $this->assertStringContainsString('-'.$oldAcronym.'-', $oldCode);

        $future = $this->draft($creator, $origin->fresh());
        $this->recipient($future, $origin);
        $this->send($creator, $future)->assertRedirect();

        $this->assertSame($oldCode, $historical->fresh()->code);
        $this->assertMatchesRegularExpression('/^FTEC-NUEVA-SIGLA-'.now()->year.'-000002$/', $future->fresh()->code);
    }

    public function test_draft_without_recipient_cannot_be_sent_or_consume_counter(): void
    {
        [$document, , $creator] = $this->draftContext();

        $this->send($creator, $document)
            ->assertSessionHasErrors('recipients');

        $document->refresh();
        $this->assertSame(DocumentStatus::DRAFT, $document->general_status);
        $this->assertNull($document->code);
        $this->assertNull($document->sent_at);
        $this->assertDatabaseCount('document_counters', 0);
    }

    public function test_codes_are_unique_sequential_and_drafts_do_not_consume_numbers(): void
    {
        [$first, $origin, $creator] = $this->draftContext();
        $second = $this->draft($creator, $origin);
        $unusedDraft = $this->draft($creator, $origin);
        $this->recipient($first, $origin);
        $this->recipient($second, $origin);

        $this->send($creator, $first)->assertRedirect();
        $this->send($creator, $second)->assertRedirect();

        $this->assertNotSame($first->fresh()->code, $second->fresh()->code);
        $this->assertStringEndsWith('-000001', $first->fresh()->code);
        $this->assertStringEndsWith('-000002', $second->fresh()->code);
        $this->assertNull($unusedDraft->fresh()->code);
        $this->assertSame(2, DocumentCounter::query()->sole()->last_number);
    }

    public function test_unauthorized_user_cannot_send_and_sent_document_cannot_be_sent_twice(): void
    {
        [$document, $origin, $creator] = $this->draftContext();
        $other = $this->userWithRole(RoleName::SECRETARY, $origin);
        $this->recipient($document, $origin);

        $this->send($other, $document)->assertForbidden();
        $this->assertDatabaseCount('document_counters', 0);

        $this->send($creator, $document)->assertRedirect();
        $this->send($creator, $document, 1)->assertForbidden();
        $this->assertSame(1, DocumentCounter::query()->sole()->last_number);
    }

    public function test_opening_document_does_not_receive_it_and_destination_can_view_after_send(): void
    {
        [$document, $origin, $creator] = $this->draftContext();
        $destination = $this->career();
        $receiver = $this->userWithRole(RoleName::SECRETARY, $destination);
        $recipient = $this->recipient($document, $destination);

        $this->send($creator, $document)->assertRedirect();
        $this->actingAs($receiver)->get(route('documents.show', $document))
            ->assertOk()->assertSee('Recibir documento');

        $recipient->refresh();
        $this->assertSame(DocumentRecipientStatus::SENT, $recipient->status);
        $this->assertNull($recipient->received_at);
        $this->assertNull($recipient->received_by);
        $this->assertNotSame($origin->id, $destination->id);
    }

    public function test_sender_sees_compact_tracking_instead_of_destination_controls_after_send(): void
    {
        [$document, , $creator] = $this->draftContext();
        $destination = $this->career();
        $this->recipient($document, $destination);
        $this->send($creator, $document)->assertRedirect();

        $this->actingAs($creator)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('SEGUIMIENTO DEL DOCUMENTO')
            ->assertSee('Documento enviado.')
            ->assertSee('Por ahora no necesita realizar ninguna acción.')
            ->assertSee('Seguimiento por destinatario')
            ->assertSee($destination->name)
            ->assertSee('Esperando recepción.')
            ->assertSee('Ver archivos')
            ->assertSee('Ver historial')
            ->assertDontSee('Recibir documento')
            ->assertDontSee('Iniciar revisión')
            ->assertDontSee('Aprobar documento')
            ->assertDontSee('name="requires_response"', false)
            ->assertDontSee('Agregar persona');
    }

    public function test_destination_sees_its_available_action_instead_of_sender_message(): void
    {
        [$document, , $creator] = $this->draftContext();
        $destination = $this->career();
        $receiver = $this->userWithRole(RoleName::SECRETARY, $destination);
        $this->recipient($document, $destination);
        $this->send($creator, $document)->assertRedirect();

        $this->actingAs($receiver)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('DOCUMENTO PARA SU ATENCIÓN')
            ->assertSee('Nuevo documento')
            ->assertSee('Este documento fue enviado a su carrera.')
            ->assertSee('Recibir documento')
            ->assertDontSee('Por ahora no necesita realizar ninguna acción.')
            ->assertDontSee('Iniciar revisión')
            ->assertDontSee('Aprobar documento');
    }

    public function test_personal_main_recipient_and_unit_copy_have_distinct_actions_and_visibility(): void
    {
        [$document, , $creator] = $this->draftContext();
        $destination = $this->career();
        $head = $this->userWithRole(RoleName::CAREER_HEAD, $destination);
        $secretary = $this->userWithRole(RoleName::SECRETARY, $destination);
        $this->recipient($document, $destination, true, $head);
        $this->recipient($document, $destination, false);
        $this->send($creator, $document)->assertRedirect();

        $headPage = $this->actingAs($head)->get(route('documents.show', $document))->assertOk()
            ->assertSee('DOCUMENTO PARA SU ATENCIÓN')
            ->assertSee('Destinatarios principales')
            ->assertSee('Copias informativas')
            ->assertSee('Registrar recepción de copia');
        $this->assertSame(1, substr_count($headPage->getContent(), '>Recibir documento</button>'));

        $this->actingAs($secretary)->get(route('documents.show', $document))->assertOk()
            ->assertSee('COPIA INFORMATIVA')
            ->assertSee('No necesita aprobarlo ni responderlo.')
            ->assertSee('Registrar recepción de copia')
            ->assertDontSee('>Recibir documento</button>', false)
            ->assertDontSee('>Iniciar revisión</button>', false)
            ->assertDontSee('>Aprobar documento</button>', false);
    }

    public function test_sender_sees_returned_recipient_as_an_actionable_exception(): void
    {
        [$document, , $creator] = $this->draftContext();
        $destination = $this->career();
        $receiver = $this->userWithRole(RoleName::CAREER_HEAD, $destination);
        $recipient = $this->recipient($document, $destination);
        $this->send($creator, $document)->assertRedirect();
        $this->transition($receiver, $document, $recipient, 'receive')->assertRedirect();
        $this->transition($receiver, $document, $recipient, 'start_review')->assertRedirect();
        $this->transition($receiver, $document, $recipient, 'return', 'Falta una firma')->assertRedirect();

        $this->actingAs($creator)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('Este documento requiere su atención.')
            ->assertSee('Falta una firma')
            ->assertSee('Ver observaciones')
            ->assertSee('Preparar corrección')
            ->assertDontSee('Aprobar documento');
    }

    public function test_tracking_summarizes_independent_progress_for_multiple_destinations(): void
    {
        [$document, , $creator] = $this->draftContext();
        $approvedUnit = $this->career();
        $reviewUnit = $this->career();
        $waitingUnit = $this->career();
        $approvedActor = $this->userWithRole(RoleName::CAREER_HEAD, $approvedUnit);
        $reviewActor = $this->userWithRole(RoleName::CAREER_HEAD, $reviewUnit);
        $approvedRecipient = $this->recipient($document, $approvedUnit);
        $reviewRecipient = $this->recipient($document, $reviewUnit);
        $this->recipient($document, $waitingUnit);
        $this->send($creator, $document)->assertRedirect();
        $this->receiveReviewApprove($approvedActor, $document, $approvedRecipient);
        $this->transition($reviewActor, $document, $reviewRecipient, 'receive')->assertRedirect();
        $this->transition($reviewActor, $document, $reviewRecipient, 'start_review')->assertRedirect();

        $this->actingAs($creator)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('3 carreras')
            ->assertSee('1 aprobó')
            ->assertSee('1 en revisión')
            ->assertSee('1 espera recepción')
            ->assertSee($approvedUnit->name)
            ->assertSee($reviewUnit->name)
            ->assertSee($waitingUnit->name);
    }

    public function test_only_head_or_secretary_of_unit_can_receive_unit_recipient(): void
    {
        [$document, , $creator] = $this->draftContext();
        $destination = $this->career();
        $secretary = $this->userWithRole(RoleName::SECRETARY, $destination);
        $assistant = $this->userWithRole(RoleName::SECRETARY_ASSISTANT, $destination);
        $outsider = $this->userWithRole(RoleName::SECRETARY, $this->career());
        $recipient = $this->recipient($document, $destination);
        $this->send($creator, $document);

        $this->transition($assistant, $document, $recipient, 'receive')->assertForbidden();
        $this->transition($outsider, $document, $recipient, 'receive')->assertForbidden();
        $this->transition($secretary, $document, $recipient, 'receive')->assertRedirect();

        $recipient->refresh();
        $this->assertSame(DocumentRecipientStatus::RECEIVED, $recipient->status);
        $this->assertSame($secretary->id, $recipient->received_by);
        $this->assertNotNull($recipient->received_at);
        $this->assertSame(DocumentStatus::RECEIVED, $document->fresh()->general_status);

        $headDocument = $this->draft($creator, $document->originUnit);
        $head = $this->userWithRole(RoleName::CAREER_HEAD, $destination);
        $headRecipient = $this->recipient($headDocument, $destination);
        $this->send($creator, $headDocument);
        $this->transition($head, $headDocument, $headRecipient, 'receive')->assertRedirect();
        $this->assertSame($head->id, $headRecipient->fresh()->received_by);
    }

    public function test_personal_recipient_is_strict_and_global_administrator_cannot_receive(): void
    {
        [$document, , $creator] = $this->draftContext();
        $destination = $this->career();
        $person = $this->userWithRole(RoleName::SECRETARY, $destination);
        $sameUnitHead = $this->userWithRole(RoleName::CAREER_HEAD, $destination);
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $recipient = $this->recipient($document, $destination, true, $person);
        $this->send($creator, $document);

        $this->transition($sameUnitHead, $document, $recipient, 'receive')->assertForbidden();
        $this->transition($administrator, $document, $recipient, 'receive')->assertForbidden();
        $this->transition($person, $document, $recipient, 'receive')->assertRedirect();

        $this->assertSame($person->id, $recipient->fresh()->received_by);
    }

    public function test_received_document_can_enter_review_and_authorized_actor_can_approve(): void
    {
        [$document, , $creator] = $this->draftContext();
        $destination = $this->career();
        $secretary = $this->userWithRole(RoleName::SECRETARY, $destination);
        $recipient = $this->recipient($document, $destination);
        $this->send($creator, $document);

        $this->transition($secretary, $document, $recipient, 'approve')->assertForbidden();
        $this->transition($secretary, $document, $recipient, 'receive')->assertRedirect();
        $this->transition($secretary, $document, $recipient, 'start_review')->assertRedirect();

        $recipient->refresh();
        $this->assertSame(DocumentRecipientStatus::IN_REVIEW, $recipient->status);
        $this->assertSame($secretary->id, $recipient->current_handler_id);
        $this->assertSame(DocumentStatus::IN_REVIEW, $document->fresh()->general_status);

        $this->transition($secretary, $document, $recipient, 'approve')->assertRedirect();
        $recipient->refresh();
        $this->assertSame(DocumentRecipientStatus::APPROVED, $recipient->status);
        $this->assertSame($secretary->id, $recipient->decided_by);
        $this->assertNotNull($recipient->decided_at);
        $this->assertSame(DocumentStatus::APPROVED, $document->fresh()->general_status);
    }

    public function test_personal_head_and_secretary_can_complete_formal_flow(): void
    {
        [$firstDocument, , $creator] = $this->draftContext();
        $roles = [RoleName::CAREER_HEAD, RoleName::SECRETARY];

        foreach ($roles as $index => $role) {
            $document = $index === 0
                ? $firstDocument
                : $this->draft($creator, $firstDocument->originUnit);
            $destination = $this->career();
            $person = $this->userWithRole($role, $destination);
            $recipient = $this->recipient($document, $destination, true, $person);

            $this->send($creator, $document)->assertRedirect();
            $this->receiveReviewApprove($person, $document, $recipient);

            $this->assertSame(DocumentRecipientStatus::APPROVED, $recipient->fresh()->status);
            $this->assertSame(DocumentStatus::APPROVED, $document->fresh()->general_status);
        }
    }

    public function test_send_revalidates_prepared_personal_recipient_eligibility(): void
    {
        [$firstDocument, , $creator] = $this->draftContext();
        $destination = $this->career();
        $assistant = $this->userWithRole(RoleName::SECRETARY_ASSISTANT, $destination);
        $extra = $this->userWithRole(RoleName::EXTRA, $destination);

        foreach ([[$assistant, true], [$extra, false]] as $index => [$person, $requiresResponse]) {
            $document = $index === 0
                ? $firstDocument
                : $this->draft($creator, $firstDocument->originUnit);
            $recipient = $this->recipient($document, $destination, $requiresResponse, $person);

            $this->send($creator, $document)->assertSessionHasErrors('recipients');

            $this->assertSame(DocumentStatus::DRAFT, $document->fresh()->general_status);
            $this->assertSame(DocumentRecipientStatus::PREPARED, $recipient->fresh()->status);
        }

        $this->assertDatabaseCount('document_counters', 0);
    }

    public function test_assistant_cannot_start_formal_review_or_approve_and_extra_cannot_transition(): void
    {
        [$document, , $creator] = $this->draftContext();
        $destination = $this->career();
        $secretary = $this->userWithRole(RoleName::SECRETARY, $destination);
        $assistant = $this->userWithRole(RoleName::SECRETARY_ASSISTANT, $destination);
        $extra = $this->userWithRole(RoleName::EXTRA, $destination);
        $recipient = $this->recipient($document, $destination);
        $this->send($creator, $document);
        $this->transition($secretary, $document, $recipient, 'receive');

        $this->actingAs($extra)->get(route('documents.show', $document))->assertForbidden();
        $this->transition($assistant, $document, $recipient, 'start_review')->assertForbidden();
        $this->transition($assistant, $document, $recipient, 'approve')->assertForbidden();
        $this->transition($extra, $document, $recipient, 'approve')->assertForbidden();
        $this->assertSame(DocumentRecipientStatus::RECEIVED, $recipient->fresh()->status);
    }

    public function test_head_and_secretary_can_perform_all_five_formal_actions_for_their_unit(): void
    {
        [$firstDocument, , $creator] = $this->draftContext();
        $destination = $this->career();

        foreach ([RoleName::CAREER_HEAD, RoleName::SECRETARY] as $role) {
            $actor = $this->userWithRole($role, $destination);

            foreach (DocumentTransition::cases() as $action) {
                $document = $this->draft($creator, $firstDocument->originUnit);
                $recipient = $this->recipient($document, $destination);
                $this->send($creator, $document)->assertRedirect();
                $this->prepareForAction($actor, $document, $recipient, $action);

                $this->transition($actor, $document, $recipient, $action->value, 'Motivo de decisión formal.')
                    ->assertRedirect()->assertSessionHasNoErrors();
                $this->assertSame(match ($action) {
                    DocumentTransition::RECEIVE => DocumentRecipientStatus::RECEIVED,
                    DocumentTransition::START_REVIEW => DocumentRecipientStatus::IN_REVIEW,
                    DocumentTransition::APPROVE => DocumentRecipientStatus::APPROVED,
                    DocumentTransition::RETURN => DocumentRecipientStatus::RETURNED,
                    DocumentTransition::REJECT => DocumentRecipientStatus::REJECTED,
                }, $recipient->fresh()->status);
            }
        }
    }

    public function test_formal_actions_reject_non_formal_roles_other_careers_and_inactive_users(): void
    {
        [$firstDocument, , $creator] = $this->draftContext();
        $destination = $this->career();
        $secretary = $this->userWithRole(RoleName::SECRETARY, $destination);
        $deniedActors = [];

        foreach ([RoleName::SECRETARY_ASSISTANT, RoleName::EXTRA, RoleName::ADMINISTRATOR, RoleName::CO_ADMINISTRATOR] as $role) {
            $actor = $this->userWithRole($role, $role->isGlobal() ? null : $destination);
            // Even direct capabilities must not bypass the institutional role rule.
            $actor->givePermissionTo([
                PermissionName::DOCUMENTS_RECEIVE->value, PermissionName::DOCUMENTS_REVIEW->value,
                PermissionName::DOCUMENTS_APPROVE->value, PermissionName::DOCUMENTS_RETURN->value,
                PermissionName::DOCUMENTS_REJECT->value,
            ]);
            $deniedActors[] = $actor;
        }

        foreach ([RoleName::CAREER_HEAD, RoleName::SECRETARY] as $role) {
            $deniedActors[] = $this->userWithRole($role, $this->career());
            $inactive = $this->userWithRole($role, $destination);
            $inactive->update(['status' => 'inactive']);
            $deniedActors[] = $inactive;
        }

        foreach (DocumentTransition::cases() as $action) {
            $document = $this->draft($creator, $firstDocument->originUnit);
            $recipient = $this->recipient($document, $destination);
            $this->send($creator, $document)->assertRedirect();
            $this->prepareForAction($secretary, $document, $recipient, $action);
            $before = $recipient->fresh()->getAttributes();
            $eventCount = $document->events()->count();

            foreach ($deniedActors as $actor) {
                $this->transition($actor, $document, $recipient, $action->value, 'No autorizado.')
                    ->assertForbidden();
            }

            $this->assertSame($before, $recipient->fresh()->getAttributes());
            $this->assertSame($eventCount, $document->events()->count());
        }
    }

    public function test_formal_authority_rejects_mismatched_recipient_and_wrong_state(): void
    {
        [$document, , $creator] = $this->draftContext();
        $destination = $this->career();
        $actor = $this->userWithRole(RoleName::SECRETARY, $destination);
        $recipient = $this->recipient($document, $destination);
        $otherDocument = $this->draft($creator, $document->originUnit);
        $this->send($creator, $document);

        $this->transition($actor, $otherDocument, $recipient, 'receive')->assertForbidden();
        foreach (['start_review', 'approve', 'return', 'reject'] as $action) {
            $this->transition($actor, $document, $recipient, $action, 'Estado incorrecto.')->assertForbidden();
        }
        $this->assertSame(DocumentRecipientStatus::SENT, $recipient->fresh()->status);
    }

    public function test_informational_copy_can_be_received_but_cannot_review_or_decide(): void
    {
        [$document, , $creator] = $this->draftContext();
        $destination = $this->career();
        $secretary = $this->userWithRole(RoleName::SECRETARY, $destination);
        $copy = $this->recipient($document, $destination, false);
        $this->send($creator, $document);

        $this->transition($secretary, $document, $copy, 'receive')->assertRedirect();

        foreach (['start_review', 'approve', 'return', 'reject'] as $action) {
            $this->transition($secretary, $document, $copy, $action, 'Motivo suficiente')->assertForbidden();
        }

        $this->assertSame(DocumentRecipientStatus::RECEIVED, $copy->fresh()->status);
        $this->assertSame(DocumentStatus::RECEIVED, $document->fresh()->general_status);
    }

    public function test_personal_assistant_copy_is_informational_and_does_not_block_approval(): void
    {
        [$document, , $creator] = $this->draftContext();
        $formalUnit = $this->career();
        $assistantUnit = $this->career();
        $secretary = $this->userWithRole(RoleName::SECRETARY, $formalUnit);
        $assistant = $this->userWithRole(RoleName::SECRETARY_ASSISTANT, $assistantUnit);
        $formal = $this->recipient($document, $formalUnit);
        $copy = $this->recipient($document, $assistantUnit, false, $assistant);

        $this->send($creator, $document)->assertRedirect();
        $this->actingAs($assistant)->get(route('documents.show', $document))->assertOk();
        $this->transition($assistant, $document, $copy, 'receive')->assertForbidden();
        $this->receiveReviewApprove($secretary, $document, $formal);

        $this->assertSame(DocumentRecipientStatus::SENT, $copy->fresh()->status);
        $this->assertSame(DocumentStatus::APPROVED, $document->fresh()->general_status);
    }

    public function test_return_and_reject_require_reason_and_remain_distinct(): void
    {
        [$returnedDocument, , $creator] = $this->draftContext();
        $destination = $this->career();
        $secretary = $this->userWithRole(RoleName::SECRETARY, $destination);
        $returned = $this->recipient($returnedDocument, $destination);
        $this->send($creator, $returnedDocument);
        $this->transition($secretary, $returnedDocument, $returned, 'receive');
        $this->transition($secretary, $returnedDocument, $returned, 'start_review');

        $this->transition($secretary, $returnedDocument, $returned, 'return', '   ')
            ->assertSessionHasErrors('reason');
        $this->transition($secretary, $returnedDocument, $returned, 'return', 'Debe corregir los anexos.')
            ->assertRedirect();

        $returned->refresh();
        $this->assertSame(DocumentRecipientStatus::RETURNED, $returned->status);
        $this->assertSame('Debe corregir los anexos.', $returned->decision_reason);
        $this->assertSame(DocumentStatus::RETURNED, $returnedDocument->fresh()->general_status);

        $rejectedDocument = $this->draft($creator, $returnedDocument->originUnit);
        $rejected = $this->recipient($rejectedDocument, $destination);
        $this->send($creator, $rejectedDocument);
        $this->transition($secretary, $rejectedDocument, $rejected, 'receive');
        $this->transition($secretary, $rejectedDocument, $rejected, 'reject', 'No corresponde al trámite.')
            ->assertRedirect();

        $this->assertSame(DocumentRecipientStatus::REJECTED, $rejected->fresh()->status);
        $this->assertSame(DocumentStatus::REJECTED, $rejectedDocument->fresh()->general_status);
    }

    public function test_aggregate_status_uses_required_recipients_and_copy_does_not_block_approval(): void
    {
        [$document, , $creator] = $this->draftContext();
        $unitA = $this->career();
        $unitB = $this->career();
        $copyUnit = $this->career();
        $actorA = $this->userWithRole(RoleName::SECRETARY, $unitA);
        $actorB = $this->userWithRole(RoleName::SECRETARY, $unitB);
        $requiredA = $this->recipient($document, $unitA);
        $requiredB = $this->recipient($document, $unitB);
        $copy = $this->recipient($document, $copyUnit, false);
        $this->send($creator, $document);

        $this->receiveReviewApprove($actorA, $document, $requiredA);
        $this->assertSame(DocumentStatus::SENT, $document->fresh()->general_status);
        $this->transition($actorB, $document, $requiredB, 'receive');
        $this->assertSame(DocumentStatus::RECEIVED, $document->fresh()->general_status);
        $this->transition($actorB, $document, $requiredB, 'start_review');
        $this->assertSame(DocumentStatus::IN_REVIEW, $document->fresh()->general_status);
        $this->transition($actorB, $document, $requiredB, 'approve');

        $this->assertSame(DocumentStatus::APPROVED, $document->fresh()->general_status);
        $this->assertSame(DocumentRecipientStatus::SENT, $copy->fresh()->status);
        $this->assertSame(DocumentRecipientStatus::APPROVED, $requiredA->fresh()->status);
        $this->assertSame(DocumentRecipientStatus::APPROVED, $requiredB->fresh()->status);
    }

    public function test_rejection_and_return_have_precedence_without_erasing_other_decisions(): void
    {
        [$document, , $creator] = $this->draftContext();
        $unitA = $this->career();
        $unitB = $this->career();
        $actorA = $this->userWithRole(RoleName::SECRETARY, $unitA);
        $actorB = $this->userWithRole(RoleName::SECRETARY, $unitB);
        $recipientA = $this->recipient($document, $unitA);
        $recipientB = $this->recipient($document, $unitB);
        $this->send($creator, $document);

        $this->receiveReviewApprove($actorA, $document, $recipientA);
        $this->transition($actorB, $document, $recipientB, 'receive');
        $this->transition($actorB, $document, $recipientB, 'return', 'Falta información.');
        $this->assertSame(DocumentStatus::RETURNED, $document->fresh()->general_status);
        $this->assertSame(DocumentRecipientStatus::APPROVED, $recipientA->fresh()->status);

        $rejectedDocument = $this->draft($creator, $document->originUnit);
        $approved = $this->recipient($rejectedDocument, $unitA);
        $rejected = $this->recipient($rejectedDocument, $unitB);
        $this->send($creator, $rejectedDocument);
        $this->receiveReviewApprove($actorA, $rejectedDocument, $approved);
        $this->transition($actorB, $rejectedDocument, $rejected, 'receive');
        $this->transition($actorB, $rejectedDocument, $rejected, 'reject', 'Rechazo formal.');

        $this->assertSame(DocumentStatus::REJECTED, $rejectedDocument->fresh()->general_status);
        $this->assertSame(DocumentRecipientStatus::APPROVED, $approved->fresh()->status);
        $this->assertSame(DocumentRecipientStatus::REJECTED, $rejected->fresh()->status);
    }

    public function test_stale_recipient_decision_cannot_overwrite_winning_transition(): void
    {
        [$document, , $creator] = $this->draftContext();
        $destination = $this->career();
        $secretary = $this->userWithRole(RoleName::SECRETARY, $destination);
        $recipient = $this->recipient($document, $destination);
        $this->send($creator, $document);
        $this->transition($secretary, $document, $recipient, 'receive');
        $this->transition($secretary, $document, $recipient, 'start_review');

        $staleDocumentVersion = $document->fresh()->lock_version;
        $staleRecipientVersion = $recipient->fresh()->lock_version;

        $this->transition($secretary, $document, $recipient, 'approve')->assertRedirect();

        try {
            app(DocumentWorkflowService::class)->transition(
                $secretary,
                $document,
                $recipient,
                DocumentTransition::RETURN,
                $staleDocumentVersion,
                $staleRecipientVersion,
                'Intento obsoleto.',
            );
            $this->fail('La decisión obsoleta no fue rechazada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document_lock_version', $exception->errors());
        }

        $this->assertSame(DocumentRecipientStatus::APPROVED, $recipient->fresh()->status);
        $this->assertSame(DocumentStatus::APPROVED, $document->fresh()->general_status);
    }

    public function test_files_and_recipients_become_immutable_after_send(): void
    {
        [$document, $origin, $creator] = $this->draftContext();
        $recipient = $this->recipient($document, $origin);
        $this->send($creator, $document);

        $this->actingAs($creator)->post(route('documents.files.store', $document), [
            'files' => [UploadedFile::fake()->create('nuevo.pdf', 10, 'application/pdf')],
            'lock_version' => 1,
        ])->assertForbidden();

        $this->actingAs($creator)->post(route('documents.recipients.store', $document), [
            'recipient_type' => 'unit',
            'unit_id' => $origin->id,
            'requires_response' => true,
            'lock_version' => 1,
        ])->assertForbidden();

        $this->actingAs($creator)->delete(route('documents.recipients.destroy', $document), [
            'recipient_id' => $recipient->id,
            'lock_version' => 1,
        ])->assertForbidden();
        $this->assertDatabaseCount('document_recipients', 1);
    }

    private function prepareForAction(User $actor, Document $document, DocumentRecipient $recipient, DocumentTransition $action): void
    {
        if ($action !== DocumentTransition::RECEIVE) {
            $this->transition($actor, $document, $recipient, 'receive')->assertRedirect();
        }
        if (in_array($action, [DocumentTransition::APPROVE, DocumentTransition::RETURN, DocumentTransition::REJECT], true)) {
            $this->transition($actor, $document, $recipient, 'start_review')->assertRedirect();
        }
    }

    private function receiveReviewApprove(User $actor, Document $document, DocumentRecipient $recipient): void
    {
        $this->transition($actor, $document, $recipient, 'receive')->assertRedirect();
        $this->transition($actor, $document, $recipient, 'start_review')->assertRedirect();
        $this->transition($actor, $document, $recipient, 'approve')->assertRedirect();
    }

    private function send(User $actor, Document $document, ?int $version = null): TestResponse
    {
        return $this->actingAs($actor)->post(route('documents.send', $document), [
            'document_lock_version' => $version ?? $document->fresh()->lock_version,
        ]);
    }

    private function transition(
        User $actor,
        Document $document,
        DocumentRecipient $recipient,
        string $transition,
        ?string $reason = null,
    ): TestResponse {
        return $this->actingAs($actor)->patch(
            route('documents.recipients.transition', [$document, $recipient, $transition]),
            [
                'document_lock_version' => $document->fresh()->lock_version,
                'recipient_lock_version' => $recipient->fresh()->lock_version,
                'reason' => $reason,
            ],
        );
    }

    /** @return array{Document, OrganizationalUnit, User} */
    private function draftContext(): array
    {
        $origin = $this->career();
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);

        return [$this->draft($creator, $origin), $origin, $creator];
    }

    private function draft(User $creator, OrganizationalUnit $origin): Document
    {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('code', 'informe')->sole()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => 'Flujo documental '.Str::random(8),
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
        ]);
    }

    private function recipient(
        Document $document,
        OrganizationalUnit $unit,
        bool $requiresResponse = true,
        ?User $user = null,
    ): DocumentRecipient {
        return DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'user_id' => $user?->id,
            'requires_response' => $requiresResponse,
            'status' => DocumentRecipientStatus::PREPARED,
            'lock_version' => 0,
        ]);
    }

    private function userWithRole(RoleName $role, ?OrganizationalUnit $unit = null): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit?->id]);
        $user->assignRole($role->value);

        return $user;
    }

    private function career(): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->sole();

        return OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera Flujo '.Str::random(8),
            'acronym' => Str::upper(Str::random(5)),
            'is_active' => true,
        ]);
    }
}
