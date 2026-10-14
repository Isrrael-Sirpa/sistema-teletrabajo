<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentAccessGrant;
use App\Models\DocumentEvent;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AuthorizationSeeder::class, DocumentTypeSeeder::class]);
    }

    public function test_administrator_sees_global_operational_summary_and_links(): void
    {
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $career = $this->career('Sistemas', 'SIS');
        $creator = $this->user(RoleName::SECRETARY, $career);
        $this->document($creator, $career, DocumentStatus::SENT);
        $this->document($creator, $career, DocumentStatus::IN_REVIEW);
        $this->document($creator, $career, DocumentStatus::RETURNED);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()->assertSee('Ámbito institucional')->assertSee('Usuarios activos');
        $this->assertSame(1, $this->cardCount($response->viewData('cards'), 'Documentos enviados pendientes'));
        $this->assertSame(1, $this->cardCount($response->viewData('cards'), 'Documentos en revisión'));
        $this->assertSame(1, $this->cardCount($response->viewData('cards'), 'Documentos devueltos'));
        $response->assertSee(route('documents.index', ['status' => 'sent']), false);
    }

    public function test_co_administrator_uses_the_global_dashboard(): void
    {
        $coAdmin = $this->user(RoleName::CO_ADMINISTRATOR);

        $this->actingAs($coAdmin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ámbito institucional')
            ->assertSee('Carreras activas')
            ->assertSee('Gestionar usuarios');
    }

    public function test_head_dashboard_is_limited_to_its_career_and_participation(): void
    {
        $careerA = $this->career('Electrónica', 'ELE');
        $careerB = $this->career('Mecánica', 'MEC');
        $head = $this->user(RoleName::CAREER_HEAD, $careerA);
        $creator = $this->user(RoleName::SECRETARY, $careerB);
        $visible = $this->document($creator, $careerB, DocumentStatus::SENT, subject: 'Visible para Electrónica');
        $this->recipient($visible, $careerA, DocumentRecipientStatus::SENT);
        $hidden = $this->document($creator, $careerB, DocumentStatus::SENT, subject: 'Solo Mecánica');
        $this->recipient($hidden, $careerB, DocumentRecipientStatus::SENT);

        $response = $this->actingAs($head)->get(route('dashboard'));

        $response->assertOk()->assertSee($careerA->name)->assertSee($visible->subject)->assertDontSee($hidden->subject);
        $this->assertSame(1, $this->cardCount($response->viewData('cards'), 'Pendientes de recibir'));
    }

    public function test_secretary_sees_only_permitted_daily_work_and_own_drafts(): void
    {
        $career = $this->career('Industrial', 'IND');
        $secretary = $this->user(RoleName::SECRETARY, $career);
        $other = $this->user(RoleName::SECRETARY, $career);
        $ownDraft = $this->document($secretary, $career, DocumentStatus::DRAFT, subject: 'Mi borrador operativo');
        $otherDraft = $this->document($other, $career, DocumentStatus::DRAFT, subject: 'Borrador ajeno');

        $response = $this->actingAs($secretary)->get(route('dashboard'));

        $response->assertOk()->assertDontSee($otherDraft->subject);
        $this->assertSame(1, $this->cardCount($response->viewData('cards'), 'Mis borradores'));
        $this->assertNotNull(collect($response->viewData('cards'))->firstWhere('label', 'Requieren decisión'));
    }

    public function test_assistant_has_collaboration_and_assignment_without_formal_decisions(): void
    {
        $origin = $this->career('Civil', 'CIV');
        $destination = $this->career('Construcciones', 'CON');
        $assistant = $this->user(RoleName::SECRETARY_ASSISTANT, $destination);
        $creator = $this->user(RoleName::SECRETARY, $origin);
        $document = $this->document($creator, $origin, DocumentStatus::IN_REVIEW, subject: 'Revisión auxiliar');
        $this->recipient($document, $destination, DocumentRecipientStatus::IN_REVIEW, handler: $assistant);

        $response = $this->actingAs($assistant)->get(route('dashboard'));
        $labels = collect($response->viewData('cards'))->pluck('label');

        $response->assertOk()->assertSee($document->subject)->assertDontSee('Puede aprobar, devolver o rechazar');
        $this->assertTrue($labels->contains('Asignados a mí'));
        $this->assertFalse($labels->contains('Requieren decisión'));
        $this->assertSame(1, $this->cardCount($response->viewData('cards'), 'Asignados a mí'));
    }

    public function test_extra_sees_only_documents_with_an_effective_explicit_grant(): void
    {
        $origin = $this->career('Geología', 'GEO');
        $extra = $this->user(RoleName::EXTRA, $this->career('Apoyo', 'APO'));
        $creator = $this->user(RoleName::SECRETARY, $origin);
        $granted = $this->document($creator, $origin, DocumentStatus::SENT, subject: 'Documento autorizado');
        $expired = $this->document($creator, $origin, DocumentStatus::SENT, subject: 'Documento vencido de acceso');
        $hidden = $this->document($creator, $origin, DocumentStatus::SENT, subject: 'Documento no autorizado');
        $grant = $this->grant($granted, $extra);
        $this->grant($expired, $extra, now()->subDays(2), now()->subDay());

        $response = $this->actingAs($extra)->get(route('dashboard'));

        $response->assertOk()->assertSee('Documentos autorizados recientes')->assertSee($granted->subject);
        $response->assertDontSee($expired->subject)->assertDontSee($hidden->subject)->assertDontSee('Usuarios activos');
        $this->assertSame(1, $this->cardCount($response->viewData('cards'), 'Documentos autorizados'));
        $this->actingAs($extra)->get(route('documents.show', $granted))->assertOk();
        $this->actingAs($extra)->get(route('documents.show', $hidden))->assertForbidden();
        $this->assertFalse(Gate::forUser($extra)->allows('downloadFile', $granted));
        $grant->update(['permission' => 'download']);
        $this->assertTrue(Gate::forUser($extra)->allows('downloadFile', $granted));
    }

    public function test_restricted_subject_is_masked_in_global_dashboard_summary(): void
    {
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $career = $this->career('Química', 'QUI');
        $creator = $this->user(RoleName::SECRETARY, $career);
        $document = $this->document(
            $creator,
            $career,
            DocumentStatus::SENT,
            DocumentConfidentiality::RESTRICTED,
            'Asunto altamente sensible',
        );

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Documento de acceso restringido')
            ->assertDontSee($document->subject);
    }

    public function test_pending_decision_and_review_counts_exclude_terminal_recipients(): void
    {
        $origin = $this->career('Ambiental', 'AMB');
        $destination = $this->career('Alimentos', 'ALI');
        $secretary = $this->user(RoleName::SECRETARY, $destination);
        $creator = $this->user(RoleName::SECRETARY, $origin);

        foreach ([
            DocumentRecipientStatus::SENT,
            DocumentRecipientStatus::RECEIVED,
            DocumentRecipientStatus::IN_REVIEW,
            DocumentRecipientStatus::APPROVED,
            DocumentRecipientStatus::REJECTED,
        ] as $status) {
            $general = match ($status) {
                DocumentRecipientStatus::SENT => DocumentStatus::SENT,
                DocumentRecipientStatus::RECEIVED => DocumentStatus::RECEIVED,
                DocumentRecipientStatus::IN_REVIEW => DocumentStatus::IN_REVIEW,
                DocumentRecipientStatus::APPROVED => DocumentStatus::APPROVED,
                DocumentRecipientStatus::REJECTED => DocumentStatus::REJECTED,
                default => DocumentStatus::SENT,
            };
            $document = $this->document($creator, $origin, $general, subject: 'Estado '.$status->value);
            $this->recipient($document, $destination, $status);
        }

        $cards = $this->actingAs($secretary)->get(route('dashboard'))->viewData('cards');

        $this->assertSame(1, $this->cardCount($cards, 'Pendientes de recibir'));
        $this->assertSame(1, $this->cardCount($cards, 'En revisión'));
        $this->assertSame(2, $this->cardCount($cards, 'Requieren decisión'));
    }

    public function test_overdue_and_due_soon_counts_reuse_deadline_rules(): void
    {
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $career = $this->career('Eléctrica', 'ELC');
        $creator = $this->user(RoleName::SECRETARY, $career);
        $this->document($creator, $career, DocumentStatus::SENT, dueAt: now()->subDay());
        $this->document($creator, $career, DocumentStatus::IN_REVIEW, dueAt: now()->addDay());
        $this->document($creator, $career, DocumentStatus::APPROVED, dueAt: now()->subDay());

        $cards = $this->actingAs($admin)->get(route('dashboard'))->viewData('cards');

        $this->assertSame(1, $this->cardCount($cards, 'Vencidos'));
        $this->assertSame(1, $this->cardCount($cards, 'Próximos a vencer'));
    }

    public function test_returned_document_distinguishes_a_prepared_unsent_correction(): void
    {
        $career = $this->career('Metalurgia', 'MET');
        $secretary = $this->user(RoleName::SECRETARY, $career);
        $document = $this->document($secretary, $career, DocumentStatus::RETURNED, subject: 'Corrección pendiente');
        DocumentEvent::query()->create([
            'document_id' => $document->id,
            'actor_id' => $secretary->id,
            'event_type' => DocumentEventType::DOCUMENT_VERSION_CREATED,
            'from_status' => DocumentStatus::RETURNED->value,
            'to_status' => DocumentStatus::RETURNED->value,
            'metadata' => ['version' => 2],
            'occurred_at' => now(),
        ]);

        $this->actingAs($secretary)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($document->subject)
            ->assertSee('CORRECCIÓN PREPARADA · FALTA ENVIAR');
    }

    public function test_card_links_apply_equivalent_authorized_filters(): void
    {
        $origin = $this->career('Textil', 'TEX');
        $destination = $this->career('Autotrónica', 'AUT');
        $head = $this->user(RoleName::CAREER_HEAD, $destination);
        $creator = $this->user(RoleName::SECRETARY, $origin);
        $pending = $this->document($creator, $origin, DocumentStatus::SENT, subject: 'Debe recibirse');
        $review = $this->document($creator, $origin, DocumentStatus::IN_REVIEW, subject: 'Ya está en revisión');
        $this->recipient($pending, $destination, DocumentRecipientStatus::SENT);
        $this->recipient($review, $destination, DocumentRecipientStatus::IN_REVIEW);

        $this->actingAs($head)->get(route('documents.index', ['work' => 'pending_receive']))
            ->assertOk()->assertSee($pending->subject)->assertDontSee($review->subject)->assertSee('Vista de trabajo: Pendientes de recibir');
        $this->actingAs($head)->get(route('documents.index', ['status' => 'in_review']))
            ->assertOk()->assertSee($review->subject)->assertDontSee($pending->subject);
    }

    public function test_unread_notifications_are_counted_once_for_the_dashboard(): void
    {
        $user = $this->user(RoleName::EXTRA, $this->career('Biblioteca', 'BIB'));
        $this->notification($user, false);
        $this->notification($user, false);
        $this->notification($user, true);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $this->assertSame(2, $response->viewData('unreadNotificationCount'));
        $this->assertSame(2, $this->cardCount($response->viewData('cards'), 'Notificaciones sin leer'));
        $response->assertSee('aria-label="Notificaciones: 2 sin leer"', false);
    }

    public function test_dashboard_has_a_clear_empty_state(): void
    {
        $extra = $this->user(RoleName::EXTRA, $this->career('Archivo', 'ARC'));

        $this->actingAs($extra)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No tiene documentos pendientes por atender.');
    }

    public function test_attention_list_orders_and_limits_in_sql_without_n_plus_one(): void
    {
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $career = $this->career('Topografía', 'TOP');
        $creator = $this->user(RoleName::SECRETARY, $career);
        $urgent = $this->document($creator, $career, DocumentStatus::SENT, priority: DocumentPriority::URGENT, subject: 'Urgente visible');
        $overdue = $this->document($creator, $career, DocumentStatus::SENT, dueAt: now()->subDay(), subject: 'Vencido primero');
        foreach (range(1, 7) as $number) {
            $this->document($creator, $career, DocumentStatus::SENT, subject: 'Normal '.$number);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($admin)->get(route('dashboard'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $attention = $response->viewData('attention');

        $this->assertCount(5, $attention);
        $this->assertSame($overdue->id, $attention->first()['document']->id);
        $this->assertSame($urgent->id, $attention->get(1)['document']->id);
        $this->assertLessThanOrEqual(16, count($queries));
    }

    private function cardCount(array $cards, string $label): int
    {
        return (int) collect($cards)->firstWhere('label', $label)['count'];
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
        ?string $subject = null,
        ?\DateTimeInterface $dueAt = null,
        DocumentPriority $priority = DocumentPriority::NORMAL,
    ): Document {
        $official = $status !== DocumentStatus::DRAFT;

        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'sender_user_id' => $official ? $creator->id : null,
            'subject' => $subject ?? 'Documento '.Str::random(8),
            'general_status' => $status,
            'priority' => $priority,
            'confidentiality' => $confidentiality,
            'due_at' => $dueAt,
            'code' => $official ? 'FTEC-'.$origin->acronym.'-2026-'.Str::upper(Str::random(8)) : null,
            'sent_at' => $official ? now()->subDay() : null,
            'lock_version' => $official ? 1 : 0,
        ]);
    }

    private function recipient(
        Document $document,
        OrganizationalUnit $unit,
        DocumentRecipientStatus $status,
        ?User $handler = null,
    ): DocumentRecipient {
        return DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'requires_response' => true,
            'status' => $status,
            'current_handler_id' => $handler?->id,
            'lock_version' => 1,
        ]);
    }

    private function grant(Document $document, User $user, $startsAt = null, $endsAt = null): DocumentAccessGrant
    {
        return DocumentAccessGrant::query()->create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'granted_by' => $document->created_by,
            'permission' => 'view',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => 'Acceso operativo autorizado',
        ]);
    }

    private function notification(User $user, bool $read): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => '{}',
            'read_at' => $read ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
