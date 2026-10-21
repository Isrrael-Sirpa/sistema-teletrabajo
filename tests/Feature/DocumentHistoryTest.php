<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentTransition;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentEvent;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentDraftService;
use App\Services\DocumentEventService;
use App\Services\DocumentFileService;
use App\Services\DocumentRecipientService;
use App\Services\DocumentWorkflowService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class DocumentHistoryTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $temporaryFiles = [];

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

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_create_update_and_discard_preserve_append_only_draft_history(): void
    {
        $career = $this->career();
        $actor = $this->userWithRole(RoleName::SECRETARY, $career);
        $service = app(DocumentDraftService::class);
        $document = $service->create($actor, $this->documentData('Solicitud inicial'));

        $created = DocumentEvent::query()->sole();
        $this->assertSame(DocumentEventType::DOCUMENT_CREATED, $created->event_type);
        $this->assertSame($actor->id, $created->actor_id);
        $this->assertNotNull($created->occurred_at);
        $this->assertSame('Solicitud inicial', $created->metadata['subject']);

        $service->update($actor, $document, [
            ...$this->documentData('Solicitud actualizada'),
            'lock_version' => 0,
        ]);

        $updated = DocumentEvent::query()->where('event_type', DocumentEventType::DRAFT_UPDATED)->sole();
        $this->assertContains('asunto', $updated->metadata['changed_fields']);
        $this->assertSame($actor->id, $updated->actor_id);

        $service->discard($actor, $document);

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        $this->assertSame(3, DocumentEvent::query()->where('document_id', $document->id)->count());
        $discarded = DocumentEvent::query()->where('event_type', DocumentEventType::DRAFT_DISCARDED)->sole();
        $this->assertSame($actor->id, $discarded->actor_id);
        $this->assertTrue($discarded->document->trashed());
    }

    public function test_recipient_history_survives_soft_removal_and_safe_restore(): void
    {
        [$document, $origin, $actor] = $this->context();
        $destination = $this->career();
        $service = app(DocumentRecipientService::class);

        $recipient = $service->add($actor, $document, [
            'recipient_type' => 'unit',
            'unit_id' => $destination->id,
            'requires_response' => true,
            'lock_version' => 0,
        ]);
        $service->update($actor, $document, [
            'recipient_id' => $recipient->id,
            'requires_response' => false,
            'lock_version' => 1,
        ]);
        $service->remove($actor, $document, [
            'recipient_id' => $recipient->id,
            'lock_version' => 2,
        ]);

        $this->assertSoftDeleted('document_recipients', ['id' => $recipient->id]);
        $this->assertCount(0, $document->fresh()->recipients);
        $this->assertSame(
            ['recipient_added', 'recipient_updated', 'recipient_removed'],
            DocumentEvent::query()->where('document_id', $document->id)->orderBy('id')->pluck('event_type')->map->value->all(),
        );
        $removed = DocumentEvent::query()->where('event_type', DocumentEventType::RECIPIENT_REMOVED)->sole();
        $this->assertSame($recipient->id, $removed->recipient->id);
        $this->assertTrue($removed->recipient->trashed());

        DB::table('document_recipients')->where('id', $recipient->id)->update([
            'status' => DocumentRecipientStatus::RECEIVED->value,
            'received_by' => $actor->id,
            'received_at' => now(),
            'current_handler_id' => $actor->id,
        ]);

        $restored = $service->add($actor, $document, [
            'recipient_type' => 'unit',
            'unit_id' => $destination->id,
            'requires_response' => true,
            'lock_version' => 3,
        ]);

        $this->assertSame($recipient->id, $restored->id);
        $this->assertSame(DocumentRecipientStatus::PREPARED, $restored->status);
        $this->assertNull($restored->received_by);
        $this->assertNull($restored->received_at);
        $this->assertNull($restored->current_handler_id);
        $this->assertSame(1, DocumentRecipient::withTrashed()->count());
        $this->assertTrue(DocumentEvent::query()->latest('id')->firstOrFail()->metadata['restored']);

        $this->expectException(ValidationException::class);
        $service->add($actor, $document, [
            'recipient_type' => 'unit',
            'unit_id' => $destination->id,
            'requires_response' => true,
            'lock_version' => 4,
        ]);
    }

    public function test_file_addition_and_removal_create_safe_metadata_events(): void
    {
        [$document, , $actor] = $this->context();
        $service = app(DocumentFileService::class);
        $file = $service->upload($actor, $document, [$this->pdf('informe reservado.pdf')], 0)[0];
        $service->remove($actor, $document, $file, 1);

        $events = DocumentEvent::query()->where('document_id', $document->id)->orderBy('id')->get();
        $this->assertSame([DocumentEventType::FILE_ADDED, DocumentEventType::FILE_REMOVED], $events->pluck('event_type')->all());
        $this->assertSame('informe reservado.pdf', $events[0]->metadata['original_name']);
        $this->assertSame(1, $events[0]->metadata['version']);
        $this->assertArrayNotHasKey('path', $events[0]->metadata);
        $this->assertArrayNotHasKey('sha256', $events[0]->metadata);
        $this->assertSame($actor->id, $events[1]->actor_id);
    }

    public function test_send_receive_review_and_approval_create_structured_state_events(): void
    {
        [$document, , $sender] = $this->context();
        $destination = $this->career();
        $receiver = $this->userWithRole(RoleName::SECRETARY, $destination);
        $recipient = $this->recipient($document, $destination);
        $workflow = app(DocumentWorkflowService::class);

        $workflow->send($sender, $document, 0);
        $workflow->transition($receiver, $document, $recipient, DocumentTransition::RECEIVE, 1, 1);
        $workflow->transition($receiver, $document, $recipient, DocumentTransition::START_REVIEW, 2, 2);
        $workflow->transition($receiver, $document, $recipient, DocumentTransition::APPROVE, 3, 3);

        $events = DocumentEvent::query()->where('document_id', $document->id)->orderBy('id')->get();
        $this->assertSame([
            DocumentEventType::DOCUMENT_SENT,
            DocumentEventType::DOCUMENT_RECEIVED,
            DocumentEventType::REVIEW_STARTED,
            DocumentEventType::DOCUMENT_APPROVED,
        ], $events->pluck('event_type')->all());
        $this->assertSame($sender->id, $events[0]->actor_id);
        $this->assertSame('draft', $events[0]->from_status);
        $this->assertSame('sent', $events[0]->to_status);
        $this->assertSame(1, $events[0]->metadata['recipient_count']);

        foreach ($events->slice(1) as $event) {
            $this->assertSame($receiver->id, $event->actor_id);
            $this->assertSame($recipient->id, $event->document_recipient_id);
            $this->assertNotNull($event->occurred_at);
        }

        $this->assertSame('in_review', $events->last()->from_status);
        $this->assertSame('approved', $events->last()->to_status);
    }

    public function test_return_and_reject_events_keep_their_reasons(): void
    {
        [, $origin, $sender] = $this->context();
        $destination = $this->career();
        $actor = $this->userWithRole(RoleName::SECRETARY, $destination);
        $workflow = app(DocumentWorkflowService::class);

        foreach ([
            [DocumentTransition::RETURN, 'Falta la firma correspondiente.', DocumentEventType::DOCUMENT_RETURNED],
            [DocumentTransition::REJECT, 'El trámite no corresponde.', DocumentEventType::DOCUMENT_REJECTED],
        ] as [$transition, $reason, $eventType]) {
            $document = $this->draft($sender, $origin);
            $recipient = $this->recipient($document, $destination);
            $workflow->send($sender, $document, 0);
            $workflow->transition($actor, $document, $recipient, DocumentTransition::RECEIVE, 1, 1);
            $workflow->transition($actor, $document, $recipient, $transition, 2, 2, $reason);

            $event = DocumentEvent::query()
                ->where('document_id', $document->id)
                ->where('event_type', $eventType)
                ->sole();
            $this->assertSame($reason, $event->reason);
            $this->assertSame($reason, $recipient->fresh()->decision_reason);
            $this->assertSame($recipient->id, $event->document_recipient_id);
        }
    }

    public function test_failed_operation_does_not_leave_an_event(): void
    {
        [$document, , $actor] = $this->context();
        $service = app(DocumentEventService::class);

        try {
            DB::transaction(function () use ($actor, $document, $service): void {
                $service->record($document, DocumentEventType::DRAFT_UPDATED, $actor);
                throw ValidationException::withMessages(['document' => 'Fallo simulado.']);
            });
            $this->fail('La transacción simulada no falló.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('document_events', 0);
        }
    }

    public function test_history_is_immutable_through_the_application_model(): void
    {
        [$document, , $actor] = $this->context();
        $event = app(DocumentEventService::class)->record($document, DocumentEventType::DOCUMENT_CREATED, $actor);

        try {
            $event->update(['reason' => 'Alteración indebida']);
            $this->fail('El evento pudo modificarse.');
        } catch (LogicException $exception) {
            $this->assertSame('Los eventos documentales son inmutables.', $exception->getMessage());
        }

        try {
            $event->delete();
            $this->fail('El evento pudo eliminarse.');
        } catch (LogicException $exception) {
            $this->assertSame('Los eventos documentales no pueden eliminarse.', $exception->getMessage());
        }

        $this->assertDatabaseHas('document_events', ['id' => $event->id, 'reason' => null]);
    }

    public function test_history_view_is_authorized_paginated_and_newest_first(): void
    {
        [$document, $career, $owner] = $this->context();
        $unrelated = $this->userWithRole(RoleName::SECRETARY, $career);
        $extra = $this->userWithRole(RoleName::EXTRA, $career);
        $service = app(DocumentEventService::class);

        for ($number = 1; $number <= 18; $number++) {
            $field = $number === 1 ? 'evento-mas-antiguo' : "campo {$number}";
            $service->record(
                $document,
                DocumentEventType::DRAFT_UPDATED,
                $owner,
                metadata: ['changed_fields' => [$field]],
            );
        }

        $this->actingAs($owner)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('Historial documental')
            ->assertSee('campo 18')
            ->assertDontSee('evento-mas-antiguo');
        $this->actingAs($owner)->get(route('documents.show', $document, false).'?historial=2')
            ->assertOk()
            ->assertSee('evento-mas-antiguo');
        $this->actingAs($unrelated)->get(route('documents.show', $document))->assertForbidden();
        $this->actingAs($extra)->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_sent_history_pagination_keeps_the_section_open_and_anchored(): void
    {
        [$document, $career, $owner] = $this->context();
        for ($number = 1; $number <= 18; $number++) {
            app(DocumentEventService::class)->record($document, DocumentEventType::DRAFT_UPDATED, $owner, metadata: ['changed_fields' => ['campo '.$number]]);
        }
        $document->forceFill(['general_status' => DocumentStatus::SENT, 'sent_at' => now(), 'code' => 'FTEC-HISTORY-2026-000001'])->save();

        $response = $this->actingAs($owner)->get(route('documents.show', $document, false).'?historial=2');
        $response->assertOk()->assertSee('historial=1#history-title', false);
        $this->assertMatchesRegularExpression('/<details[^>]*data-document-history[^>]*\sopen(?:\s|>)/', $response->getContent());
    }

    /** @return array{Document, OrganizationalUnit, User} */
    private function context(): array
    {
        $career = $this->career();
        $actor = $this->userWithRole(RoleName::SECRETARY, $career);

        return [$this->draft($actor, $career), $career, $actor];
    }

    /** @return array<string, mixed> */
    private function documentData(string $subject): array
    {
        return [
            'document_type_id' => DocumentType::query()->where('code', 'informe')->sole()->id,
            'subject' => $subject,
            'description' => null,
            'priority' => DocumentPriority::NORMAL->value,
            'confidentiality' => DocumentConfidentiality::NORMAL->value,
            'due_at' => null,
        ];
    }

    private function draft(User $creator, OrganizationalUnit $origin): Document
    {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('code', 'informe')->sole()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => 'Historial '.Str::random(8),
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
        ]);
    }

    private function recipient(Document $document, OrganizationalUnit $unit): DocumentRecipient
    {
        return DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'requires_response' => true,
            'status' => DocumentRecipientStatus::PREPARED,
            'lock_version' => 0,
        ]);
    }

    private function pdf(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'history-test-');
        file_put_contents($path, "%PDF-1.4\nhistorial");
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, 'application/pdf', null, true);
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
            'name' => 'Carrera Historial '.Str::random(8),
            'acronym' => Str::upper(Str::random(5)),
            'is_active' => true,
        ]);
    }
}
