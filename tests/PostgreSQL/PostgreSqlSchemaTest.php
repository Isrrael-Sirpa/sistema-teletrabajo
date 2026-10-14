<?php

namespace Tests\PostgreSQL;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgreSqlSchemaTest extends TestCase
{
    public function test_laravel_uses_the_expected_postgresql_database(): void
    {
        $connection = DB::selectOne('select current_database() as database, version() as version');

        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('sistema_teletrabajo', $connection->database);
        $this->assertStringContainsString('PostgreSQL 18.3', $connection->version);
    }

    public function test_all_migrations_are_registered_as_executed(): void
    {
        $migrationFiles = glob(database_path('migrations/*.php')) ?: [];

        $this->assertCount(count($migrationFiles), DB::table('migrations')->get());
    }

    public function test_critical_constraints_and_indexes_exist(): void
    {
        $constraintNames = collect(DB::select(
            'select conname from pg_constraint where connamespace = current_schema()::regnamespace'
        ))->pluck('conname');

        foreach ([
            'units_type_check',
            'docs_status_check',
            'docs_sent_fields_check',
            'docs_annul_check',
            'recipients_target_unique',
            'recipient_reason_check',
            'access_revocation_check',
            'delegation_revocation_check',
        ] as $constraint) {
            $this->assertContains($constraint, $constraintNames);
        }

        $indexNames = collect(DB::select(
            'select indexname from pg_indexes where schemaname = current_schema()'
        ))->pluck('indexname');

        foreach ([
            'units_singleton_type_unique',
            'documents_due_open_index',
            'active_access_grants_unique',
        ] as $index) {
            $this->assertContains($index, $indexNames);
        }
    }

    public function test_documental_foreign_keys_do_not_cascade_deletes(): void
    {
        $cascadeCount = DB::table('pg_constraint')
            ->whereRaw('connamespace = current_schema()::regnamespace')
            ->where('contype', 'f')
            ->where('confdeltype', 'c')
            ->whereRaw("conrelid::regclass::text NOT IN ('model_has_permissions', 'model_has_roles', 'role_has_permissions')")
            ->count();

        $this->assertSame(0, $cascadeCount);
    }

    public function test_every_foreign_key_has_a_supporting_index(): void
    {
        $missingIndexes = DB::select(<<<'SQL'
            select constraint_data.table_name, constraint_data.constraint_name
            from (
                select
                    constraint_row.conrelid,
                    constraint_row.conkey,
                    constraint_row.conrelid::regclass::text as table_name,
                    constraint_row.conname as constraint_name
                from pg_constraint as constraint_row
                where constraint_row.connamespace = current_schema()::regnamespace
                  and constraint_row.contype = 'f'
            ) as constraint_data
            where not exists (
                select 1
                from pg_index as index_row
                where index_row.indrelid = constraint_data.conrelid
                  and index_row.indisvalid
                  and constraint_data.conkey <@ index_row.indkey::smallint[]
            )
            order by constraint_data.table_name, constraint_data.constraint_name
            SQL);

        $this->assertSame([], $missingIndexes);
    }

    public function test_postgresql_specific_column_types_are_in_use(): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('table_name', ['audit_logs', 'document_events'])
            ->whereIn('column_name', ['ip_address', 'metadata', 'occurred_at'])
            ->pluck('udt_name', 'column_name');

        $this->assertSame('inet', $columns['ip_address']);
        $this->assertSame('jsonb', $columns['metadata']);
        $this->assertSame('timestamptz', $columns['occurred_at']);
    }

    public function test_postgresql_rejects_an_invalid_organizational_unit_type(): void
    {
        DB::beginTransaction();

        try {
            DB::table('organizational_units')->insert([
                'public_id' => (string) Str::uuid(),
                'type' => 'invalid',
                'name' => 'Unidad inválida temporal',
                'acronym' => 'UIT',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('PostgreSQL accepted an invalid organizational unit type.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }
}
