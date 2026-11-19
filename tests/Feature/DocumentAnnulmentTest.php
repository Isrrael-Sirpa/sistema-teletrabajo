<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\DocumentAccessPermission;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentDeadlineStatus;
use App\Enums\DocumentEventType;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Events\DocumentNotificationRequested;
use App\Listeners\SendDocumentLifecycleNotifications;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentAccessGrant;
use App\Models\DocumentEvent;
use App\Models\DocumentFile;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentDeadlineReminderService;
use App\Services\DocumentDeadlineService;
use App\Services\TemporaryDelegationService;
use App\Support\CareerHeadDelegationCatalog;
use Carbon\CarbonImmutable;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentAnnulmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-12 10:00:00');
        $this->seed([
            AuthorizationSeeder::class,
            InstitutionalStructureSeeder::class,
            DocumentTypeSeeder::class,
        ]);
        Storage::fake('documentos');
        config(['documents.disk' => 'documentos']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[DataProvider('globalRoles')]
    public function test_administrator_and_co_administrator_can_annul_an_official_document(string $role): void
    {
        [$document] = $this->officialContext();
        $actor = $this->user(RoleName::from($role));
        $code = $document->code;
        $sentAt = $document->sent_at;

        $this->assertTrue($actor->can(PermissionName::DOCUMENTS_ANNUL->value));
        $this->assertTrue($actor->hasAnyRole([
            RoleName::ADMINISTRATOR->value,
            RoleName::CO_ADMINISTRATOR->value,
        ]));
        $this->assertNotNull($document->code);
        $this->assertNotNull($document->sent_at);
        $this->assertNull($document->deleted_at);
        $this->assertNotSame(DocumentStatus::DRAFT, $document->general_status);
        $this->assertNotSame(DocumentStatus::ANNULLED, $document->general_status);
        $this->assertTrue($actor->can('annul', $document));

        $this->annul($actor, $document, '  Emisión con error administrativo.  ')
            ->assertRedirect(route('documents.show', $document))
            ->assertSessionHas('status');

        $document->refresh();
        $this->assertSame(DocumentStatus::ANNULLED, $document->general_status);
        $this->assertSame($actor->id, $document->annulled_by);
        $this->assertSame('Emisión con error administrativo.', $document->annulment_reason);
        $this->assertNotNull($document->annulled_at);
        $this->assertSame(1, $document->lock_version);
        $this->assertSame($code, $document->code);
        $this->assertTrue($sentAt->equalTo($document->sent_at));
        $this->assertNull($document->deleted_at);
        $this->assertDatabaseHas('eventos_documentos', [
            'document_id' => $document->id,
            'event_type' => DocumentEventType::DOCUMENT_ANNULLED->value,
            'actor_id' => $actor->id,
            'from_status' => DocumentStatus::SENT->value,
            'to_status' => DocumentStatus::ANNULLED->value,
            'reason' => 'Emisión con error administrativo.',
        ]);
        $this->assertDatabaseHas('registros_auditoria', [
            'actor_id' => $actor->id,
            'action' => AuditAction::DOCUMENT_ANNULLED->value,
            'auditable_id' => $document->id,
        ]);
    }

    /** @return array<string, array{string}> */
    public static function globalRoles(): array
    {
        return [
            'Administrador' => [RoleName::ADMINISTRATOR->value],
            'Co-administrador' => [RoleName::CO_ADMINISTRATOR->value],
        ];
    }

    public function test_local_roles_extra_and_active_delegation_cannot_annul(): void
    {
        [$document, $career, $creator] = $this->officialContext();
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $head = $this->user(RoleName::CAREER_HEAD, $career);
        $assistant = $this->user(RoleName::SECRETARY_ASSISTANT, $career);
        $extra = $this->user(RoleName::EXTRA, $career);
        DocumentAccessGrant::query()->create([
            'document_id' => $document->id,
            'user_id' => $extra->id,
            'granted_by' => $admin->id,
            'permission' => DocumentAccessPermission::DOWNLOAD,
            'reason' => 'Consulta histórica autorizada.',
        ]);

        foreach ([$head, $creator, $assistant, $extra] as $actor) {
            $this->annul($actor, $document, 'Intento no autorizado.')->assertForbidden();
        }

        $delegate = $this->user(RoleName::SECRETARY, $career);
        app(TemporaryDelegationService::class)->create($admin, [
            'organizational_unit_id' => $career->id,
            'delegator_user_id' => $head->id,
            'delegate_user_id' => $delegate->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'reason' => 'Encargo temporal sin anulación.',
            'permissions' => [
                PermissionName::DOCUMENTS_VIEW->value,
            ],
        ]);
        $this->annul($delegate, $document, 'La delegación no debe permitirlo.')->assertForbidden();
        $this->assertSame(DocumentStatus::SENT, $document->fresh()->general_status);
        $this->assertFalse(CareerHeadDelegationCatalog::contains(PermissionName::DOCUMENTS_ANNUL));
    }

    public function test_draft_discarded_and_already_annulled_documents_cannot_be_annulled(): void
    {
        $career = $this->career('Estados no anulables');
        $creator = $this->user(RoleName::SECRETARY, $career);
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $draft = $this->document($creator, $career, DocumentStatus::DRAFT);

        $this->annul($admin, $draft, 'No corresponde.')->assertForbidden();
        $draft->delete();
        $this->annul($admin, $draft, 'Tampoco corresponde.')->assertNotFound();

        [$official] = $this->officialContext();
        $this->annul($admin, $official, 'Primera y única anulación.')->assertRedirect();
        $this->annul($admin, $official->fresh(), 'Segundo intento.')->assertForbidden();
        $this->assertSame(1, DocumentEvent::query()
            ->where('document_id', $official->id)
            ->where('event_type', DocumentEventType::DOCUMENT_ANNULLED->value)
            ->count());
    }

    #[DataProvider('annullableStatuses')]
    public function test_every_official_workflow_status_can_be_annulled(string $status): void
    {
        [$document] = $this->officialContext(DocumentStatus::from($status));
        $admin = $this->user(RoleName::ADMINISTRATOR);

        $this->annul($admin, $document, 'Decisión administrativa superior.')->assertRedirect();

        $this->assertSame(DocumentStatus::ANNULLED, $document->fresh()->general_status);
    }

    /** @return array<string, array{string}> */
    public static function annullableStatuses(): array
    {
        return [
            'enviado' => [DocumentStatus::SENT->value],
            'recibido' => [DocumentStatus::RECEIVED->value],
            'en revisión' => [DocumentStatus::IN_REVIEW->value],
            'aprobado' => [DocumentStatus::APPROVED->value],
            'devuelto' => [DocumentStatus::RETURNED->value],
            'rechazado' => [DocumentStatus::REJECTED->value],
        ];
    }

    public function test_annulment_reason_and_optimistic_version_are_validated(): void
    {
        config(['documents.workflow.annulment_reason_max_length' => 20]);
        [$document] = $this->officialContext();
        $admin = $this->user(RoleName::ADMINISTRATOR);

        $this->annul($admin, $document, '')->assertSessionHasErrors('reason');
        $this->annul($admin, $document, '     ')->assertSessionHasErrors('reason');
        $this->annul($admin, $document, str_repeat('a', 21))->assertSessionHasErrors('reason');
        $this->post(route('documents.annul', $document), [
            'document_lock_version' => 9,
            'reason' => 'Motivo válido.',
        ])->assertSessionHasErrors('document_lock_version');

        $document->refresh();
        $this->assertSame(DocumentStatus::SENT, $document->general_status);
        $this->assertNull($document->annulment_reason);
        $this->assertNull($document->annulled_at);
    }

    public function test_annulment_preserves_recipients_files_versions_history_grants_and_downloads(): void
    {
        [$document, $career, $creator] = $this->officialContext(DocumentStatus::RETURNED);
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $extra = $this->user(RoleName::EXTRA, $career);
        $approved = $this->recipient($document, $career, DocumentRecipientStatus::APPROVED, $creator);
        $returned = $this->recipient($document, $this->career('Destino devuelto'), DocumentRecipientStatus::RETURNED, $creator);
        $received = $this->recipient($document, $this->career('Destino recibido'), DocumentRecipientStatus::RECEIVED, $creator);
        $historicalEvent = DocumentEvent::query()->create([
            'document_id' => $document->id,
            'actor_id' => $creator->id,
            'event_type' => DocumentEventType::DOCUMENT_RETURNED,
            'from_status' => 'in_review',
            'to_status' => 'returned',
            'reason' => 'Corrección anterior.',
            'occurred_at' => now()->subHour(),
        ]);
        $versionOne = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'uploaded_by' => $creator->id,
        ]);
        $versionTwo = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 2,
            'uploaded_by' => $creator->id,
            'note' => 'Corrección preparada',
        ]);
        $path = $document->public_id.'/version-2/'.Str::uuid().'.pdf';
        Storage::disk('documentos')->put($path, '%PDF-1.4 historical');
        $file = DocumentFile::query()->create([
            'document_version_id' => $versionTwo->id,
            'disk' => 'documentos',
            'path' => $path,
            'original_name' => 'correccion.pdf',
            'stored_name' => basename($path),
            'extension' => 'pdf',
            'detected_mime_type' => 'application/pdf',
            'size_bytes' => Storage::disk('documentos')->size($path),
            'sha256' => hash('sha256', Storage::disk('documentos')->get($path)),
            'uploaded_by' => $creator->id,
        ]);
        DocumentEvent::query()->create([
            'document_id' => $document->id,
            'actor_id' => $creator->id,
            'event_type' => DocumentEventType::DOCUMENT_VERSION_CREATED,
            'from_status' => 'returned',
            'to_status' => 'returned',
            'metadata' => ['version' => 2, 'file_count' => 1],
            'occurred_at' => now()->subMinute(),
        ]);
        $grant = DocumentAccessGrant::query()->create([
            'document_id' => $document->id,
            'user_id' => $extra->id,
            'granted_by' => $admin->id,
            'permission' => DocumentAccessPermission::DOWNLOAD,
            'reason' => 'Consulta histórica.',
        ]);

        $this->annul($admin, $document, 'La corrección preparada ya no debe enviarse.')->assertRedirect();

        $this->assertSame(DocumentRecipientStatus::APPROVED, $approved->fresh()->status);
        $this->assertSame(DocumentRecipientStatus::RETURNED, $returned->fresh()->status);
        $this->assertSame(DocumentRecipientStatus::RECEIVED, $received->fresh()->status);
        $this->assertDatabaseHas('versiones_documentos', ['id' => $versionOne->id]);
        $this->assertDatabaseHas('versiones_documentos', ['id' => $versionTwo->id]);
        $this->assertDatabaseHas('archivos_documentos', ['id' => $file->id]);
        $this->assertTrue(Storage::disk('documentos')->exists($path));
        $this->assertDatabaseHas('eventos_documentos', ['id' => $historicalEvent->id]);
        $this->assertNull($grant->fresh()->revoked_at);
        $this->actingAs($extra)->get(route('documents.show', $document))->assertOk()->assertSee('DOCUMENTO ANULADO');
        $this->actingAs($extra)->get(route('documents.files.download', [$document, $file]))->assertDownload('correccion.pdf');
        $this->actingAs($creator)->post(route('documents.correction.send', $document), [
            'document_lock_version' => $document->fresh()->lock_version,
        ])->assertForbidden();
    }

    public function test_annulled_document_blocks_recipient_transitions_and_is_clearly_marked_in_ui(): void
    {
        [$document, $career] = $this->officialContext();
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $receiver = $this->user(RoleName::SECRETARY, $career);
        $recipient = $this->recipient($document, $career, DocumentRecipientStatus::SENT);

        $this->actingAs($admin)->get(route('documents.show', $document))
            ->assertOk()->assertSee('Anular documento')->assertSee('sin efecto institucional');
        $this->annul($admin, $document, 'Documento sustituido administrativamente.')->assertRedirect();

        $this->actingAs($receiver)->patch(route('documents.recipients.transition', [$document, $recipient, 'receive']), [
            'document_lock_version' => $document->fresh()->lock_version,
            'recipient_lock_version' => $recipient->lock_version,
        ])->assertForbidden();
        $this->actingAs($admin)->get(route('documents.show', $document))
            ->assertOk()
            ->assertSee('DOCUMENTO ANULADO')
            ->assertSee('Documento sustituido administrativamente.')
            ->assertDontSee('Recibir documento')
            ->assertDontSee('Anular documento', false);
    }

    public function test_annulment_dispatches_notifications_to_origin_and_formal_destinations(): void
    {
        Event::fake([DocumentNotificationRequested::class]);
        [$document, $origin, $creator] = $this->officialContext();
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $originHead = $this->user(RoleName::CAREER_HEAD, $origin);
        $destination = $this->career('Destino notificado');
        $destinationHead = $this->user(RoleName::CAREER_HEAD, $destination);
        $destinationSecretary = $this->user(RoleName::SECRETARY, $destination);
        $recipient = $this->recipient($document, $destination, DocumentRecipientStatus::SENT);

        $this->annul($admin, $document, 'Anulación para notificar.')->assertRedirect();
        Event::assertDispatched(
            DocumentNotificationRequested::class,
            fn ($event): bool => $event->documentId === $document->id
                && $event->eventType === DocumentEventType::DOCUMENT_ANNULLED
        );

        app(SendDocumentLifecycleNotifications::class)->handle(new DocumentNotificationRequested(
            $document->id,
            $admin->id,
            DocumentEventType::DOCUMENT_ANNULLED,
            occurredAt: now()->toIso8601String(),
        ));

        foreach ([$creator, $originHead, $destinationHead] as $notified) {
            $notification = $notified->notifications()->sole();
            $this->assertSame('Documento anulado', $notification->data['title']);
            $this->assertSame(DocumentEventType::DOCUMENT_ANNULLED->value, $notification->data['event_type']);
        }
        $this->assertSame(0, $destinationSecretary->notifications()->count());
        $this->assertSame(DocumentRecipientStatus::SENT, $recipient->fresh()->status);
    }

    public function test_restricted_annulment_notification_and_audit_do_not_copy_sensitive_content(): void
    {
        Event::fake([DocumentNotificationRequested::class]);
        [$document, $origin] = $this->officialContext(confidentiality: DocumentConfidentiality::RESTRICTED);
        $document->forceFill([
            'subject' => 'Asunto ultrasecreto no persistible',
            'description' => 'Contenido reservado no persistible',
        ])->save();
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $destination = $this->career('Destino restringido');
        $recipientUser = $this->user(RoleName::SECRETARY, $destination);
        $this->recipient($document, $destination, DocumentRecipientStatus::SENT, $recipientUser);

        $this->annul($admin, $document, 'Motivo administrativo permitido.')->assertRedirect();
        app(SendDocumentLifecycleNotifications::class)->handle(new DocumentNotificationRequested(
            $document->id,
            $admin->id,
            DocumentEventType::DOCUMENT_ANNULLED,
            occurredAt: now()->toIso8601String(),
        ));

        $notificationJson = json_encode($recipientUser->notifications()->sole()->data, JSON_THROW_ON_ERROR);
        $audit = AuditLog::query()->where('action', AuditAction::DOCUMENT_ANNULLED->value)->sole();
        $auditJson = json_encode([$audit->old_values, $audit->new_values], JSON_THROW_ON_ERROR);
        foreach ([$document->subject, $document->description, (string) $document->code] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $notificationJson);
        }
        $this->assertStringNotContainsString($document->subject, $auditJson);
        $this->assertStringNotContainsString($document->description, $auditJson);
        $this->assertStringNotContainsString((string) $document->code, $auditJson);
        $this->assertStringContainsString('Motivo administrativo permitido.', $auditJson);
        $this->assertSame($origin->id, $document->origin_unit_id);
    }

    public function test_annulled_document_leaves_dashboard_deadlines_search_and_reports_consistent(): void
    {
        [$document, $career, $creator] = $this->officialContext(dueAt: now()->subDay());
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $head = $this->user(RoleName::CAREER_HEAD, $career);
        $this->recipient($document, $career, DocumentRecipientStatus::SENT);

        $this->actingAs($head)->get(route('dashboard'))->assertOk()
            ->assertSee('Por recibir')->assertSee($document->subject);
        $this->annul($admin, $document, 'Cierre administrativo del trámite.')->assertRedirect();

        $this->assertSame(DocumentDeadlineStatus::NOT_APPLICABLE, app(DocumentDeadlineService::class)->status($document->fresh()));
        $this->assertSame(0, app(DocumentDeadlineReminderService::class)->process()['evaluated']);
        $dashboard = $this->actingAs($admin)->get(route('dashboard'));
        $cards = collect($dashboard->viewData('cards'));
        $this->assertSame(0, $cards->firstWhere('label', 'Documentos enviados pendientes')['count']);
        $this->assertSame(0, $cards->firstWhere('label', 'Vencidos')['count']);
        $this->assertSame(0, $cards->firstWhere('label', 'Próximos a vencer')['count']);
        $this->actingAs($head)->get(route('dashboard'))->assertOk()
            ->assertSee('No hay documentos pendientes para su unidad.')->assertDontSee($document->subject);
        $this->actingAs($head)->get(route('documents.index', ['work' => 'pending_receive']))
            ->assertOk()->assertDontSee($document->subject);
        $this->actingAs($admin)->get(route('documents.index', [
            'q' => $document->code,
            'status' => DocumentStatus::ANNULLED->value,
        ]))->assertOk()->assertSee($document->subject);
        $report = $this->actingAs($admin)->get(route('reports.documents.index', [
            'status' => DocumentStatus::ANNULLED->value,
        ]));
        $report->assertOk()->assertSee($document->subject)->assertSee('Anulado');
        $this->assertSame(1, collect($report->viewData('summaries')['statuses'])->sum('count'));
        $this->assertSame($creator->id, $document->created_by);
    }

    /** @return array{Document, OrganizationalUnit, User} */
    private function officialContext(
        DocumentStatus $status = DocumentStatus::SENT,
        DocumentConfidentiality $confidentiality = DocumentConfidentiality::NORMAL,
        CarbonImmutable|string|null $dueAt = null,
    ): array {
        $career = $this->career('Origen');
        $creator = $this->user(RoleName::SECRETARY, $career);

        return [$this->document($creator, $career, $status, $confidentiality, $dueAt), $career, $creator];
    }

    private function career(string $label): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();

        return OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => $label.' '.Str::random(8),
            'acronym' => Str::upper(Str::random(8)),
            'is_active' => true,
        ]);
    }

    private function user(RoleName $role, ?OrganizationalUnit $unit = null): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit?->id]);
        $user->assignRole($role->value);

        return $user;
    }

    private function document(
        User $creator,
        OrganizationalUnit $origin,
        DocumentStatus $status,
        DocumentConfidentiality $confidentiality = DocumentConfidentiality::NORMAL,
        CarbonImmutable|string|null $dueAt = null,
    ): Document {
        $official = $status !== DocumentStatus::DRAFT;

        $document = new Document;
        $document->forceFill([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'sender_user_id' => $official ? $creator->id : null,
            'subject' => 'Documento anulable '.Str::random(8),
            'description' => 'Descripción administrativa.',
            'general_status' => $status,
            'priority' => DocumentPriority::URGENT,
            'confidentiality' => $confidentiality,
            'due_at' => $dueAt,
            'code' => $official ? 'FTEC-ANU-2026-'.Str::upper(Str::random(8)) : null,
            'sent_at' => $official ? now()->subDays(2) : null,
            'lock_version' => 0,
        ])->save();

        return $document;
    }

    private function recipient(
        Document $document,
        OrganizationalUnit $unit,
        DocumentRecipientStatus $status,
        ?User $person = null,
    ): DocumentRecipient {
        $received = in_array($status, [
            DocumentRecipientStatus::RECEIVED,
            DocumentRecipientStatus::IN_REVIEW,
            DocumentRecipientStatus::APPROVED,
            DocumentRecipientStatus::RETURNED,
            DocumentRecipientStatus::REJECTED,
        ], true);
        $decided = in_array($status, [
            DocumentRecipientStatus::APPROVED,
            DocumentRecipientStatus::RETURNED,
            DocumentRecipientStatus::REJECTED,
        ], true);

        return DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'user_id' => $person?->id,
            'requires_response' => true,
            'status' => $status,
            'received_by' => $received ? ($person?->id ?? $document->created_by) : null,
            'received_at' => $received ? now()->subDay() : null,
            'current_handler_id' => $status === DocumentRecipientStatus::IN_REVIEW ? ($person?->id ?? $document->created_by) : null,
            'decided_by' => $decided ? ($person?->id ?? $document->created_by) : null,
            'decided_at' => $decided ? now()->subHour() : null,
            'decision_reason' => in_array($status, [DocumentRecipientStatus::RETURNED, DocumentRecipientStatus::REJECTED], true)
                ? 'Motivo histórico.'
                : null,
            'lock_version' => 1,
        ]);
    }

    private function annul(User $actor, Document $document, string $reason)
    {
        return $this->actingAs($actor)->post(route('documents.annul', $document), [
            'document_lock_version' => $document->lock_version,
            'reason' => $reason,
        ]);
    }
}
