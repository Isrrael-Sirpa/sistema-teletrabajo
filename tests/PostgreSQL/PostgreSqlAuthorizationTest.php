<?php

namespace Tests\PostgreSQL;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgreSqlAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_authorization_catalog_is_seeded_in_postgresql(): void
    {
        $this->assertSame(count(RoleName::cases()), Role::query()->count());
        $this->assertSame(count(PermissionName::cases()), Permission::query()->count());

        foreach (RoleName::cases() as $role) {
            $this->assertTrue(Role::query()->where('name', $role->value)->exists());
        }
    }

    public function test_postgresql_seeding_is_idempotent(): void
    {
        $before = [
            Role::query()->count(),
            Permission::query()->count(),
            DB::table('role_has_permissions')->count(),
        ];

        $this->seed(AuthorizationSeeder::class);
        $this->seed(AuthorizationSeeder::class);

        $this->assertSame($before, [
            Role::query()->count(),
            Permission::query()->count(),
            DB::table('role_has_permissions')->count(),
        ]);
    }

    public function test_a_postgresql_user_can_receive_a_role_without_hardcoded_ids(): void
    {
        $user = User::factory()->create([
            'email' => 'rbac.postgresql@example.test',
        ]);

        $user->assignRole(RoleName::SECRETARY_ASSISTANT->value);

        $this->assertTrue($user->hasRole(RoleName::SECRETARY_ASSISTANT->value));
        $this->assertTrue($user->can(PermissionName::DOCUMENTS_REVIEW->value));
        $this->assertFalse($user->can(PermissionName::DOCUMENTS_APPROVE->value));
    }

    public function test_postgresql_enforces_rbac_name_format_constraints(): void
    {
        $constraints = DB::table('pg_constraint')
            ->whereRaw('connamespace = current_schema()::regnamespace')
            ->whereIn('conname', ['roles_name_format_check', 'permissions_name_format_check'])
            ->pluck('conname')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'permissions_name_format_check',
            'roles_name_format_check',
        ], $constraints);
    }

    public function test_cascading_deletes_are_confined_to_rbac_pivot_tables(): void
    {
        $tables = collect(DB::select(<<<'SQL'
            select distinct conrelid::regclass::text as table_name
            from pg_constraint
            where connamespace = current_schema()::regnamespace
              and contype = 'f'
              and confdeltype = 'c'
            order by table_name
            SQL))
            ->pluck('table_name')
            ->all();

        $this->assertSame([
            'model_has_permissions',
            'model_has_roles',
            'role_has_permissions',
        ], $tables);
    }
}
