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

class PostgreSqlDocumentSearchTest extends TestCase
{
    /** @var array<int, int> */
    private array $documentIds = [];

    /** @var array<int, int> */
    private array $userIds = [];

    /** @var array<int, int> */
    private array $unitIds = [];

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

        parent::tearDown();
    }

    public function test_postgresql_executes_authorized_combined_search_with_timestamptz_and_literal_wildcards(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $origin = $this->career('Origen búsqueda');
        $destination = $this->career('Destino búsqueda');
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $creator = $this->user(RoleName::SECRETARY, $origin);
        $document = $this->document($creator, $origin, 'Avance 50% confirmado', 'FTEC-PGS-2026-000777');
        DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $destination->id,
            'requires_response' => true,
            'status' => DocumentRecipientStatus::SENT,
            'lock_version' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('documents.index', [
            'q' => '50%',
            'status' => 'sent',
            'origin_unit' => $origin->public_id,
            'recipient_unit' => $destination->public_id,
            'recipient_status' => 'sent',
            'sent_from' => '2026-10-12',
            'sent_to' => '2026-10-12',
        ]));

        $response->assertOk()->assertSee($document->subject)->assertSee('1 documento encontrado');
        $this->assertSame([$document->id], $response->viewData('documents')->pluck('id')->all());
        $this->actingAs($admin)->get(route('documents.index', ['q' => $document->code]))
            ->assertOk()->assertSee($document->subject);
    }

    public function test_postgresql_existing_indexes_support_exact_code_deadline_and_recipient_filters(): void
    {
        $origin = $this->career('Índices búsqueda');
        $creator = $this->user(RoleName::SECRETARY, $origin);
        $document = $this->document($creator, $origin, 'Plan indexado', 'FTEC-PGI-2026-000778');
        DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $origin->id,
            'requires_response' => true,
            'status' => DocumentRecipientStatus::SENT,
            'lock_version' => 1,
        ]);

        $definitions = collect(DB::select(<<<'SQL'
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND tablename IN ('documents', 'document_recipients')
            SQL))->keyBy('indexname');

        $this->assertTrue($definitions->has('documents_code_unique'));
        $this->assertTrue($definitions->has('documents_origin_unit_id_general_status_created_at_index'));
        $this->assertTrue($definitions->has('documents_general_status_priority_index'));
        $this->assertTrue($definitions->has('documents_due_open_index'));
        $this->assertTrue($definitions->has('document_recipients_unit_id_status_index'));

        DB::transaction(function () use ($document, $origin): void {
            DB::statement('SET LOCAL enable_seqscan = off');
            $codePlan = $this->plan('SELECT id FROM documents WHERE code = ? AND deleted_at IS NULL', [$document->code]);
            $recipientPlan = $this->plan(
                'SELECT document_id FROM document_recipients WHERE unit_id = ? AND status = ? AND deleted_at IS NULL',
                [$origin->id, DocumentRecipientStatus::SENT->value],
            );
            $deadlinePlan = $this->plan(
                "SELECT id FROM documents WHERE due_at IS NOT NULL AND general_status NOT IN ('approved', 'rejected', 'annulled') AND due_at <= ? AND deleted_at IS NULL",
                [now()->addDay()],
            );

            $this->assertStringContainsString('documents_code_unique', $codePlan);
            $this->assertTrue(
                str_contains($recipientPlan, 'document_recipients_unit_id_status_index')
                    || str_contains($recipientPlan, 'recipients_target_unique'),
                $recipientPlan,
            );
            $this->assertStringContainsString('documents_due_open_index', $deadlinePlan);
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

    private function document(User $creator, OrganizationalUnit $origin, string $subject, string $code): Document
    {
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => $subject,
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::IMPORTANT,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'due_at' => CarbonImmutable::parse('2026-10-13 17:00:00', config('app.timezone')),
            'lock_version' => 0,
        ]);
        $this->documentIds[] = $document->id;
        $document->forceFill([
            'sender_user_id' => $creator->id,
            'general_status' => DocumentStatus::SENT,
            'code' => $code,
            'sent_at' => CarbonImmutable::parse('2026-10-12 18:30:00', config('app.timezone')),
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
