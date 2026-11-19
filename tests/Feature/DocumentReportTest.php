<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentAccessGrant;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
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

class DocumentReportTest extends TestCase
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

    public function test_administrator_and_co_administrator_can_consult_global_reports(): void
    {
        $careerA = $this->career('Sistemas', 'SIS');
        $careerB = $this->career('Electrónica', 'ELE');
        $creatorA = $this->user(RoleName::SECRETARY, $careerA);
        $creatorB = $this->user(RoleName::SECRETARY, $careerB);
        $first = $this->document($creatorA, $careerA, 'Global A');
        $second = $this->document($creatorB, $careerB, 'Global B');

        foreach ([RoleName::ADMINISTRATOR, RoleName::CO_ADMINISTRATOR] as $role) {
            $response = $this->actingAs($this->user($role))->get(route('reports.documents.index'));
            $response->assertOk()->assertSee($first->subject)->assertSee($second->subject)->assertSee('Reportes');
            $this->assertSame(2, $response->viewData('documents')->total());
        }
    }

    public function test_head_secretary_and_assistant_remain_limited_to_document_visibility(): void
    {
        $own = $this->career('Industrial', 'IND');
        $foreign = $this->career('Química', 'QUI');
        $ownSecretary = $this->user(RoleName::SECRETARY, $own);
        $foreignSecretary = $this->user(RoleName::SECRETARY, $foreign);
        $head = $this->user(RoleName::CAREER_HEAD, $own);
        $secretary = $this->user(RoleName::SECRETARY, $own);
        $assistant = $this->user(RoleName::SECRETARY_ASSISTANT, $own);
        $ownDocument = $this->document($ownSecretary, $own, 'Visible por origen');
        $incoming = $this->document($foreignSecretary, $foreign, 'Visible por destino');
        $this->recipient($incoming, $own, DocumentRecipientStatus::SENT);
        $copy = $this->document($foreignSecretary, $foreign, 'Copia informativa');
        $this->recipient($copy, $own, DocumentRecipientStatus::SENT)->update(['requires_response' => false]);
        $hidden = $this->document($foreignSecretary, $foreign, 'Oculto sin participación');

        $this->assertReportIds($head, [$ownDocument, $incoming, $copy]);
        $this->assertReportIds($secretary, [$copy]);
        $this->assertReportIds($assistant, [$copy]);
        $this->assertNotSame($hidden->id, $incoming->id);
    }

    public function test_extra_cannot_access_reports_and_only_document_grants_provide_visibility(): void
    {
        $career = $this->career('Apoyo', 'APO');
        $creator = $this->user(RoleName::SECRETARY, $career);
        $extra = $this->user(RoleName::EXTRA, $career);
        $recipientOnly = $this->document($creator, $career, 'Extra anómalo como destinatario');
        $this->recipient($recipientOnly, $career, DocumentRecipientStatus::SENT, $extra);
        $granted = $this->document($creator, $career, 'Extra con acceso explícito');
        $this->grant($granted, $extra);

        $this->actingAs($extra)->get(route('reports.documents.index'))->assertForbidden();
        $this->actingAs($extra)->get(route('reports.documents.csv'))->assertForbidden();
        $this->actingAs($extra)->get(route('documents.index'))
            ->assertOk()->assertSee($granted->subject)->assertDontSee($recipientOnly->subject)->assertDontSee('Reportes');
    }

    public function test_active_delegation_expands_only_its_career_and_expiration_removes_it(): void
    {
        $career = $this->career('Mecatrónica', 'MEC');
        $foreign = $this->career('Geología', 'GEO');
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $head = $this->user(RoleName::CAREER_HEAD, $career);
        $delegate = $this->user(RoleName::SECRETARY, $career);
        $localCreator = $this->user(RoleName::SECRETARY, $career);
        $foreignCreator = $this->user(RoleName::SECRETARY, $foreign);
        $local = $this->document($localCreator, $career, 'Reporte delegado local');
        $outside = $this->document($foreignCreator, $foreign, 'Reporte delegado ajeno');
        $endsAt = now()->addHour();
        app(TemporaryDelegationService::class)->create($admin, [
            'organizational_unit_id' => $career->id,
            'delegator_user_id' => $head->id,
            'delegate_user_id' => $delegate->id,
            'starts_at' => now()->subHour(),
            'ends_at' => $endsAt,
            'reason' => 'Cobertura temporal de reportes.',
            'permissions' => [
                PermissionName::DOCUMENTS_VIEW->value,
                PermissionName::REPORTS_VIEW_UNIT->value,
                PermissionName::REPORTS_EXPORT->value,
            ],
        ]);

        $this->assertReportIds($delegate, [$local]);
        $this->assertNotSame($outside->id, $local->id);
        CarbonImmutable::setTestNow($endsAt);
        $this->assertReportIds($delegate, []);
    }

    public function test_restricted_and_manipulated_filters_never_reveal_an_invisible_document(): void
    {
        $own = $this->career('Civil', 'CIV');
        $foreign = $this->career('Arquitectura', 'ARQ');
        $head = $this->user(RoleName::CAREER_HEAD, $own);
        $creator = $this->user(RoleName::SECRETARY, $foreign);
        $secret = $this->document($creator, $foreign, '=Asunto reservado', confidentiality: DocumentConfidentiality::RESTRICTED);

        $response = $this->actingAs($head)->get(route('reports.documents.index', [
            'origin_unit' => $foreign->public_id,
            'confidentiality' => 'restricted',
        ]));

        $response->assertOk()->assertDontSee($secret->subject)->assertSee('0 documentos');
        $this->assertSame(0, $response->viewData('documents')->total());
        $this->assertSame(0, collect($response->viewData('summaries')['statuses'])->sum('count'));
    }

    public function test_sql_aggregates_status_unit_priority_and_type_without_duplicate_recipients(): void
    {
        $career = $this->career('Informática', 'INF');
        $destinationA = $this->career('Destino A', 'DEA');
        $destinationB = $this->career('Destino B', 'DEB');
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $creator = $this->user(RoleName::SECRETARY, $career);
        $type = DocumentType::query()->where('code', 'memorandum')->firstOrFail();
        $sent = $this->document($creator, $career, 'Enviado doble', DocumentStatus::SENT, DocumentPriority::URGENT, type: $type);
        $this->recipient($sent, $destinationA, DocumentRecipientStatus::SENT);
        $this->recipient($sent, $destinationB, DocumentRecipientStatus::SENT);
        $approved = $this->document($creator, $career, 'Aprobado único', DocumentStatus::APPROVED, DocumentPriority::NORMAL, type: $type);

        $summary = $this->actingAs($admin)->get(route('reports.documents.index'))->viewData('summaries');

        $this->assertSame(2, collect($summary['statuses'])->sum('count'));
        $this->assertSame(2, collect($summary['priorities'])->sum('count'));
        $this->assertSame(2, collect($summary['types'])->sum('count'));
        $unit = collect($summary['units'])->firstWhere('label', $career->name);
        $this->assertSame(2, $unit['total']);
        $this->assertSame(1, $unit['sent']);
        $this->assertSame(1, $unit['approved']);
    }

    public function test_report_periods_include_the_complete_local_end_day_for_sent_received_and_decided(): void
    {
        $career = $this->career('Telecomunicaciones', 'TEL');
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $creator = $this->user(RoleName::SECRETARY, $career);
        $sent = $this->document($creator, $career, 'Enviado al final del día');
        $sent->forceFill(['sent_at' => CarbonImmutable::parse('2026-10-10 23:55:00')])->save();
        $received = $this->document($creator, $career, 'Recibido al final del día', DocumentStatus::RECEIVED);
        $received->forceFill(['sent_at' => CarbonImmutable::parse('2026-10-10 22:00:00')])->save();
        $this->recipient($received, $career, DocumentRecipientStatus::RECEIVED, receivedAt: '2026-10-10 23:59:00');
        $approved = $this->document($creator, $career, 'Aprobado al final del día', DocumentStatus::APPROVED);
        $approved->forceFill(['sent_at' => CarbonImmutable::parse('2026-10-10 21:00:00')])->save();
        $this->recipient($approved, $career, DocumentRecipientStatus::APPROVED, decidedAt: '2026-10-10 23:58:00', decidedBy: $creator);
        $this->document($creator, $career, 'Fuera del período')->forceFill(['sent_at' => CarbonImmutable::parse('2026-10-11 00:01:00')])->save();

        $period = ['period_from' => '2026-10-10', 'period_to' => '2026-10-10'];
        $this->assertReportIds($admin, [...$period, 'report' => 'sent'], [$sent, $received, $approved]);
        $this->assertReportIds($admin, [...$period, 'report' => 'received'], [$received]);
        $this->assertReportIds($admin, [...$period, 'report' => 'approved'], [$approved]);
    }

    public function test_overdue_due_soon_and_combined_filters_reuse_document_rules(): void
    {
        $career = $this->career('Ambiental', 'AMB');
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $creator = $this->user(RoleName::SECRETARY, $career);
        $overdue = $this->document($creator, $career, 'Urgente vencido', dueAt: now()->subMinute(), priority: DocumentPriority::URGENT);
        $soon = $this->document($creator, $career, 'Normal próximo', dueAt: now()->addDay());
        $this->document($creator, $career, 'Cerrado antiguo', DocumentStatus::APPROVED, dueAt: now()->subDay());

        $this->assertReportIds($admin, ['report' => 'overdue'], [$overdue]);
        $this->assertReportIds($admin, ['report' => 'due_soon'], [$soon]);
        $this->assertReportIds($admin, ['report' => 'overdue', 'priority' => 'urgent', 'origin_unit' => $career->public_id], [$overdue]);
    }

    public function test_detail_is_paginated_and_preserves_query_string(): void
    {
        config(['reports.per_page' => 5]);
        $career = $this->career('Textil', 'TEX');
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $creator = $this->user(RoleName::SECRETARY, $career);
        foreach (range(1, 7) as $number) {
            $this->document($creator, $career, 'Serie reportable '.$number, priority: DocumentPriority::IMPORTANT);
        }

        $response = $this->actingAs($admin)->get(route('reports.documents.index', ['priority' => 'important']));
        $documents = $response->viewData('documents');
        $response->assertOk()->assertSee('7 documentos');
        $this->assertSame(5, $documents->count());
        $this->assertStringContainsString('priority=important', (string) $documents->nextPageUrl());
    }

    public function test_csv_uses_same_filters_is_utf8_and_mitigates_formula_injection(): void
    {
        $career = $this->career('Alimentos', 'ALI');
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $creator = $this->user(RoleName::SECRETARY, $career);
        $dangerous = $this->document($creator, $career, '=SUM(1,1) Ácido', priority: DocumentPriority::URGENT);
        $this->document($creator, $career, 'No exportar', priority: DocumentPriority::NORMAL);

        $response = $this->actingAs($admin)->get(route('reports.documents.csv', ['priority' => 'urgent']));
        $content = $response->streamedContent();

        $response->assertOk()->assertDownload();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString("'=SUM(1,1) Ácido", $content);
        $this->assertStringContainsString($dangerous->code, $content);
        $this->assertStringNotContainsString('No exportar', $content);
        $this->assertTrue(mb_check_encoding($content, 'UTF-8'));
    }

    public function test_export_limit_is_enforced_before_generation(): void
    {
        config(['reports.export_max_rows' => 1]);
        [$admin, $career, $creator] = $this->context();
        $this->document($creator, $career, 'Primero');
        $this->document($creator, $career, 'Segundo');

        $this->actingAs($admin)->get(route('reports.documents.csv'))
            ->assertRedirect()->assertSessionHasErrors('export');
        $this->assertDatabaseMissing('registros_auditoria', ['action' => AuditAction::REPORT_EXPORTED->value]);
    }

    public function test_csv_presentation_has_administrative_columns_local_dates_and_controlled_missing_values(): void
    {
        [$admin, $career, $creator] = $this->context();
        $creator->forceFill(['name' => 'María Peña'])->save();
        $subject = "carta de aprobacion de equipos; \"área técnica\"\nsegunda línea, á é í ó ú ñ \\";
        $sent = $this->document($creator, $career, $subject);
        $sent->forceFill([
            // The ephemeral test database stores local wall-clock timestamps.
            'sent_at' => CarbonImmutable::parse('2026-10-21 14:18:00', 'UTC')->setTimezone('America/La_Paz'),
            'due_at' => CarbonImmutable::parse('2026-11-01 03:59:00', 'UTC')->setTimezone('America/La_Paz'),
        ])->save();
        $this->recipient($sent, $career, DocumentRecipientStatus::SENT);
        $draft = $this->document($creator, $career, 'borrador sin fecha', DocumentStatus::DRAFT);
        $draftWithDue = $this->document($creator, $career, 'borrador con fecha', DocumentStatus::DRAFT,
            dueAt: CarbonImmutable::parse('2026-10-31 23:59:00', 'America/La_Paz'));

        $response = $this->actingAs($admin)->get(route('reports.documents.csv'));
        $response->assertOk()->assertDownload('reporte-documentos-20261012-100000.csv');
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringStartsWith("\xEF\xBB\xBFCódigo;", $content);
        $this->assertStringContainsString("\r\n", $content);
        $rows = $this->csvRows($content);
        $this->assertSame([
            'Código', 'Tipo de documento', 'Asunto', 'Unidad de origen', 'Remitente',
            'Estado', 'Prioridad', 'Clasificación', 'Fecha de envío', 'Fecha límite',
            'Situación del plazo', 'Destinatarios',
        ], array_shift($rows));
        $this->assertCount(3, $rows);
        $bySubject = collect($rows)->keyBy(fn (array $row): string => $row[2]);
        $this->assertSame([
            $sent->code, $sent->documentType->name, $subject, $career->name, 'María Peña',
            'Enviado', 'Normal', 'Normal', '21/10/2026 10:18', '31/10/2026 23:59', 'Vigente', '1',
        ], $bySubject[$subject]);
        $this->assertSame([
            '—', $draft->documentType->name, $draft->subject, $career->name, '—',
            'Borrador', 'Normal', 'Normal', '—', '—', 'Sin fecha límite', '0',
        ], $bySubject[$draft->subject]);
        $this->assertSame('31/10/2026 23:59', $bySubject[$draftWithDue->subject][9]);
        $this->assertSame('No aplicable', $bySubject[$draftWithDue->subject][10]);
        $this->assertNull($draft->fresh()->code);
        $this->assertSame($subject, $sent->fresh()->subject);
        $this->assertTrue($sent->fresh()->sent_at->equalTo(CarbonImmutable::parse('2026-10-21 14:18:00', 'UTC')));
    }

    public function test_report_shows_readable_applied_filters_and_preserves_export_parameters(): void
    {
        [$admin, $career, $creator] = $this->context();
        $document = $this->document($creator, $career, 'carta institucional');
        $filters = [
            'report' => 'sent', 'status' => 'sent', 'origin_unit' => $career->public_id,
            'document_type' => $document->documentType->public_id,
            'period_from' => '2026-10-01', 'period_to' => '2026-10-31',
            'q' => 'carta', 'sent_from' => '2026-10-01', 'sort' => 'code', 'direction' => 'asc',
        ];

        $response = $this->actingAs($admin)->get(route('reports.documents.index', $filters));
        $response->assertOk()
            ->assertSee('Reportes documentales')->assertSee('Filtros aplicados')
            ->assertSee('01/10/2026')->assertSee('31/10/2026')
            ->assertSee('fecha de envío')->assertSee('Documentos enviados')
            ->assertSee('Descargar CSV')->assertSee('Aplicar filtros')
            ->assertSee('name="q" value="carta"', false)
            ->assertSee('name="sent_from" value="2026-10-01"', false)
            ->assertDontSee('Borrador sin código');
        preg_match('/href="([^"]+)"[^>]*data-report-export/', $response->getContent(), $exportLink);
        $url = html_entity_decode($exportLink[1], ENT_QUOTES, 'UTF-8');
        $this->assertSame(route('reports.documents.csv', absolute: false), parse_url($url, PHP_URL_PATH));
        parse_str(parse_url($url, PHP_URL_QUERY), $exportFilters);
        $this->assertEquals($filters, $exportFilters);
        $this->assertSame([$document->id], $response->viewData('documents')->pluck('id')->all());
    }

    public function test_report_summary_uses_all_filtered_documents_not_only_the_current_page(): void
    {
        config(['reports.per_page' => 5]);
        [$admin, $career, $creator] = $this->context();
        foreach (range(1, 7) as $number) {
            $this->document($creator, $career, 'Enviado '.$number);
        }
        $this->document($creator, $career, 'Borrador', DocumentStatus::DRAFT);
        $this->document($creator, $career, 'Anulado', DocumentStatus::ANNULLED);

        $response = $this->actingAs($admin)->get(route('reports.documents.index'));
        $response->assertOk();
        $this->assertCount(5, $response->viewData('documents'));
        preg_match('/<section aria-labelledby="report-summary-title">(.*?)<\/section>/s', $response->getContent(), $summary);
        preg_match_all('/<dd[^>]*>(.*?)<\/dd>/s', $summary[1], $values);
        $this->assertSame(['9 documentos', '7', '1', '1'], array_map(
            fn (string $html): string => trim(preg_replace('/\s+/', ' ', strip_tags($html))),
            $values[1],
        ));
    }

    public function test_export_audit_contains_safe_administrative_metadata_only(): void
    {
        [$admin, $career, $creator] = $this->context();
        $document = $this->document($creator, $career, 'Asunto que no debe auditarse');

        $this->actingAs($admin)->get(route('reports.documents.csv', [
            'q' => $document->subject,
            'priority' => 'normal',
        ]))->assertOk();
        $log = AuditLog::query()->where('action', AuditAction::REPORT_EXPORTED->value)->sole();
        $encoded = json_encode($log->new_values);

        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame(1, $log->new_values['row_count']);
        $this->assertSame('csv', $log->new_values['format']);
        $this->assertSame('success', $log->new_values['result']);
        $this->assertArrayNotHasKey('q', $log->new_values['filters']);
        $this->assertStringNotContainsString($document->subject, $encoded);
        $this->assertStringNotContainsString($document->code, $encoded);
    }

    public function test_invalid_report_dates_and_sorting_are_rejected_safely(): void
    {
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $this->actingAs($admin)->get(route('reports.documents.index', [
            'report' => 'registros_auditoria',
            'period_from' => '2026-10-12',
            'period_to' => '2026-10-11',
            'sort' => 'subject; DROP TABLE users',
            'direction' => 'sideways',
        ]))->assertRedirect()->assertSessionHasErrors(['report', 'period_to', 'sort', 'direction']);
    }

    public function test_report_query_count_does_not_grow_per_document(): void
    {
        [$admin, $career, $creator] = $this->context();
        $this->document($creator, $career, 'Consulta reporte 1');
        $one = $this->queryCount($admin);
        foreach (range(2, 8) as $number) {
            $this->document($creator, $career, 'Consulta reporte '.$number);
        }
        $many = $this->queryCount($admin);

        $this->assertLessThanOrEqual($one + 1, $many);
    }

    public function test_correspondence_book_shows_only_the_secretarys_unit_entries_and_exits(): void
    {
        $own = $this->career('Mecánica Automotriz', 'MAZ');
        $foreign = $this->career('Aeronáutica', 'AER');
        $hiddenUnit = $this->career('Química Industrial', 'QMC');
        $secretary = $this->user(RoleName::SECRETARY, $own);
        $foreignSecretary = $this->user(RoleName::SECRETARY, $foreign);
        $hiddenSecretary = $this->user(RoleName::SECRETARY, $hiddenUnit);

        $outgoing = $this->document($secretary, $own, 'Salida del libro');
        $this->recipient($outgoing, $foreign, DocumentRecipientStatus::SENT);
        $incoming = $this->document($foreignSecretary, $foreign, 'Entrada del libro');
        $this->recipient($incoming, $own, DocumentRecipientStatus::RECEIVED, $secretary, now());
        $hidden = $this->document($hiddenSecretary, $hiddenUnit, 'Correspondencia ajena');
        $this->recipient($hidden, $foreign, DocumentRecipientStatus::SENT);

        $response = $this->actingAs($secretary)->get(route('correspondence-book.index'));

        $response->assertOk()
            ->assertSee($outgoing->code)
            ->assertSee($incoming->code)
            ->assertSee($secretary->name)
            ->assertDontSee($hidden->code);
        $this->assertSame(2, $response->viewData('entries')->total());
    }

    public function test_correspondence_book_filters_by_month_and_rejects_assistants(): void
    {
        $own = $this->career('Electricidad Industrial', 'ELT');
        $foreign = $this->career('Electromecánica', 'ELM');
        $secretary = $this->user(RoleName::SECRETARY, $own);
        $foreignSecretary = $this->user(RoleName::SECRETARY, $foreign);
        $assistant = $this->user(RoleName::SECRETARY_ASSISTANT, $own);

        $outgoing = $this->document($secretary, $own, 'Solo salida');
        $this->recipient($outgoing, $foreign, DocumentRecipientStatus::SENT);
        $incoming = $this->document($foreignSecretary, $foreign, 'Solo entrada');
        $this->recipient($incoming, $own, DocumentRecipientStatus::SENT);
        $incoming->forceFill(['sent_at' => now()->subMonth()])->save();

        $response = $this->actingAs($secretary)->get(route('correspondence-book.index', [
            'year' => now()->year,
            'month' => now()->month,
        ]));

        $response->assertOk()->assertSee($outgoing->code)->assertDontSee($incoming->code);
        $this->assertSame(1, $response->viewData('entries')->total());
        $this->actingAs($assistant)->get(route('correspondence-book.index'))->assertForbidden();
    }

    public function test_correspondence_book_can_be_downloaded_as_pdf_with_the_same_scope(): void
    {
        $unit = $this->career('Mecánica Industrial', 'MIN');
        $secretary = $this->user(RoleName::SECRETARY, $unit);
        $document = $this->document($secretary, $unit, 'Registro PDF');
        $this->recipient($document, $unit, DocumentRecipientStatus::SENT);

        $response = $this->actingAs($secretary)->get(route('correspondence-book.pdf', ['year' => now()->year]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    /** @return array{User, OrganizationalUnit, User} */
    private function context(): array
    {
        $career = $this->career('Sistemas', 'SIS');

        return [$this->user(RoleName::ADMINISTRATOR), $career, $this->user(RoleName::SECRETARY, $career)];
    }

    private function career(string $name, string $acronym): OrganizationalUnit
    {
        return OrganizationalUnit::query()->create([
            'type' => 'career', 'name' => $name.' '.Str::random(5),
            'acronym' => $acronym.Str::upper(Str::random(3)), 'is_active' => true,
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
        CarbonImmutable|string|null $dueAt = null,
        ?DocumentType $type = null,
    ): Document {
        $official = $status !== DocumentStatus::DRAFT;
        $document = Document::query()->create([
            'document_type_id' => ($type ?? DocumentType::query()->where('is_active', true)->firstOrFail())->id,
            'origin_unit_id' => $origin->id, 'created_by' => $creator->id,
            'sender_user_id' => $official ? $creator->id : null, 'subject' => $subject,
            'general_status' => $status, 'priority' => $priority, 'confidentiality' => $confidentiality,
            'due_at' => $dueAt, 'code' => $official ? 'FTEC-REP-2026-'.Str::upper(Str::random(6)) : null,
            'lock_version' => $official ? 1 : 0,
        ]);
        if ($official) {
            $document->forceFill(['sent_at' => now()->subDay()])->save();
        }

        return $document;
    }

    private function recipient(
        Document $document,
        OrganizationalUnit $unit,
        DocumentRecipientStatus $status,
        ?User $user = null,
        CarbonImmutable|string|null $receivedAt = null,
        CarbonImmutable|string|null $decidedAt = null,
        ?User $decidedBy = null,
    ): DocumentRecipient {
        return DocumentRecipient::query()->create([
            'document_id' => $document->id, 'unit_id' => $unit->id, 'user_id' => $user?->id,
            'requires_response' => true, 'status' => $status,
            'received_by' => $receivedAt ? ($user?->id ?? $decidedBy?->id) : null,
            'received_at' => $receivedAt, 'decided_by' => $decidedBy?->id,
            'decided_at' => $decidedAt, 'lock_version' => 1,
        ]);
    }

    private function grant(Document $document, User $user): void
    {
        DocumentAccessGrant::query()->create([
            'document_id' => $document->id, 'user_id' => $user->id,
            'granted_by' => $document->created_by, 'permission' => 'view',
            'reason' => 'Consulta explícita.',
        ]);
    }

    /** @param array<int, Document>|array<string, mixed> $filtersOrExpected @param array<int, Document>|null $expected */
    private function assertReportIds(User $user, array $filtersOrExpected, ?array $expected = null): void
    {
        $filters = $expected === null ? [] : $filtersOrExpected;
        $documents = $expected ?? $filtersOrExpected;
        $response = $this->actingAs($user)->get(route('reports.documents.index', $filters));
        $response->assertOk();
        $actual = $response->viewData('documents')->pluck('id')->sort()->values()->all();
        $ids = collect($documents)->pluck('id')->sort()->values()->all();
        $this->assertSame($ids, $actual);
        $this->assertSame(count($ids), $response->viewData('documents')->total());
    }

    /** @return array<int, array<int, string|null>> */
    private function csvRows(string $content): array
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, separator: ';', escape: '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    private function queryCount(User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('reports.documents.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
