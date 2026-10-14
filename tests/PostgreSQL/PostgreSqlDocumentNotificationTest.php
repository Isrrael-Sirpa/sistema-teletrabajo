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
use App\Services\DocumentWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgreSqlDocumentNotificationTest extends TestCase
{
    /** @var array<int, int> */
    private array $documentIds = [];

    /** @var array<int, int> */
    private array $userIds = [];

    /** @var array<int, int> */
    private array $unitIds = [];

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        DB::table('audit_logs')->whereIn('actor_id', $this->userIds)->delete();
        DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $this->userIds)->delete();
        DB::table('document_events')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('document_recipients')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('document_files')->whereIn(
            'document_version_id',
            DB::table('document_versions')->whereIn('document_id', $this->documentIds)->select('id'),
        )->delete();
        DB::table('document_versions')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('documents')->whereIn('id', $this->documentIds)->delete();
        DB::table('document_counters')->whereIn('origin_unit_id', $this->unitIds)->delete();
        DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $this->userIds)->delete();
        DB::table('model_has_permissions')->where('model_type', User::class)->whereIn('model_id', $this->userIds)->delete();
        DB::table('users')->whereIn('id', $this->userIds)->delete();
        DB::table('organizational_units')->whereIn('id', $this->unitIds)->delete();

        parent::tearDown();
    }

    public function test_postgresql_persists_notification_only_after_outer_transaction_commits(): void
    {
        [$document, $actor, $recipientUser] = $this->context();

        DB::beginTransaction();
        app(DocumentWorkflowService::class)->send($actor, $document, 0);

        $this->assertSame(0, $recipientUser->notifications()->count());

        DB::commit();

        $notification = $recipientUser->notifications()->sole();
        $this->assertSame('document_sent', $notification->data['event_type']);
        $this->assertSame($document->public_id, $notification->data['document_public_id']);
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_postgresql_rollback_discards_document_changes_and_pending_notification(): void
    {
        [$document, $actor, $recipientUser] = $this->context();

        DB::beginTransaction();
        app(DocumentWorkflowService::class)->send($actor, $document, 0);
        $this->assertSame(0, $recipientUser->notifications()->count());
        DB::rollBack();

        $document->refresh();
        $this->assertSame(DocumentStatus::DRAFT, $document->general_status);
        $this->assertNull($document->code);
        $this->assertSame(0, $recipientUser->notifications()->count());
        $this->assertDatabaseMissing('document_events', [
            'document_id' => $document->id,
            'event_type' => 'document_sent',
        ]);
    }

    /** @return array{Document, User, User} */
    private function context(): array
    {
        $origin = $this->career();
        $destination = $this->career();
        $actor = $this->userWithRole(RoleName::SECRETARY, $origin);
        $recipientUser = $this->userWithRole(RoleName::SECRETARY, $destination);
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $actor->id,
            'subject' => 'Notificación PostgreSQL '.Str::random(8),
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
        ]);
        $this->documentIds[] = $document->id;
        DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $destination->id,
            'user_id' => $recipientUser->id,
            'requires_response' => true,
            'status' => DocumentRecipientStatus::PREPARED,
            'lock_version' => 0,
        ]);

        return [$document, $actor, $recipientUser];
    }

    private function career(): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();
        $unit = OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera Notificaciones PG '.Str::random(8),
            'acronym' => Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $this->unitIds[] = $unit->id;

        return $unit;
    }

    private function userWithRole(RoleName $role, OrganizationalUnit $unit): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit->id]);
        $user->assignRole($role->value);
        $this->userIds[] = $user->id;

        return $user;
    }
}
