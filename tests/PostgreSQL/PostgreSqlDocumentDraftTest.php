<?php

namespace Tests\PostgreSQL;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentDraftService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PostgreSqlDocumentDraftTest extends TestCase
{
    use DatabaseTransactions;

    public function test_service_creates_a_safe_draft_in_postgresql(): void
    {
        $career = $this->career();
        $secretary = $this->userWithRole(RoleName::SECRETARY, $career);
        $type = DocumentType::query()->where('is_active', true)->firstOrFail();

        $document = app(DocumentDraftService::class)->create($secretary, [
            'document_type_id' => $type->id,
            'origin_unit_id' => 999999,
            'subject' => 'Borrador PostgreSQL',
            'description' => null,
            'priority' => DocumentPriority::IMPORTANT->value,
            'confidentiality' => DocumentConfidentiality::RESTRICTED->value,
            'due_at' => now()->addDays(3)->toDateString(),
        ]);

        $this->assertSame($career->id, $document->origin_unit_id);
        $this->assertSame($secretary->id, $document->created_by);
        $this->assertSame(DocumentStatus::DRAFT, $document->general_status);
        $this->assertNull($document->code);
        $this->assertNull($document->sender_user_id);
        $this->assertNull($document->sent_at);
        $this->assertSame(0, $document->lock_version);
    }

    public function test_postgresql_rejects_invalid_document_domains_and_negative_lock_version(): void
    {
        $context = $this->rawContext();

        foreach ([
            ['general_status' => 'unknown'],
            ['priority' => 'critical'],
            ['confidentiality' => 'public'],
            ['lock_version' => -1],
        ] as $invalid) {
            $this->assertConstraintViolation(
                fn () => $this->insertRawDocument($context, $invalid),
                '23514',
            );
        }
    }

    public function test_postgresql_enforces_draft_and_sent_field_coherence(): void
    {
        $context = $this->rawContext();

        $this->assertConstraintViolation(fn () => $this->insertRawDocument($context, [
            'general_status' => DocumentStatus::DRAFT->value,
            'code' => 'SHOULD-NOT-EXIST',
        ]), '23514');

        $this->assertConstraintViolation(fn () => $this->insertRawDocument($context, [
            'general_status' => DocumentStatus::SENT->value,
            'code' => null,
            'sent_at' => null,
        ]), '23514');
    }

    public function test_postgresql_enforces_document_foreign_keys(): void
    {
        $context = $this->rawContext();

        $this->assertConstraintViolation(fn () => $this->insertRawDocument($context, [
            'document_type_id' => 999999999,
        ]), '23503');
    }

    public function test_optimistic_lock_prevents_a_stale_postgresql_update(): void
    {
        $career = $this->career();
        $secretary = $this->userWithRole(RoleName::SECRETARY, $career);
        $type = DocumentType::query()->where('is_active', true)->firstOrFail();
        $service = app(DocumentDraftService::class);
        $document = $service->create($secretary, $this->serviceData($type, 'Versión inicial'));

        $service->update($secretary, $document, [
            ...$this->serviceData($type, 'Versión confirmada'),
            'lock_version' => 0,
        ]);

        try {
            $service->update($secretary, $document, [
                ...$this->serviceData($type, 'Versión obsoleta'),
                'lock_version' => 0,
            ]);
            $this->fail('La actualización obsoleta no fue rechazada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lock_version', $exception->errors());
        }

        $document->refresh();
        $this->assertSame('Versión confirmada', $document->subject);
        $this->assertSame(1, $document->lock_version);
    }

    public function test_discard_is_logical_only_for_a_clean_draft_without_dependencies(): void
    {
        $career = $this->career();
        $secretary = $this->userWithRole(RoleName::SECRETARY, $career);
        $type = DocumentType::query()->where('is_active', true)->firstOrFail();
        $service = app(DocumentDraftService::class);
        $protected = $service->create($secretary, $this->serviceData($type, 'Borrador relacionado'));

        DB::table('document_recipients')->insert([
            'document_id' => $protected->id,
            'unit_id' => $career->id,
            'requires_response' => true,
            'status' => 'sent',
            'lock_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $service->discard($secretary, $protected);
            $this->fail('El borrador con dependencias fue eliminado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }

        $this->assertDatabaseHas('documents', ['id' => $protected->id]);

        $clean = $service->create($secretary, $this->serviceData($type, 'Borrador limpio'));
        $service->discard($secretary, $clean);

        $this->assertSoftDeleted('documents', ['id' => $clean->id]);
        $this->assertDatabaseHas('document_events', [
            'document_id' => $clean->id,
            'event_type' => 'draft_discarded',
            'actor_id' => $secretary->id,
        ]);
        $this->assertNotNull(Document::withTrashed()->findOrFail($clean->id)->deleted_at);
    }

    /** @return array<string, mixed> */
    private function serviceData(DocumentType $type, string $subject): array
    {
        return [
            'document_type_id' => $type->id,
            'subject' => $subject,
            'description' => null,
            'priority' => DocumentPriority::NORMAL->value,
            'confidentiality' => DocumentConfidentiality::NORMAL->value,
            'due_at' => null,
        ];
    }

    /** @return array{document_type_id: int, origin_unit_id: int, created_by: int} */
    private function rawContext(): array
    {
        $career = $this->career();
        $user = User::factory()->create(['organizational_unit_id' => $career->id]);

        return [
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $career->id,
            'created_by' => $user->id,
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function insertRawDocument(array $context, array $overrides): void
    {
        DB::table('documents')->insert([
            'public_id' => (string) Str::uuid(),
            ...$context,
            'subject' => 'Inserción de restricción '.Str::random(8),
            'general_status' => DocumentStatus::DRAFT->value,
            'priority' => DocumentPriority::NORMAL->value,
            'confidentiality' => DocumentConfidentiality::NORMAL->value,
            'lock_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }

    /** @param callable(): void $callback */
    private function assertConstraintViolation(callable $callback, string $sqlState): void
    {
        DB::beginTransaction();

        try {
            $callback();
            $this->fail('PostgreSQL aceptó datos documentales inválidos.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        } finally {
            if (DB::transactionLevel() > 1) {
                DB::rollBack();
            }
        }
    }

    private function userWithRole(RoleName $role, OrganizationalUnit $unit): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit->id]);
        $user->assignRole($role->value);

        return $user;
    }

    private function career(): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();

        return OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera PostgreSQL '.Str::random(8),
            'acronym' => Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
    }
}
