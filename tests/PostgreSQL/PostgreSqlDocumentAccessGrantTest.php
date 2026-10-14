<?php

namespace Tests\PostgreSQL;

use App\Enums\DocumentConfidentiality;
use App\Enums\DocumentPriority;
use App\Enums\DocumentStatus;
use App\Enums\RoleName;
use App\Models\Document;
use App\Models\DocumentAccessGrant;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgreSqlDocumentAccessGrantTest extends TestCase
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
        DB::table('audit_logs')->whereIn('actor_id', $this->userIds)->delete();
        DB::table('document_access_grants')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('document_events')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('document_recipients')->whereIn('document_id', $this->documentIds)->delete();
        DB::table('documents')->whereIn('id', $this->documentIds)->delete();
        DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $this->userIds)->delete();
        DB::table('users')->whereIn('id', $this->userIds)->delete();
        DB::table('organizational_units')->whereIn('id', $this->unitIds)->delete();

        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_postgresql_constraints_and_timestamptz_support_grant_history(): void
    {
        [$admin, $extra, $document] = $this->context();
        $grant = $this->grant($admin, $extra, $document);
        $column = DB::selectOne(<<<'SQL'
            SELECT data_type
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'document_access_grants'
              AND column_name = 'ends_at'
            SQL);
        $index = DB::selectOne(<<<'SQL'
            SELECT indexdef
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND indexname = 'active_access_grants_unique'
            SQL);

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('timestamp with time zone', $column->data_type);
        $this->assertStringContainsString('WHERE (revoked_at IS NULL)', $index->indexdef);

        $this->assertConstraintViolation(fn () => DB::table('document_access_grants')->insert([
            ...$this->grantRow($admin, $extra, $document),
            'permission' => 'view',
        ]), '23505');
        $this->assertConstraintViolation(fn () => DB::table('document_access_grants')->insert([
            ...$this->grantRow($admin, $extra, $document),
            'user_id' => PHP_INT_MAX,
            'permission' => 'download',
        ]), '23503');
        $this->assertConstraintViolation(fn () => DB::table('document_access_grants')->insert([
            ...$this->grantRow($admin, $extra, $document),
            'permission' => 'edit',
        ]), '23514');

        $grant->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $admin->id,
            'revocation_reason' => 'Cierre histórico controlado.',
        ])->save();
        $this->assertDatabaseHas('document_access_grants', [
            'id' => $grant->id,
            'revoked_by' => $admin->id,
            'revocation_reason' => 'Cierre histórico controlado.',
        ]);
    }

    public function test_two_concurrent_grants_for_same_document_and_user_create_one_active_cycle(): void
    {
        [$admin, $extra, $document] = $this->context();
        [$worker, $barrier] = $this->worker();
        $processes = [
            $this->startWorker($worker, $barrier, $admin, $extra, $document, 'view'),
            $this->startWorker($worker, $barrier, $admin, $extra, $document, 'download'),
        ];

        file_put_contents($barrier, 'go');
        array_walk($processes, fn (Process $process) => $process->wait());

        foreach ($processes as $process) {
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }

        $results = collect($processes)->map(fn (Process $process): string => trim($process->getOutput()))->sort()->values()->all();
        $this->assertSame(['created', 'rejected'], $results);
        $this->assertSame(1, DocumentAccessGrant::query()
            ->where('document_id', $document->id)
            ->where('user_id', $extra->id)
            ->whereNull('revoked_at')
            ->count());
    }

    /** @return array{User, User, Document} */
    private function context(): array
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();
        $career = OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera Accesos PG '.Str::random(8),
            'acronym' => Str::upper(Str::random(7)),
            'is_active' => true,
        ]);
        $this->unitIds[] = $career->id;
        $admin = $this->user(RoleName::ADMINISTRATOR);
        $creator = $this->user(RoleName::SECRETARY, $career);
        $extra = $this->user(RoleName::EXTRA, $career);
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->where('is_active', true)->firstOrFail()->id,
            'origin_unit_id' => $career->id,
            'created_by' => $creator->id,
            'subject' => 'Acceso PostgreSQL '.Str::random(8),
            'general_status' => DocumentStatus::DRAFT,
            'priority' => DocumentPriority::NORMAL,
            'confidentiality' => DocumentConfidentiality::NORMAL,
            'lock_version' => 0,
        ]);
        $this->documentIds[] = $document->id;

        return [$admin, $extra, $document];
    }

    private function user(RoleName $role, ?OrganizationalUnit $unit = null): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit?->id]);
        $user->assignRole($role->value);
        $this->userIds[] = $user->id;

        return $user;
    }

    private function grant(User $admin, User $extra, Document $document): DocumentAccessGrant
    {
        return DocumentAccessGrant::query()->create($this->grantRow($admin, $extra, $document));
    }

    /** @return array<string, mixed> */
    private function grantRow(User $admin, User $extra, Document $document): array
    {
        return [
            'document_id' => $document->id,
            'user_id' => $extra->id,
            'granted_by' => $admin->id,
            'permission' => 'view',
            'starts_at' => now(),
            'ends_at' => null,
            'reason' => 'Prueba PostgreSQL.',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** @param callable(): void $callback */
    private function assertConstraintViolation(callable $callback, string $sqlState): void
    {
        DB::beginTransaction();
        try {
            $callback();
            $this->fail('PostgreSQL aceptó una concesión inválida.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        } finally {
            DB::rollBack();
        }
    }

    /** @return array{string, string} */
    private function worker(): array
    {
        $worker = storage_path('framework/testing/access-grant-worker-'.Str::uuid().'.php');
        $barrier = storage_path('framework/testing/access-grant-barrier-'.Str::uuid());
        $script = <<<'PHP'
<?php
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$deadline = microtime(true) + 10;
while (! file_exists($argv[2]) && microtime(true) < $deadline) { usleep(10000); }
try {
    $actor = App\Models\User::findOrFail((int) $argv[3]);
    $extra = App\Models\User::findOrFail((int) $argv[4]);
    $document = App\Models\Document::findOrFail((int) $argv[5]);
    app(App\Services\DocumentAccessGrantService::class)->grant($actor, $document, [
        'user_id' => $extra->id,
        'permission' => $argv[6],
        'ends_at' => null,
    ]);
    echo 'created';
} catch (Illuminate\Validation\ValidationException) {
    echo 'rejected';
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
        User $admin,
        User $extra,
        Document $document,
        string $permission,
    ): Process {
        $process = new Process([
            PHP_BINARY,
            $worker,
            base_path(),
            $barrier,
            (string) $admin->id,
            (string) $extra->id,
            (string) $document->id,
            $permission,
        ], base_path(), timeout: 30);
        $process->start();

        return $process;
    }
}
