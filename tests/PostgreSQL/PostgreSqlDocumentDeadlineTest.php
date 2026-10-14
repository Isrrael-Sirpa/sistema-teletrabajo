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
use App\Services\DocumentDeadlineReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgreSqlDocumentDeadlineTest extends TestCase
{
    /** @var array<int, int> */
    private array $documentIds = [];

    /** @var array<int, int> */
    private array $userIds = [];

    /** @var array<int, int> */
    private array $unitIds = [];

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $this->userIds)->delete();
        DB::table('document_events')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('document_recipients')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('documents')->whereIn('id', $this->documentIds)->delete();
        DB::table('document_counters')->whereIn('origin_unit_id', $this->unitIds)->delete();
        DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $this->userIds)->delete();
        DB::table('model_has_permissions')->where('model_type', User::class)->whereIn('model_id', $this->userIds)->delete();
        DB::table('users')->whereIn('id', $this->userIds)->delete();
        DB::table('organizational_units')->whereIn('id', $this->unitIds)->delete();

        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_postgresql_uses_timestamptz_and_partial_deadline_index(): void
    {
        $column = DB::selectOne(<<<'SQL'
            SELECT data_type
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'documents'
              AND column_name = 'due_at'
            SQL);
        $index = DB::selectOne(<<<'SQL'
            SELECT indexdef
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND indexname = 'documents_due_open_index'
            SQL);

        $this->assertSame('timestamp with time zone', $column->data_type);
        $this->assertStringContainsString('due_at IS NOT NULL', $index->indexdef);
        $this->assertStringContainsString('approved', $index->indexdef);
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_two_concurrent_processors_create_one_notification_for_the_same_milestone(): void
    {
        $now = CarbonImmutable::parse('2026-10-11 08:00:00', 'America/La_Paz');
        [$document, $recipient] = $this->context($now);
        [$worker, $barrier] = $this->worker();
        $processes = [
            $this->startWorker($worker, $barrier, $document->id, $now),
            $this->startWorker($worker, $barrier, $document->id, $now),
        ];

        file_put_contents($barrier, 'go');
        array_walk($processes, fn (Process $process) => $process->wait());

        foreach ($processes as $process) {
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }

        $generated = collect($processes)->map(fn (Process $process): int => (int) trim($process->getOutput()));
        $this->assertSame([0, 1], $generated->sort()->values()->all());
        $this->assertSame(1, $recipient->notifications()->count());
        $this->assertSame(0, app(DocumentDeadlineReminderService::class)->processDocument($document->id, $now));
        $notification = $recipient->notifications()->sole();
        $this->assertSame('due_soon', $notification->data['deadline_milestone']);
        $this->assertSame('document_due_soon', $notification->data['event_type']);
    }

    /** @return array{Document, User} */
    private function context(CarbonImmutable $now): array
    {
        $origin = $this->career();
        $destination = $this->career();
        $creator = $this->userWithRole(RoleName::SECRETARY, $origin);
        $recipient = $this->userWithRole(RoleName::SECRETARY, $destination);
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => 'Concurrencia de plazo '.Str::random(8),
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'due_at' => $now->addDays(2)->endOfDay(),
            'lock_version' => 0,
        ]);
        $this->documentIds[] = $document->id;
        $document->forceFill([
            'sender_user_id' => $creator->id,
            'general_status' => DocumentStatus::SENT,
            'code' => 'FTEC-'.$origin->acronym.'-2026-'.Str::upper(Str::random(6)),
            'sent_at' => $now->subDay(),
            'lock_version' => 1,
        ])->save();
        DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $destination->id,
            'user_id' => $recipient->id,
            'requires_response' => true,
            'status' => DocumentRecipientStatus::SENT,
            'lock_version' => 1,
        ]);

        return [$document, $recipient];
    }

    private function career(): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();
        $unit = OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera Plazos PG '.Str::random(8),
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

    /** @return array{string, string} */
    private function worker(): array
    {
        $worker = storage_path('framework/testing/deadline-worker-'.Str::uuid().'.php');
        $barrier = storage_path('framework/testing/deadline-barrier-'.Str::uuid());
        $script = <<<'PHP'
<?php
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$deadline = microtime(true) + 10;
while (! file_exists($argv[2]) && microtime(true) < $deadline) { usleep(10000); }
try {
    $now = Carbon\CarbonImmutable::parse($argv[4], 'America/La_Paz');
    echo app(App\Services\DocumentDeadlineReminderService::class)->processDocument((int) $argv[3], $now);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(2);
}
PHP;
        file_put_contents($worker, $script);
        $this->temporaryFiles[] = $worker;
        $this->temporaryFiles[] = $barrier;

        return [$worker, $barrier];
    }

    private function startWorker(
        string $worker,
        string $barrier,
        int $documentId,
        CarbonImmutable $now,
    ): Process {
        $process = new Process([
            PHP_BINARY,
            $worker,
            base_path(),
            $barrier,
            (string) $documentId,
            $now->toIso8601String(),
        ], base_path(), timeout: 30);
        $process->start();

        return $process;
    }
}
