<?php

namespace Tests\Feature;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentAccessGrant;
use App\Models\DocumentEvent;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\TemporaryDelegationService;
use Carbon\CarbonImmutable;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-10-12 10:00:00');
        $this->seed([AuthorizationSeeder::class, DocumentTypeSeeder::class]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_searches_exact_official_code_and_partial_text_case_insensitively(): void
    {
        [$admin, $career, $creator] = $this->globalContext();
        $code = $this->document($creator, $career, 'Acta ordinaria', description: 'Resolución Académica Especial');
        $code->forceFill(['code' => 'FTEC-SIS-2026-000125'])->save();
        $subject = $this->document($creator, $career, 'Programa de MOVILIDAD estudiantil');
        $description = $this->document($creator, $career, 'Otro asunto', description: 'Incluye cooperación internacional');
        $this->document($creator, $career, 'Documento sin coincidencia');

        $this->assertResultIds($admin, ['q' => ' ftec-sis-2026-000125 '], [$code]);
        $this->assertResultIds($admin, ['q' => 'movilidad'], [$subject]);
        $this->assertResultIds($admin, ['q' => 'COOPERACIÓN'], [$description]);
    }

    public function test_filters_document_fields_type_origin_creator_sender_and_confidentiality(): void
    {
        [$admin, $career, $creator] = $this->globalContext();
        $otherCareer = $this->career('Diseño', 'DIS');
        $otherCreator = $this->user(RoleName::SECRETARY, $otherCareer);
        $type = DocumentType::query()->where('code', 'memorandum')->firstOrFail();
        $match = $this->document(
            $creator,
            $career,
            'Coincidencia estructurada',
            DocumentStatus::IN_REVIEW,
            DocumentPriority::URGENT,
            DocumentConfidentiality::RESTRICTED,
            type: $type,
        );
        $this->document($otherCreator, $otherCareer, 'Documento diferente', DocumentStatus::SENT);

        $this->assertResultIds($admin, [
            'status' => 'in_review',
            'priority' => 'urgent',
            'confidentiality' => 'restricted',
            'document_type' => $type->public_id,
            'origin_unit' => $career->public_id,
            'creator' => $creator->public_id,
            'sender' => $creator->public_id,
        ], [$match]);
    }

    public function test_filters_recipient_unit_person_and_status_without_duplicates(): void
    {
        [$admin, $origin, $creator] = $this->globalContext();
        $destination = $this->career('Telecomunicaciones', 'TEL');
        $person = $this->user(RoleName::SECRETARY, $destination);
        $match = $this->document($creator, $origin, 'Destinatario buscado');
        $this->recipient($match, $destination, DocumentRecipientStatus::IN_REVIEW, $person);
        $this->recipient($match, $destination, DocumentRecipientStatus::IN_REVIEW);
        $other = $this->document($creator, $origin, 'Otro destinatario');
        $this->recipient($other, $origin, DocumentRecipientStatus::SENT);

        $this->assertResultIds($admin, [
            'recipient_unit' => $destination->public_id,
            'recipient_user' => $person->public_id,
            'recipient_status' => 'in_review',
        ], [$match]);
    }

    public function test_filters_sent_due_and_created_date_ranges_including_complete_end_day(): void
    {
        [$admin, $career, $creator] = $this->globalContext();
        $match = $this->document($creator, $career, 'Dentro de fechas', dueAt: '2026-10-20 23:30:00');
        $match->forceFill([
            'sent_at' => CarbonImmutable::parse('2026-10-10 22:00:00'),
            'created_at' => CarbonImmutable::parse('2026-10-05 23:59:00'),
        ])->save();
        $outside = $this->document($creator, $career, 'Fuera de fechas', dueAt: '2026-10-21 00:01:00');
        $outside->forceFill([
            'sent_at' => CarbonImmutable::parse('2026-10-11 00:01:00'),
            'created_at' => CarbonImmutable::parse('2026-10-06 00:01:00'),
        ])->save();

        $this->assertResultIds($admin, [
            'sent_from' => '2026-10-10', 'sent_to' => '2026-10-10',
            'due_from' => '2026-10-20', 'due_to' => '2026-10-20',
            'created_from' => '2026-10-05', 'created_to' => '2026-10-05',
        ], [$match]);
    }

    public function test_filters_deadline_conditions_using_shared_business_rules(): void
    {
        [$admin, $career, $creator] = $this->globalContext();
        $overdue = $this->document($creator, $career, 'Vencido', dueAt: now()->subMinute());
        $soon = $this->document($creator, $career, 'Próximo', dueAt: now()->addDay());
        $future = $this->document($creator, $career, 'Con fecha futura', dueAt: now()->addMonth());
        $none = $this->document($creator, $career, 'Sin fecha', dueAt: null);
        $closed = $this->document($creator, $career, 'Cerrado vencido', DocumentStatus::APPROVED, dueAt: now()->subDay());

        $this->assertResultIds($admin, ['deadline' => 'overdue'], [$overdue]);
        $this->assertResultIds($admin, ['deadline' => 'due_soon'], [$soon]);
        $this->assertResultIds($admin, ['deadline' => 'has_due'], [$overdue, $soon, $future, $closed]);
        $this->assertResultIds($admin, ['deadline' => 'no_due'], [$none]);
    }

    public function test_filters_pending_correction_and_minimum_version_from_existing_history(): void
    {
        [$admin, $career, $creator] = $this->globalContext();
        $pending = $this->document($creator, $career, 'Corrección preparada', DocumentStatus::RETURNED);
        $sent = $this->document($creator, $career, 'Corrección ya enviada', DocumentStatus::RETURNED);
        foreach ([$pending, $sent] as $document) {
            DocumentVersion::query()->create([
                'document_id' => $document->id,
                'version_number' => 3,
                'uploaded_by' => $creator->id,
                'note' => 'Corrección',
            ]);
            $this->event($document, $creator, DocumentEventType::DOCUMENT_VERSION_CREATED);
        }
        $this->event($sent, $creator, DocumentEventType::CORRECTION_SENT);

        $this->assertResultIds($admin, ['correction_pending' => '1', 'version_min' => 3], [$pending]);
        $this->assertResultIds($admin, ['version_min' => 4], []);
    }

    public function test_combines_multiple_filters_with_and_semantics(): void
    {
        [$admin, $career, $creator] = $this->globalContext();
        $match = $this->document($creator, $career, 'Plan anual de laboratorios', priority: DocumentPriority::IMPORTANT);
        $this->document($creator, $career, 'Plan anual de laboratorios', priority: DocumentPriority::NORMAL);
        $this->document($creator, $career, 'Tema distinto', priority: DocumentPriority::IMPORTANT);

        $this->assertResultIds($admin, [
            'q' => 'laboratorios',
            'priority' => 'important',
            'status' => 'sent',
            'origin_unit' => $career->public_id,
        ], [$match]);
    }

    public function test_applies_only_whitelisted_stable_sorting(): void
    {
        [$admin, $career, $creator] = $this->globalContext();
        $normal = $this->document($creator, $career, 'Normal', priority: DocumentPriority::NORMAL);
        $urgent = $this->document($creator, $career, 'Urgente', priority: DocumentPriority::URGENT);
        $important = $this->document($creator, $career, 'Importante', priority: DocumentPriority::IMPORTANT);
        $normal->forceFill(['created_at' => now()->subDays(2)])->save();
        $important->forceFill(['created_at' => now()->subDay()])->save();

        $response = $this->actingAs($admin)->get(route('documents.index', ['sort' => 'priority', 'direction' => 'asc']));
        $this->assertSame([$normal->id, $important->id, $urgent->id], $response->viewData('documents')->pluck('id')->all());
        $oldest = $this->actingAs($admin)->get(route('documents.index', ['sort' => 'created_at', 'direction' => 'asc']));
        $newest = $this->actingAs($admin)->get(route('documents.index', ['sort' => 'created_at', 'direction' => 'desc']));
        $this->assertSame([$normal->id, $important->id, $urgent->id], $oldest->viewData('documents')->pluck('id')->all());
        $this->assertSame([$urgent->id, $important->id, $normal->id], $newest->viewData('documents')->pluck('id')->all());

        $this->actingAs($admin)->get(route('documents.index', ['sort' => 'subject; DROP TABLE users', 'direction' => 'sideways']))
            ->assertRedirect()->assertSessionHasErrors(['sort', 'direction']);
    }

    public function test_preserves_filters_in_pagination_and_offers_a_clear_action(): void
    {
        [$admin, $career, $creator] = $this->globalContext();
        foreach (range(1, 17) as $number) {
            $this->document($creator, $career, 'Informe seriado '.$number);
        }

        $response = $this->actingAs($admin)->get(route('documents.index', ['q' => 'Informe seriado', 'page' => 1]));
        $paginator = $response->viewData('documents');

        $response->assertOk()->assertSee('17 documentos encontrados')->assertSee('Limpiar filtros');
        $this->assertStringContainsString('q=Informe%20seriado', (string) $paginator->nextPageUrl());
        $this->assertSame(15, $paginator->count());
    }

    public function test_global_and_career_scopes_are_applied_before_search(): void
    {
        $careerA = $this->career('Sistemas', 'SIS');
        $careerB = $this->career('Industrial', 'IND');
        $creatorA = $this->user(RoleName::SECRETARY, $careerA);
        $creatorB = $this->user(RoleName::SECRETARY, $careerB);
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $head = $this->user(RoleName::CAREER_HEAD, $careerA);
        $own = $this->document($creatorA, $careerA, 'Clave ámbito compartida');
        $foreign = $this->document($creatorB, $careerB, 'Clave ámbito compartida');

        $this->assertResultIds($admin, ['q' => 'ámbito compartida'], [$own, $foreign]);
        $this->assertResultIds($head, ['q' => 'ámbito compartida'], [$own]);
    }

    public function test_filter_options_come_only_from_visible_documents_and_include_visible_foreign_origins(): void
    {
        $ownCareer = $this->career('Destino visible', 'DVI');
        $foreignCareer = $this->career('Origen visible', 'OVI');
        $hiddenCareer = $this->career('Origen oculto', 'OCO');
        $head = $this->user(RoleName::CAREER_HEAD, $ownCareer);
        $visibleCreator = $this->user(RoleName::SECRETARY, $foreignCareer);
        $hiddenCreator = $this->user(RoleName::SECRETARY, $hiddenCareer);
        $visible = $this->document($visibleCreator, $foreignCareer, 'Documento entrante visible');
        $this->recipient($visible, $ownCareer, DocumentRecipientStatus::SENT);
        $this->document($hiddenCreator, $hiddenCareer, 'Documento totalmente oculto');

        $response = $this->actingAs($head)->get(route('documents.index'));

        $response->assertOk()
            ->assertSee('Documento entrante visible')
            ->assertSee($foreignCareer->name)
            ->assertSee($visibleCreator->name)
            ->assertDontSee('Documento totalmente oculto')
            ->assertDontSee($hiddenCareer->name)
            ->assertDontSee($hiddenCreator->name);
        $this->assertTrue($response->viewData('units')->contains('id', $foreignCareer->id));
        $this->assertFalse($response->viewData('units')->contains('id', $hiddenCareer->id));
        $this->assertTrue($response->viewData('people')->contains('id', $visibleCreator->id));
        $this->assertFalse($response->viewData('people')->contains('id', $hiddenCreator->id));
    }

    public function test_secretary_assistant_and_personal_recipient_keep_their_visibility_rules(): void
    {
        $origin = $this->career('Origen', 'ORI');
        $destination = $this->career('Destino', 'DES');
        $personalDestination = $this->career('Destino personal', 'DPE');
        $creator = $this->user(RoleName::SECRETARY, $origin);
        $secretary = $this->user(RoleName::SECRETARY, $destination);
        $assistant = $this->user(RoleName::SECRETARY_ASSISTANT, $destination);
        $personal = $this->user(RoleName::SECRETARY, $personalDestination);
        $shared = $this->document($creator, $origin, 'Expediente localizado');
        $this->recipient($shared, $destination, DocumentRecipientStatus::SENT);
        $personalDocument = $this->document($creator, $origin, 'Expediente personal');
        $this->recipient($personalDocument, $personalDestination, DocumentRecipientStatus::SENT, $personal);
        $hidden = $this->document($creator, $origin, 'Expediente oculto');

        $this->assertResultIds($secretary, ['q' => 'Expediente'], [$shared]);
        $this->assertResultIds($assistant, ['q' => 'Expediente'], [$shared]);
        $this->assertResultIds($personal, ['q' => 'Expediente'], [$personalDocument]);
        $this->assertNotContains($hidden->id, [$shared->id, $personalDocument->id]);
    }

    public function test_extra_user_finds_only_documents_with_an_effective_grant(): void
    {
        $career = $this->career('Bibliotecología', 'BIB');
        $creator = $this->user(RoleName::SECRETARY, $career);
        $extra = $this->user(RoleName::EXTRA, $career);
        $granted = $this->document($creator, $career, 'Informe reservado concedido');
        $expired = $this->document($creator, $career, 'Informe reservado vencido');
        $hidden = $this->document($creator, $career, 'Informe reservado oculto');
        $this->recipient($hidden, $career, DocumentRecipientStatus::SENT, $extra);
        $this->grant($granted, $extra);
        $this->grant($expired, $extra, now()->subDays(2), now()->subDay());

        $this->assertResultIds($extra, ['q' => 'Informe reservado'], [$granted]);
        $this->assertNotSame($hidden->id, $granted->id);
    }

    public function test_active_delegation_expands_search_only_for_its_career(): void
    {
        $career = $this->career('Mecatrónica', 'MEC');
        $otherCareer = $this->career('Matemática', 'MAT');
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $head = $this->user(RoleName::CAREER_HEAD, $career);
        $delegate = $this->user(RoleName::SECRETARY, $career);
        $otherCreator = $this->user(RoleName::SECRETARY, $career);
        $foreignCreator = $this->user(RoleName::SECRETARY, $otherCareer);
        $local = $this->document($otherCreator, $career, 'Delegación local buscable');
        $foreign = $this->document($foreignCreator, $otherCareer, 'Delegación extranjera buscable');
        app(TemporaryDelegationService::class)->create($admin, [
            'organizational_unit_id' => $career->id,
            'delegator_user_id' => $head->id,
            'delegate_user_id' => $delegate->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'reason' => 'Cobertura de búsqueda documental.',
            'permissions' => [PermissionName::DOCUMENTS_VIEW->value],
        ]);

        $this->assertResultIds($delegate, ['q' => 'Delegación'], [$local]);
        $this->assertNotSame($foreign->id, $local->id);
    }

    public function test_restricted_invisible_and_soft_deleted_documents_never_leak_in_results_or_counts(): void
    {
        $careerA = $this->career('Arquitectura', 'ARQ');
        $careerB = $this->career('Química', 'QUI');
        $head = $this->user(RoleName::CAREER_HEAD, $careerA);
        $creatorA = $this->user(RoleName::SECRETARY, $careerA);
        $creatorB = $this->user(RoleName::SECRETARY, $careerB);
        $visible = $this->document($creatorA, $careerA, 'Palabra ultrasecreta visible');
        $invisible = $this->document($creatorB, $careerB, 'Palabra ultrasecreta invisible', confidentiality: DocumentConfidentiality::RESTRICTED);
        $deleted = $this->document($creatorA, $careerA, 'Palabra ultrasecreta descartada', DocumentStatus::DRAFT);
        $deleted->delete();

        $response = $this->actingAs($head)->get(route('documents.index', ['q' => 'ultrasecreta']));
        $response->assertOk()->assertSee($visible->subject)->assertDontSee($invisible->subject)->assertDontSee($deleted->subject)
            ->assertSee('1 documento encontrado');
        $this->assertSame(1, $response->viewData('documents')->total());
    }

    public function test_rejects_invalid_uuid_enum_boolean_dates_ranges_and_version(): void
    {
        $admin = $this->user(RoleName::ADMINISTRATOR);

        $this->actingAs($admin)->get(route('documents.index', [
            'document_type' => 'not-a-uuid',
            'origin_unit' => (string) Str::uuid(),
            'status' => 'unknown',
            'recipient_status' => 'unknown',
            'priority' => 'critical',
            'confidentiality' => 'secret',
            'deadline' => 'tomorrow',
            'correction_pending' => 'perhaps',
            'sent_from' => '2026-10-12',
            'sent_to' => '2026-10-11',
            'version_min' => 0,
        ]))->assertRedirect()->assertSessionHasErrors([
            'document_type', 'origin_unit', 'status', 'recipient_status', 'priority',
            'confidentiality', 'deadline', 'correction_pending', 'sent_to', 'version_min',
        ]);
    }

    public function test_text_search_treats_sql_wildcards_and_injection_payloads_as_literal_input(): void
    {
        [$admin, $career, $creator] = $this->globalContext();
        $this->document($creator, $career, 'Documento ordinario');
        $literal = $this->document($creator, $career, 'Cobertura 100% confirmada');

        $this->assertResultIds($admin, ['q' => '%'], [$literal]);
        $this->assertResultIds($admin, ['q' => "%' OR 1=1 --"], []);
        $this->assertDatabaseCount('documents', 2);
    }

    public function test_result_queries_do_not_grow_per_row(): void
    {
        [$admin, $career, $creator] = $this->globalContext();
        $this->document($creator, $career, 'Consulta estable 1');
        $firstCount = $this->queryCountFor($admin, ['q' => 'Consulta estable']);
        foreach (range(2, 8) as $number) {
            $this->document($creator, $career, 'Consulta estable '.$number);
        }
        $manyCount = $this->queryCountFor($admin, ['q' => 'Consulta estable']);

        $this->assertLessThanOrEqual($firstCount + 1, $manyCount);
    }

    /** @return array{User, OrganizationalUnit, User} */
    private function globalContext(): array
    {
        $career = $this->career('Sistemas', 'SIS');

        return [$this->user(RoleName::ADMINISTRATOR), $career, $this->user(RoleName::SECRETARY, $career)];
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
        string $subject,
        DocumentStatus $status = DocumentStatus::SENT,
        DocumentPriority $priority = DocumentPriority::NORMAL,
        DocumentConfidentiality $confidentiality = DocumentConfidentiality::NORMAL,
        string $description = '',
        CarbonImmutable|string|null $dueAt = null,
        ?DocumentType $type = null,
    ): Document {
        $official = $status !== DocumentStatus::DRAFT;
        $document = Document::query()->create([
            'document_type_id' => ($type ?? DocumentType::query()->where('is_active', true)->firstOrFail())->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'sender_user_id' => $official ? $creator->id : null,
            'subject' => $subject,
            'description' => $description,
            'general_status' => $status,
            'priority' => $priority,
            'confidentiality' => $confidentiality,
            'due_at' => $dueAt,
            'code' => $official ? 'FTEC-TST-2026-'.Str::upper(Str::random(6)) : null,
            'lock_version' => $official ? 1 : 0,
        ]);
        if ($official) {
            $document->forceFill(['sent_at' => now()->subHour()])->save();
        }

        return $document;
    }

    private function recipient(Document $document, OrganizationalUnit $unit, DocumentRecipientStatus $status, ?User $user = null): DocumentRecipient
    {
        return DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'user_id' => $user?->id,
            'requires_response' => true,
            'status' => $status,
            'lock_version' => 1,
        ]);
    }

    private function event(Document $document, User $actor, DocumentEventType $type): void
    {
        DocumentEvent::query()->create([
            'document_id' => $document->id,
            'actor_id' => $actor->id,
            'event_type' => $type,
            'from_status' => DocumentStatus::RETURNED->value,
            'to_status' => DocumentStatus::RETURNED->value,
            'metadata' => ['version' => 3],
            'occurred_at' => now(),
        ]);
    }

    private function grant(Document $document, User $user, $startsAt = null, $endsAt = null): void
    {
        DocumentAccessGrant::query()->create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'granted_by' => $document->created_by,
            'permission' => 'view',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => 'Acceso temporal para búsqueda.',
        ]);
    }

    /** @param array<string, mixed> $filters @param array<int, Document> $expected */
    private function assertResultIds(User $user, array $filters, array $expected): void
    {
        $response = $this->actingAs($user)->get(route('documents.index', $filters));
        $response->assertOk();
        $actual = $response->viewData('documents')->pluck('id')->sort()->values()->all();
        $ids = collect($expected)->pluck('id')->sort()->values()->all();
        $this->assertSame($ids, $actual);
        $this->assertSame(count($ids), $response->viewData('documents')->total());
    }

    /** @param array<string, mixed> $filters */
    private function queryCountFor(User $user, array $filters): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('documents.index', $filters))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
