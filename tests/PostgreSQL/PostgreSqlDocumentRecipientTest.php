<?php

namespace Tests\PostgreSQL;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentRecipientService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PostgreSqlDocumentRecipientTest extends TestCase
{
    use DatabaseTransactions;

    public function test_postgresql_defaults_new_recipient_to_prepared_and_allows_informational_copy(): void
    {
        [$document, $unit] = $this->documentContext();

        $id = DB::table('document_recipients')->insertGetId([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'user_id' => null,
            'requires_response' => false,
            'lock_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recipient = DB::table('document_recipients')->find($id);
        $this->assertSame('prepared', $recipient->status);
        $this->assertFalse($recipient->requires_response);
    }

    public function test_postgresql_rejects_invalid_recipient_status_and_foreign_keys(): void
    {
        [$document, $unit] = $this->documentContext();

        $this->assertConstraintViolation(fn () => $this->insertRecipient([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'status' => 'draft_recipient',
        ]), '23514');

        $this->assertConstraintViolation(fn () => $this->insertRecipient([
            'document_id' => $document->id,
            'unit_id' => 999999999,
            'status' => 'prepared',
        ]), '23503');
    }

    public function test_postgresql_prevents_equivalent_duplicates_but_allows_unit_and_person_combination(): void
    {
        [$document, $unit] = $this->documentContext();
        $person = User::factory()->create(['organizational_unit_id' => $unit->id]);

        $this->insertRecipient([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'user_id' => null,
            'status' => 'prepared',
        ]);

        $this->assertConstraintViolation(fn () => $this->insertRecipient([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'user_id' => null,
            'status' => 'prepared',
        ]), '23505');

        $this->insertRecipient([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'user_id' => $person->id,
            'status' => 'prepared',
        ]);

        $this->assertSame(2, DocumentRecipient::query()->where('document_id', $document->id)->count());
    }

    public function test_service_derives_person_unit_and_does_not_create_send_side_effects(): void
    {
        [$document, $origin, $creator] = $this->authorizedDocumentContext();
        $targetUnit = $this->career();
        $target = $this->userWithRole(RoleName::CAREER_HEAD, $targetUnit);
        $notificationCount = DB::table('notifications')->count();

        $recipient = app(DocumentRecipientService::class)->add($creator, $document, [
            'recipient_type' => 'person',
            'user_id' => $target->id,
            'unit_id' => $origin->id,
            'requires_response' => false,
            'lock_version' => 0,
        ]);

        $document->refresh();
        $this->assertSame($targetUnit->id, $recipient->unit_id);
        $this->assertSame($target->id, $recipient->user_id);
        $this->assertSame('prepared', $recipient->status->value);
        $this->assertSame(DocumentStatus::DRAFT, $document->general_status);
        $this->assertNull($document->code);
        $this->assertNull($document->sent_at);
        $this->assertSame($notificationCount, DB::table('notifications')->count());
        $this->assertDatabaseHas('document_events', [
            'document_id' => $document->id,
            'event_type' => 'recipient_added',
            'actor_id' => $creator->id,
        ]);
    }

    public function test_service_enforces_personal_recipient_role_eligibility(): void
    {
        [$document, , $creator] = $this->authorizedDocumentContext();
        $destination = $this->career();
        $assistant = $this->userWithRole(RoleName::SECRETARY_ASSISTANT, $destination);
        $extra = $this->userWithRole(RoleName::EXTRA, $destination);
        $service = app(DocumentRecipientService::class);

        foreach ([[$assistant, true], [$extra, false]] as [$person, $requiresResponse]) {
            try {
                $service->add($creator, $document, [
                    'recipient_type' => 'person',
                    'user_id' => $person->id,
                    'requires_response' => $requiresResponse,
                    'lock_version' => 0,
                ]);
                $this->fail('El servicio aceptó una combinación personal no autorizada.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $recipient = $service->add($creator, $document, [
            'recipient_type' => 'person',
            'user_id' => $assistant->id,
            'requires_response' => false,
            'lock_version' => 0,
        ]);

        $this->assertSame($assistant->id, $recipient->user_id);
        $this->assertFalse($recipient->requires_response);
        $this->assertSame(1, $document->fresh()->lock_version);
    }

    public function test_postgresql_optimistic_lock_rejects_stale_recipient_change(): void
    {
        [$document, , $creator] = $this->authorizedDocumentContext();
        $targetA = $this->career();
        $targetB = $this->career();
        $service = app(DocumentRecipientService::class);

        $service->add($creator, $document, [
            'recipient_type' => 'unit',
            'unit_id' => $targetA->id,
            'requires_response' => true,
            'lock_version' => 0,
        ]);

        try {
            $service->add($creator, $document, [
                'recipient_type' => 'unit',
                'unit_id' => $targetB->id,
                'requires_response' => true,
                'lock_version' => 0,
            ]);
            $this->fail('La modificación obsoleta de destinatarios no fue rechazada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lock_version', $exception->errors());
        }

        $this->assertSame(1, DocumentRecipient::query()->where('document_id', $document->id)->count());
        $this->assertSame($targetA->id, DocumentRecipient::query()->where('document_id', $document->id)->sole()->unit_id);
        $this->assertSame(1, $document->fresh()->lock_version);
    }

    /** @return array{Document, OrganizationalUnit} */
    private function documentContext(): array
    {
        $unit = $this->career();
        $creator = User::factory()->create(['organizational_unit_id' => $unit->id]);

        return [$this->draft($creator, $unit), $unit];
    }

    /** @return array{Document, OrganizationalUnit, User} */
    private function authorizedDocumentContext(): array
    {
        $unit = $this->career();
        $creator = $this->userWithRole(RoleName::SECRETARY, $unit);

        return [$this->draft($creator, $unit), $unit, $creator];
    }

    /** @param array<string, mixed> $attributes */
    private function insertRecipient(array $attributes): void
    {
        DB::table('document_recipients')->insert([
            'user_id' => null,
            'requires_response' => true,
            'lock_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    /** @param callable(): void $callback */
    private function assertConstraintViolation(callable $callback, string $sqlState): void
    {
        DB::beginTransaction();

        try {
            $callback();
            $this->fail('PostgreSQL aceptó un destinatario documental inválido.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        } finally {
            if (DB::transactionLevel() > 1) {
                DB::rollBack();
            }
        }
    }

    private function draft(User $creator, OrganizationalUnit $origin): Document
    {
        return Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => 'Borrador destinatarios PostgreSQL '.Str::random(6),
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
        ]);
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
            'name' => 'Carrera Destino '.Str::random(8),
            'acronym' => Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
    }
}
