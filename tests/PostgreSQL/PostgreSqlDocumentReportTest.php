<?php

namespace Tests\PostgreSQL;

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
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgreSqlDocumentReportTest extends TestCase
{
    /** @var array<int, int> */
    private array $documentIds = [];

    /** @var array<int, int> */
    private array $userIds = [];

    /** @var array<int, int> */
    private array $unitIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-12 10:00:00');
    }

    protected function tearDown(): void
    {
        DB::table('document_events')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('document_versions')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('document_recipients')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('document_access_grants')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('documents')->whereIn('id', $this->documentIds)->delete();
        DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $this->userIds)->delete();
        DB::table('model_has_permissions')->where('model_type', User::class)->whereIn('model_id', $this->userIds)->delete();
        DB::table('users')->whereIn('id', $this->userIds)->delete();
        DB::table('organizational_units')->whereIn('id', $this->unitIds)->delete();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_postgresql_reports_aggregate_distinct_documents_and_apply_local_date_boundaries(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $origin = $this->career('Reportes agregados');
        $destinationA = $this->career('Reporte destino A');
        $destinationB = $this->career('Reporte destino B');
        $creator = $this->user(RoleName::SECRETARY, $origin);
        $included = $this->document($creator, $origin, 'Incluido al final del día', '2026-10-10 23:59:59');
        $excluded = $this->document($creator, $origin, 'Fuera al iniciar el día siguiente', '2026-10-11 00:00:00');

        foreach ([$destinationA, $destinationB] as $destination) {
            DocumentRecipient::query()->create([
                'document_id' => $included->id,
                'unit_id' => $destination->id,
                'requires_response' => true,
                'status' => DocumentRecipientStatus::SENT,
                'lock_version' => 1,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('reports.documents.index', [
            'report' => 'sent',
            'period_from' => '2026-10-10',
            'period_to' => '2026-10-10',
        ]));

        $response->assertOk()->assertSee($included->subject)->assertDontSee($excluded->subject);
        $this->assertSame([$included->id], $response->viewData('documents')->pluck('id')->all());
        $summaries = $response->viewData('summaries');
        $this->assertSame(1, collect($summaries['statuses'])->sum('count'));
        $this->assertSame(1, collect($summaries['units'])->sum('total'));
        $this->assertSame(1, collect($summaries['types'])->sum('count'));
    }

    public function test_postgresql_report_filters_are_supported_by_existing_indexes(): void
    {
        $origin = $this->career('Índices de reportes');
        $creator = $this->user(RoleName::SECRETARY, $origin);
        $document = $this->document($creator, $origin, 'Reporte indexado', '2026-10-10 16:30:00');
        DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $origin->id,
            'requires_response' => true,
            'status' => DocumentRecipientStatus::SENT,
            'lock_version' => 1,
        ]);

        $definitions = collect(DB::select(<<<'SQL'
            SELECT indexname
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND tablename IN ('documents', 'document_recipients')
            SQL))->pluck('indexname');
        $this->assertContains('documents_origin_unit_id_general_status_created_at_index', $definitions);
        $this->assertContains('documents_general_status_priority_index', $definitions);
        $this->assertContains('document_recipients_unit_id_status_index', $definitions);

        DB::transaction(function () use ($origin): void {
            DB::statement('SET LOCAL enable_seqscan = off');
            $statusPlan = $this->plan(
                'SELECT id FROM documents WHERE general_status = ? AND priority = ? AND deleted_at IS NULL',
                [DocumentStatus::SENT->value, DocumentPriority::IMPORTANT->value],
            );
            $originPlan = $this->plan(
                'SELECT id FROM documents WHERE origin_unit_id = ? AND general_status = ? AND created_at >= ? AND deleted_at IS NULL',
                [$origin->id, DocumentStatus::SENT->value, now()->subMonth()],
            );
            $recipientPlan = $this->plan(
                'SELECT document_id FROM document_recipients WHERE unit_id = ? AND status = ? AND deleted_at IS NULL',
                [$origin->id, DocumentRecipientStatus::SENT->value],
            );

            $this->assertStringContainsString('Index Scan', $statusPlan);
            $this->assertStringContainsString('Index Scan', $originPlan);
            $this->assertTrue(
                str_contains($recipientPlan, 'document_recipients_unit_id_status_index')
                    || str_contains($recipientPlan, 'recipients_target_unique'),
                $recipientPlan,
            );
        });
    }

    private function career(string $label): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();
        $unit = OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => $label.' '.Str::random(8),
            'acronym' => Str::upper(Str::random(8)),
            'is_active' => true,
        ]);
        $this->unitIds[] = $unit->id;

        return $unit;
    }

    private function user(RoleName $role, ?OrganizationalUnit $unit = null): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit?->id]);
        $user->assignRole($role->value);
        $this->userIds[] = $user->id;

        return $user;
    }

    private function document(User $creator, OrganizationalUnit $origin, string $subject, string $sentAt): Document
    {
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => $subject,
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::IMPORTANT,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'due_at' => CarbonImmutable::parse('2026-10-15 17:00:00', config('app.timezone')),
            'lock_version' => 0,
        ]);
        $this->documentIds[] = $document->id;
        $document->forceFill([
            'sender_user_id' => $creator->id,
            'general_status' => DocumentStatus::SENT,
            'code' => 'FTEC-'.$origin->acronym.'-2026-'.Str::upper(Str::random(8)),
            'sent_at' => CarbonImmutable::parse($sentAt, config('app.timezone')),
            'lock_version' => 1,
        ])->save();

        return $document;
    }

    /** @param array<int, mixed> $bindings */
    private function plan(string $sql, array $bindings): string
    {
        return collect(DB::select('EXPLAIN '.$sql, $bindings))
            ->map(fn (object $row): string => $row->{'QUERY PLAN'})
            ->implode("\n");
    }
}
