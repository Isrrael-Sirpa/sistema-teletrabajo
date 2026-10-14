<?php

namespace Tests\PostgreSQL;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentEventType;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentEvent;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentEventService;
use App\Services\DocumentRecipientService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgreSqlDocumentHistoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_postgresql_uses_timezone_soft_delete_columns_and_indexes(): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('table_name', ['documents', 'document_recipients'])
            ->where('column_name', 'deleted_at')
            ->pluck('udt_name', 'table_name');

        $this->assertSame('timestamptz', $columns['documents']);
        $this->assertSame('timestamptz', $columns['document_recipients']);

        $indexes = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->whereIn('indexname', [
                'documents_deleted_at_index',
                'document_recipients_deleted_at_index',
            ])
            ->pluck('indexname');

        $this->assertCount(2, $indexes);
    }

    public function test_recipient_soft_delete_preserves_event_fk_and_relation(): void
    {
        [$document, $unit, $actor] = $this->context();
        $recipient = DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'requires_response' => true,
        ]);
        $event = app(DocumentEventService::class)->record(
            $document,
            DocumentEventType::RECIPIENT_REMOVED,
            $actor,
            $recipient,
            metadata: app(DocumentEventService::class)->recipientMetadata($recipient),
        );

        $recipient->delete();

        $this->assertNull(DocumentRecipient::query()->find($recipient->id));
        $this->assertTrue(DocumentRecipient::withTrashed()->findOrFail($recipient->id)->trashed());
        $this->assertSame($recipient->id, $event->fresh()->recipient->id);
        $this->assertTrue($event->fresh()->recipient->trashed());

        $this->assertConstraintViolation(
            fn () => DB::table('document_recipients')->where('id', $recipient->id)->delete(),
            '23001',
        );
        $this->assertDatabaseHas('document_events', ['id' => $event->id]);
    }

    public function test_postgresql_restores_same_recipient_without_breaking_uniqueness(): void
    {
        [$document, , $actor] = $this->context();
        $destination = $this->career();
        $service = app(DocumentRecipientService::class);
        $recipient = $service->add($actor, $document, [
            'recipient_type' => 'unit',
            'unit_id' => $destination->id,
            'requires_response' => true,
            'lock_version' => 0,
        ]);
        $service->remove($actor, $document, [
            'recipient_id' => $recipient->id,
            'lock_version' => 1,
        ]);
        $restored = $service->add($actor, $document, [
            'recipient_type' => 'unit',
            'unit_id' => $destination->id,
            'requires_response' => false,
            'lock_version' => 2,
        ]);

        $this->assertSame($recipient->id, $restored->id);
        $this->assertFalse($restored->requires_response);
        $this->assertSame(1, DocumentRecipient::withTrashed()->where('document_id', $document->id)->count());
        $this->assertSame(3, DocumentEvent::query()->where('document_id', $document->id)->count());
    }

    public function test_jsonb_timestamp_and_transactional_rollback_are_preserved(): void
    {
        [$document, , $actor] = $this->context();
        $service = app(DocumentEventService::class);
        $event = $service->record(
            $document,
            DocumentEventType::DOCUMENT_RETURNED,
            $actor,
            fromStatus: 'received',
            toStatus: 'returned',
            reason: 'Falta la firma.',
            metadata: ['recipient_type' => 'unit'],
        );

        $stored = DB::table('document_events')->find($event->id);
        $this->assertJson($stored->metadata);
        $this->assertNotNull($stored->occurred_at);
        $this->assertSame('Falta la firma.', $stored->reason);

        try {
            DB::transaction(function () use ($actor, $document, $service): void {
                $service->record($document, DocumentEventType::DRAFT_UPDATED, $actor);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }

        $this->assertSame(1, DocumentEvent::query()->where('document_id', $document->id)->count());
    }

    /** @return array{Document, OrganizationalUnit, User} */
    private function context(): array
    {
        $unit = $this->career();
        $actor = User::factory()->create(['organizational_unit_id' => $unit->id]);
        $actor->assignRole(RoleName::SECRETARY->value);

        return [$this->draft($actor, $unit), $unit, $actor];
    }

    private function draft(User $creator, OrganizationalUnit $origin): Document
    {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => 'Historial PostgreSQL '.Str::random(8),
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
        ]);
    }

    private function career(): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();

        return OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera Historial PG '.Str::random(8),
            'acronym' => Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
    }

    /** @param callable(): mixed $callback */
    private function assertConstraintViolation(callable $callback, string $sqlState): void
    {
        DB::beginTransaction();

        try {
            $callback();
            $this->fail('PostgreSQL permitió romper una FK del historial.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        } finally {
            if (DB::transactionLevel() > 1) {
                DB::rollBack();
            }
        }
    }
}
