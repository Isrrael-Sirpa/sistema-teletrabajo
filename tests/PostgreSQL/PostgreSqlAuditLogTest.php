<?php

namespace Tests\PostgreSQL;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgreSqlAuditLogTest extends TestCase
{
    /** @var array<int, int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        DB::table('audit_logs')->whereIn('actor_id', $this->userIds)->delete();
        DB::table('users')->whereIn('id', $this->userIds)->delete();

        parent::tearDown();
    }

    public function test_postgresql_preserves_audit_types_indexes_foreign_key_and_json_values(): void
    {
        $user = User::factory()->create();
        $this->userIds[] = $user->id;
        $log = app(AuditLogService::class)->record(
            $user,
            AuditAction::USER_UPDATED,
            $user,
            ['status' => 'inactive'],
            ['status' => 'active'],
        );

        $columns = collect(DB::select(<<<'SQL'
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'audit_logs'
              AND column_name IN ('ip_address', 'old_values', 'new_values', 'request_id', 'occurred_at')
            SQL))->pluck('data_type', 'column_name');
        $indexes = collect(DB::select(<<<'SQL'
            SELECT indexname
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND tablename = 'audit_logs'
            SQL))->pluck('indexname');

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('inet', $columns['ip_address']);
        $this->assertSame('jsonb', $columns['old_values']);
        $this->assertSame('jsonb', $columns['new_values']);
        $this->assertSame('uuid', $columns['request_id']);
        $this->assertSame('timestamp with time zone', $columns['occurred_at']);
        $this->assertTrue($indexes->contains('audit_logs_actor_id_occurred_at_index'));
        $this->assertTrue($indexes->contains('audit_logs_auditable_type_auditable_id_index'));
        $this->assertTrue($indexes->contains('audit_logs_action_occurred_at_index'));
        $this->assertSame('inactive', $log->fresh()->old_values['status']);
        $this->assertSame('active', $log->fresh()->new_values['status']);

        $this->assertConstraintViolation(
            fn () => DB::table('users')->where('id', $user->id)->delete(),
            '23001',
        );
        $this->assertConstraintViolation(
            fn () => DB::table('audit_logs')->insert($this->row($user, ['ip_address' => '999.999.999.999'])),
            '22P02',
        );
        $this->assertConstraintViolation(
            fn () => DB::table('audit_logs')->insert($this->row($user, ['request_id' => 'no-es-uuid'])),
            '22P02',
        );
    }

    /** @param array<string, mixed> $overrides */
    private function row(User $user, array $overrides): array
    {
        return [
            'actor_id' => $user->id,
            'action' => AuditAction::USER_UPDATED->value,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'ip_address' => '192.0.2.10',
            'user_agent' => 'Prueba PostgreSQL',
            'old_values' => json_encode(['status' => 'inactive'], JSON_THROW_ON_ERROR),
            'new_values' => json_encode(['status' => 'active'], JSON_THROW_ON_ERROR),
            'request_id' => (string) Str::uuid(),
            'occurred_at' => now(),
            ...$overrides,
        ];
    }

    /** @param callable(): void $callback */
    private function assertConstraintViolation(callable $callback, string $sqlState): void
    {
        DB::beginTransaction();
        try {
            $callback();
            $this->fail('PostgreSQL aceptó un dato de auditoría inválido.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        } finally {
            DB::rollBack();
        }
    }
}
