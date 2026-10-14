<?php

namespace Tests\PostgreSQL;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\TemporaryDelegation;
use App\Models\User;
use App\Services\TemporaryDelegationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgreSqlTemporaryDelegationTest extends TestCase
{
    /** @var array<int, int> */
    private array $userIds = [];

    /** @var array<int, int> */
    private array $unitIds = [];

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        $delegationIds = DB::table('temporary_delegations')
            ->whereIn('organizational_unit_id', $this->unitIds)
            ->pluck('id');

        DB::table('audit_logs')->whereIn('actor_id', $this->userIds)->delete();
        DB::table('temporary_delegation_permissions')->whereIn('temporary_delegation_id', $delegationIds)->delete();
        DB::table('temporary_delegations')->whereIn('id', $delegationIds)->delete();
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

    public function test_postgresql_schema_preserves_delegation_history_and_restricts_references(): void
    {
        [$administrator, $career, $head, $secretary] = $this->context();
        $delegation = $this->createDelegation($administrator, $career, $head, $secretary);
        $permission = Permission::findByName(PermissionName::USERS_VIEW->value, 'web');

        $columns = collect(DB::select(<<<'SQL'
            SELECT column_name, data_type, is_nullable
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'temporary_delegations'
              AND column_name IN ('delegator_user_id', 'starts_at', 'ends_at')
            SQL))->keyBy('column_name');
        $foreignKeys = collect(DB::select(<<<'SQL'
            SELECT conname, confdeltype
            FROM pg_constraint
            WHERE connamespace = current_schema()::regnamespace
              AND conrelid IN ('temporary_delegations'::regclass, 'temporary_delegation_permissions'::regclass)
              AND contype = 'f'
            SQL));
        $primaryKey = DB::selectOne(<<<'SQL'
            SELECT pg_get_constraintdef(oid) AS definition
            FROM pg_constraint
            WHERE connamespace = current_schema()::regnamespace
              AND conrelid = 'temporary_delegation_permissions'::regclass
              AND contype = 'p'
            SQL);

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('NO', $columns['delegator_user_id']->is_nullable);
        $this->assertSame('timestamp with time zone', $columns['starts_at']->data_type);
        $this->assertSame('timestamp with time zone', $columns['ends_at']->data_type);
        $this->assertNotEmpty($foreignKeys);
        $this->assertTrue($foreignKeys->every(fn (object $key): bool => $key->confdeltype === 'r'));
        $this->assertStringContainsString(
            'temporary_delegation_id, permission_id',
            $primaryKey->definition,
        );

        $this->assertConstraintViolation(fn () => DB::table('temporary_delegation_permissions')->insert([
            'temporary_delegation_id' => $delegation->id,
            'permission_id' => $permission->id,
            'created_at' => now(),
        ]), '23505');
        $this->assertConstraintViolation(fn () => DB::table('temporary_delegation_permissions')->insert([
            'temporary_delegation_id' => PHP_INT_MAX,
            'permission_id' => $permission->id,
            'created_at' => now(),
        ]), '23503');
        $this->assertConstraintViolation(fn () => DB::table('temporary_delegations')->insert([
            'public_id' => (string) Str::uuid(),
            'delegator_user_id' => $head->id,
            'delegate_user_id' => $secretary->id,
            'organizational_unit_id' => $career->id,
            'authorized_by' => $administrator->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now(),
            'reason' => 'Intervalo inválido.',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]), '23514');
    }

    public function test_postgresql_allows_adjacent_half_open_intervals(): void
    {
        [$administrator, $career, $head, $secretary] = $this->context();
        $secondSecretary = $this->user(RoleName::SECRETARY, $career);
        $start = now()->addDay()->startOfMinute();
        $boundary = $start->copy()->addDays(4);

        $first = $this->createDelegation($administrator, $career, $head, $secretary, $start, $boundary);
        $second = $this->createDelegation($administrator, $career, $head, $secondSecretary, $boundary, $boundary->copy()->addDays(3));

        $this->assertSame($first->ends_at->timestamp, $second->starts_at->timestamp);
        $this->assertSame(2, TemporaryDelegation::query()->where('organizational_unit_id', $career->id)->count());
    }

    public function test_two_independent_postgresql_processes_cannot_create_overlapping_headships(): void
    {
        [$administrator, $career, $head, $secretary] = $this->context();
        $secondSecretary = $this->user(RoleName::SECRETARY, $career);
        [$worker, $barrier] = $this->worker();
        $start = now()->subHour()->toIso8601String();
        $end = now()->addDays(2)->toIso8601String();
        $processes = [
            $this->startWorker($worker, $barrier, $administrator, $career, $head, $secretary, $start, $end),
            $this->startWorker($worker, $barrier, $administrator, $career, $head, $secondSecretary, $start, $end),
        ];

        file_put_contents($barrier, 'go');
        array_walk($processes, fn (Process $process) => $process->wait());

        foreach ($processes as $process) {
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }

        $results = collect($processes)
            ->map(fn (Process $process): string => trim($process->getOutput()))
            ->sort()->values()->all();
        $this->assertSame(['created', 'rejected'], $results);
        $this->assertSame(1, TemporaryDelegation::query()
            ->where('organizational_unit_id', $career->id)
            ->whereNull('revoked_at')
            ->count());
    }

    /** @return array{User, OrganizationalUnit, User, User} */
    private function context(): array
    {
        $faculty = OrganizationalUnit::query()->where('type', 'faculty')->firstOrFail();
        $career = OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => 'career',
            'name' => 'Carrera Delegaciones PG '.Str::random(8),
            'acronym' => Str::upper(Str::random(7)),
            'is_active' => true,
        ]);
        $this->unitIds[] = $career->id;

        return [
            $this->user(RoleName::ADMINISTRATOR),
            $career,
            $this->user(RoleName::CAREER_HEAD, $career),
            $this->user(RoleName::SECRETARY, $career),
        ];
    }

    private function user(RoleName $role, ?OrganizationalUnit $unit = null): User
    {
        $user = User::factory()->create(['organizational_unit_id' => $unit?->id]);
        $user->assignRole($role->value);
        $this->userIds[] = $user->id;

        return $user;
    }

    private function createDelegation(
        User $administrator,
        OrganizationalUnit $career,
        User $head,
        User $secretary,
        $startsAt = null,
        $endsAt = null,
    ): TemporaryDelegation {
        return app(TemporaryDelegationService::class)->create($administrator, [
            'organizational_unit_id' => $career->id,
            'delegator_user_id' => $head->id,
            'delegate_user_id' => $secretary->id,
            'starts_at' => ($startsAt ?? now()->subHour())->toIso8601String(),
            'ends_at' => ($endsAt ?? now()->addDay())->toIso8601String(),
            'reason' => 'Prueba PostgreSQL de delegación.',
            'permissions' => [PermissionName::USERS_VIEW->value],
        ]);
    }

    /** @param callable(): void $callback */
    private function assertConstraintViolation(callable $callback, string $sqlState): void
    {
        DB::beginTransaction();
        try {
            $callback();
            $this->fail('PostgreSQL aceptó datos inválidos para una delegación.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    /** @return array{string, string} */
    private function worker(): array
    {
        $worker = storage_path('framework/testing/delegation-worker-'.Str::uuid().'.php');
        $barrier = storage_path('framework/testing/delegation-barrier-'.Str::uuid());
        $script = <<<'PHP'
<?php
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$deadline = microtime(true) + 10;
while (! file_exists($argv[2]) && microtime(true) < $deadline) { usleep(10000); }
try {
    $actor = App\Models\User::findOrFail((int) $argv[3]);
    app(App\Services\TemporaryDelegationService::class)->create($actor, [
        'organizational_unit_id' => (int) $argv[4],
        'delegator_user_id' => (int) $argv[5],
        'delegate_user_id' => (int) $argv[6],
        'starts_at' => $argv[7],
        'ends_at' => $argv[8],
        'reason' => 'Alta concurrente PostgreSQL.',
        'permissions' => [App\Enums\PermissionName::USERS_VIEW->value],
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
        User $administrator,
        OrganizationalUnit $career,
        User $head,
        User $secretary,
        string $startsAt,
        string $endsAt,
    ): Process {
        $process = new Process([
            PHP_BINARY,
            $worker,
            base_path(),
            $barrier,
            (string) $administrator->id,
            (string) $career->id,
            (string) $head->id,
            (string) $secretary->id,
            $startsAt,
            $endsAt,
        ], base_path(), timeout: 30);
        $process->start();

        return $process;
    }
}
