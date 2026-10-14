<?php

namespace Tests\PostgreSQL;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentRecipientStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentTransition;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgreSqlDocumentWorkflowTest extends TestCase
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
        DB::table('notifications')->whereIn('notifiable_id', $this->userIds)->delete();
        DB::table('audit_logs')->whereIn('actor_id', $this->userIds)->delete();
        Document::withTrashed()->whereIn('id', $this->documentIds)->pluck('public_id')
            ->each(fn (string $publicId) => Storage::disk('documents')->deleteDirectory($publicId));
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

        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_postgresql_enforces_recipient_decision_and_copy_constraints(): void
    {
        [$document, $unit, $actor] = $this->context();
        $copy = $this->recipient($document, $unit, false, DocumentRecipientStatus::SENT);

        $this->assertConstraintViolation(function () use ($copy): void {
            DB::table('document_recipients')->where('id', $copy->id)->update([
                'status' => DocumentRecipientStatus::IN_REVIEW->value,
            ]);
        }, '23514');

        $required = $this->recipient($document, $this->career(), true, DocumentRecipientStatus::RECEIVED);
        $this->assertConstraintViolation(function () use ($required, $actor): void {
            DB::table('document_recipients')->where('id', $required->id)->update([
                'status' => DocumentRecipientStatus::RETURNED->value,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_reason' => '   ',
            ]);
        }, '23514');
    }

    public function test_postgresql_enforces_complete_annulment_fields_and_restricts_the_annuller_foreign_key(): void
    {
        [$document, $unit, $creator] = $this->context();
        $this->recipient($document, $unit);
        app(DocumentWorkflowService::class)->send($creator, $document, 0);
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        $this->assertConstraintViolation(function () use ($document): void {
            DB::table('documents')->where('id', $document->id)->update([
                'general_status' => DocumentStatus::ANNULLED->value,
            ]);
        }, '23514');

        app(DocumentWorkflowService::class)->annul(
            $administrator,
            $document->fresh(),
            1,
            'Anulación íntegra en PostgreSQL.',
        );

        $this->assertConstraintViolation(function () use ($administrator): void {
            DB::table('users')->where('id', $administrator->id)->delete();
        }, '23001');
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'general_status' => DocumentStatus::ANNULLED->value,
            'annulled_by' => $administrator->id,
            'annulment_reason' => 'Anulación íntegra en PostgreSQL.',
        ]);
    }

    public function test_two_concurrent_sends_get_distinct_correlatives(): void
    {
        [, $unit, $creator] = $this->context();
        $first = $this->draft($creator, $unit);
        $second = $this->draft($creator, $unit);
        $this->recipient($first, $unit);
        $this->recipient($second, $unit);
        [$worker, $barrier] = $this->worker();

        $processes = [
            $this->startWorker($worker, $barrier, ['send', $creator->id, $first->id]),
            $this->startWorker($worker, $barrier, ['send', $creator->id, $second->id]),
        ];

        file_put_contents($barrier, 'go');
        array_walk($processes, fn (Process $process) => $process->wait());

        foreach ($processes as $process) {
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }

        $codes = Document::query()->whereKey([$first->id, $second->id])->pluck('code');
        $this->assertCount(2, $codes);
        $this->assertCount(2, $codes->unique());
        $this->assertSame(['000001', '000002'], $codes->map(fn (string $code): string => Str::afterLast($code, '-'))->sort()->values()->all());
        $this->assertSame(2, DB::table('document_counters')->where('origin_unit_id', $unit->id)->value('last_number'));
    }

    public function test_concurrent_decisions_allow_only_one_winner(): void
    {
        [$document, $unit, $actor] = $this->context();
        $recipient = $this->recipient($document, $unit);
        $service = app(DocumentWorkflowService::class);
        $service->send($actor, $document, 0);
        $service->transition($actor, $document, $recipient, DocumentTransition::RECEIVE, 1, 1);
        $service->transition($actor, $document, $recipient, DocumentTransition::START_REVIEW, 2, 2);
        $documentVersion = $document->fresh()->lock_version;
        $recipientVersion = $recipient->fresh()->lock_version;
        [$worker, $barrier] = $this->worker();

        $processes = [
            $this->startWorker($worker, $barrier, [
                'transition', $actor->id, $document->id, $recipient->id, 'approve',
                $documentVersion, $recipientVersion, '',
            ]),
            $this->startWorker($worker, $barrier, [
                'transition', $actor->id, $document->id, $recipient->id, 'return',
                $documentVersion, $recipientVersion, 'Corrección concurrente.',
            ]),
        ];

        file_put_contents($barrier, 'go');
        array_walk($processes, fn (Process $process) => $process->wait());

        foreach ($processes as $process) {
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }

        $outputs = collect($processes)->map(fn (Process $process): string => trim($process->getOutput()));
        $this->assertSame(1, $outputs->filter(fn (string $output): bool => str_starts_with($output, 'ok:'))->count());
        $this->assertSame(
            1,
            $outputs->filter(fn (string $output): bool => str_contains($output, 'conflict'))->count(),
            $outputs->toJson(),
        );

        $recipient->refresh();
        $document->refresh();
        $this->assertContains($recipient->status, [DocumentRecipientStatus::APPROVED, DocumentRecipientStatus::RETURNED]);
        $this->assertSame(
            $recipient->status === DocumentRecipientStatus::APPROVED ? DocumentStatus::APPROVED : DocumentStatus::RETURNED,
            $document->general_status,
        );
        $this->assertSame($recipientVersion + 1, $recipient->lock_version);
        $this->assertSame($documentVersion + 1, $document->lock_version);
    }

    public function test_concurrent_annulment_and_approval_allow_only_one_winner_and_preserve_integrity(): void
    {
        [$document, $unit, $creator] = $this->context();
        $recipient = $this->recipient($document, $unit);
        $service = app(DocumentWorkflowService::class);
        $service->send($creator, $document, 0);
        $service->transition($creator, $document, $recipient, DocumentTransition::RECEIVE, 1, 1);
        $service->transition($creator, $document, $recipient, DocumentTransition::START_REVIEW, 2, 2);
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $documentVersion = $document->fresh()->lock_version;
        $recipientVersion = $recipient->fresh()->lock_version;
        [$worker, $barrier] = $this->worker();

        $processes = [
            $this->startWorker($worker, $barrier, [
                'annul', $administrator->id, $document->id, $documentVersion,
                'Anulación concurrente PostgreSQL.',
            ]),
            $this->startWorker($worker, $barrier, [
                'transition', $administrator->id, $document->id, $recipient->id, 'approve',
                $documentVersion, $recipientVersion, '',
            ]),
        ];

        file_put_contents($barrier, 'go');
        array_walk($processes, fn (Process $process) => $process->wait());

        foreach ($processes as $process) {
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }

        $outputs = collect($processes)->map(fn (Process $process): string => trim($process->getOutput()));
        $this->assertSame(1, $outputs->filter(fn (string $output): bool => str_starts_with($output, 'ok:'))->count(), $outputs->toJson());
        $this->assertSame(1, $outputs->filter(fn (string $output): bool => $output === 'conflict')->count(), $outputs->toJson());

        $document->refresh();
        $recipient->refresh();
        $this->assertSame($documentVersion + 1, $document->lock_version);
        $this->assertContains($document->general_status, [DocumentStatus::ANNULLED, DocumentStatus::APPROVED]);

        if ($document->general_status === DocumentStatus::ANNULLED) {
            $this->assertSame(DocumentRecipientStatus::IN_REVIEW, $recipient->status);
        } else {
            $this->assertSame(DocumentRecipientStatus::APPROVED, $recipient->status);
            $service->annul(
                $administrator,
                $document,
                $document->lock_version,
                'Anulación posterior a la aprobación concurrente.',
            );
            $document->refresh();
        }

        $this->assertSame(DocumentStatus::ANNULLED, $document->general_status);
        $this->assertNotNull($document->annulled_at);
        $this->assertSame($administrator->id, $document->annulled_by);
        $this->assertNull($document->deleted_at);
        $this->assertSame(1, DB::table('document_events')
            ->where('document_id', $document->id)
            ->where('event_type', 'document_annulled')
            ->count());
    }

    public function test_concurrent_correction_preparation_creates_only_one_version_two(): void
    {
        [$document, $unit, $actor] = $this->context();
        $document->forceFill([
            'general_status' => DocumentStatus::RETURNED,
            'code' => 'FTEC-'.$unit->acronym.'-'.now()->year.'-000901',
            'sender_user_id' => $actor->id,
            'sent_at' => now()->subDay(),
        ])->save();
        DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'uploaded_by' => $actor->id,
        ]);
        DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'requires_response' => true,
            'status' => DocumentRecipientStatus::RETURNED,
            'received_by' => $actor->id,
            'received_at' => now()->subHours(2),
            'current_handler_id' => $actor->id,
            'decided_by' => $actor->id,
            'decided_at' => now()->subHour(),
            'decision_reason' => 'Debe corregirse.',
            'lock_version' => 3,
        ]);

        $uploadPath = tempnam(sys_get_temp_dir(), 'correction-pg-');
        file_put_contents($uploadPath, "%PDF-1.4\ncorrección concurrente");
        $this->temporaryFiles[] = $uploadPath;
        [$worker, $barrier] = $this->worker();
        $processes = [
            $this->startWorker($worker, $barrier, ['prepare', $actor->id, $document->id, $uploadPath, 0]),
            $this->startWorker($worker, $barrier, ['prepare', $actor->id, $document->id, $uploadPath, 0]),
        ];

        file_put_contents($barrier, 'go');
        array_walk($processes, fn (Process $process) => $process->wait());

        foreach ($processes as $process) {
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }

        $outputs = collect($processes)->map(fn (Process $process): string => trim($process->getOutput()));
        $this->assertSame(1, $outputs->filter(fn (string $output): bool => $output === 'ok:2')->count(), $outputs->toJson());
        $this->assertSame(1, $outputs->filter(fn (string $output): bool => $output === 'conflict')->count(), $outputs->toJson());
        $this->assertSame([1, 2], DocumentVersion::query()
            ->where('document_id', $document->id)
            ->orderBy('version_number')
            ->pluck('version_number')
            ->all());
        $this->assertSame(1, DB::table('document_events')
            ->where('document_id', $document->id)
            ->where('event_type', 'document_version_created')
            ->count());
    }

    /** @return array{Document, OrganizationalUnit, User} */
    private function context(): array
    {
        $unit = $this->career();
        $creator = $this->userWithRole(RoleName::SECRETARY, $unit);

        return [$this->draft($creator, $unit), $unit, $creator];
    }

    private function draft(User $creator, OrganizationalUnit $origin): Document
    {
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $origin->id,
            'created_by' => $creator->id,
            'subject' => 'Concurrencia PostgreSQL '.Str::random(8),
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
        ]);
        $this->documentIds[] = $document->id;

        return $document;
    }

    private function recipient(
        Document $document,
        OrganizationalUnit $unit,
        bool $requiresResponse = true,
        DocumentRecipientStatus $status = DocumentRecipientStatus::PREPARED,
    ): DocumentRecipient {
        return DocumentRecipient::query()->create([
            'document_id' => $document->id,
            'unit_id' => $unit->id,
            'requires_response' => $requiresResponse,
            'status' => $status,
            'lock_version' => 0,
        ]);
    }

    private function userWithRole(RoleName $role, ?OrganizationalUnit $unit = null): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit?->id]);
        $user->assignRole($role->value);
        $this->userIds[] = $user->id;

        return $user;
    }

    private function career(): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();
        $unit = OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera Concurrencia '.Str::random(8),
            'acronym' => Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $this->unitIds[] = $unit->id;

        return $unit;
    }

    /** @return array{string, string} */
    private function worker(): array
    {
        $worker = storage_path('framework/testing/workflow-worker-'.Str::uuid().'.php');
        $barrier = storage_path('framework/testing/workflow-barrier-'.Str::uuid());
        $script = <<<'PHP'
<?php
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$barrier = $argv[2];
$deadline = microtime(true) + 10;
while (! file_exists($barrier) && microtime(true) < $deadline) { usleep(10000); }
try {
    $actor = App\Models\User::query()->findOrFail((int) $argv[4]);
    $document = App\Models\Document::query()->findOrFail((int) $argv[5]);
    if ($argv[3] === 'send') {
        $service = app(App\Services\DocumentWorkflowService::class);
        echo 'ok:'.$service->send($actor, $document, 0)->code;
        exit(0);
    }
    if ($argv[3] === 'prepare') {
        $file = new Illuminate\Http\UploadedFile($argv[6], 'correccion.pdf', 'application/pdf', null, true);
        $version = app(App\Services\DocumentFileService::class)->prepareCorrection(
            $actor,
            $document,
            [$file],
            (int) $argv[7],
        );
        echo 'ok:'.$version->version_number;
        exit(0);
    }
    if ($argv[3] === 'annul') {
        $service = app(App\Services\DocumentWorkflowService::class);
        $annulled = $service->annul($actor, $document, (int) $argv[6], $argv[7]);
        echo 'ok:'.$annulled->general_status->value;
        exit(0);
    }
    $service = app(App\Services\DocumentWorkflowService::class);
    $recipient = App\Models\DocumentRecipient::query()->findOrFail((int) $argv[6]);
    $transition = App\Enums\DocumentTransition::from($argv[7]);
    $service->transition($actor, $document, $recipient, $transition, (int) $argv[8], (int) $argv[9], $argv[10] ?: null);
    echo 'ok:'.$transition->value;
} catch (Illuminate\Validation\ValidationException|Illuminate\Auth\Access\AuthorizationException $exception) {
    echo 'conflict';
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

    /** @param array<int, int|string> $arguments */
    private function startWorker(string $worker, string $barrier, array $arguments): Process
    {
        $process = new Process([
            PHP_BINARY,
            $worker,
            base_path(),
            $barrier,
            ...array_map(strval(...), $arguments),
        ], base_path(), timeout: 30);
        $process->start();

        return $process;
    }

    /** @param callable(): void $callback */
    private function assertConstraintViolation(callable $callback, string $sqlState): void
    {
        DB::beginTransaction();

        try {
            $callback();
            $this->fail('PostgreSQL aceptó una transición documental inválida.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }
}
