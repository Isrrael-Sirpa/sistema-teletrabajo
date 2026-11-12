<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\RbacCatalog;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);

        Route::middleware(['web', 'auth', 'active.user', 'permission:roles.manage'])
            ->get('/_tests/protected-by-permission', fn (): string => 'Acceso autorizado');
    }

    public function test_the_institutional_roles_exist_with_spanish_names(): void
    {
        $this->assertSame(
            collect(RoleName::cases())->pluck('value')->sort()->values()->all(),
            Role::query()->pluck('name')->sort()->values()->all(),
        );

        foreach (RoleName::cases() as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role->value,
                'display_name' => $role->label(),
                'guard_name' => RbacCatalog::GUARD,
            ]);
        }
    }

    public function test_all_declared_permissions_exist(): void
    {
        $expected = collect(PermissionName::cases())->pluck('value')->sort()->values()->all();

        $this->assertSame($expected, Permission::query()->pluck('name')->sort()->values()->all());
    }

    public function test_administrator_receives_every_declared_permission(): void
    {
        $this->assertRoleHasExactly(
            RoleName::ADMINISTRATOR,
            array_column(PermissionName::cases(), 'value'),
        );
    }

    public function test_co_administrator_receives_every_declared_permission(): void
    {
        $this->assertRoleHasExactly(
            RoleName::CO_ADMINISTRATOR,
            array_column(PermissionName::cases(), 'value'),
        );
    }

    public function test_career_head_receives_only_its_configured_permissions(): void
    {
        $this->assertRoleHasExactly(
            RoleName::CAREER_HEAD,
            RbacCatalog::rolePermissions()[RoleName::CAREER_HEAD->value],
        );

        $role = Role::findByName(RoleName::CAREER_HEAD->value);

        $this->assertTrue($role->hasPermissionTo(PermissionName::DOCUMENTS_APPROVE->value));
        $this->assertFalse($role->hasPermissionTo(PermissionName::REPORTS_VIEW_GLOBAL->value));
        $this->assertFalse($role->hasPermissionTo(PermissionName::ROLES_MANAGE->value));
    }

    public function test_secretary_receives_the_configured_document_permissions(): void
    {
        $this->assertRoleHasExactly(
            RoleName::SECRETARY,
            RbacCatalog::rolePermissions()[RoleName::SECRETARY->value],
        );

        $role = Role::findByName(RoleName::SECRETARY->value);

        $this->assertFalse($role->hasPermissionTo(PermissionName::DOCUMENTS_APPROVE->value));
        $this->assertFalse($role->hasPermissionTo(PermissionName::DOCUMENTS_RETURN->value));
        $this->assertFalse($role->hasPermissionTo(PermissionName::DOCUMENTS_REJECT->value));
        $this->assertFalse($role->hasPermissionTo(PermissionName::DOCUMENTS_ANNUL->value));
    }

    public function test_secretary_assistant_cannot_make_definitive_decisions(): void
    {
        $role = Role::findByName(RoleName::SECRETARY_ASSISTANT->value);

        $this->assertTrue($role->hasPermissionTo(PermissionName::DOCUMENTS_REVIEW->value));
        $this->assertTrue($role->hasPermissionTo(PermissionName::DOCUMENTS_ASSISTANT_REVIEW->value));
        $this->assertFalse($role->hasPermissionTo(PermissionName::DOCUMENTS_APPROVE->value));
        $this->assertFalse($role->hasPermissionTo(PermissionName::DOCUMENTS_RETURN->value));
        $this->assertFalse($role->hasPermissionTo(PermissionName::DOCUMENTS_REJECT->value));
        $this->assertFalse($role->hasPermissionTo(PermissionName::DOCUMENTS_ANNUL->value));
    }

    public function test_extra_has_only_document_view_and_download_permissions(): void
    {
        $this->assertRoleHasExactly(RoleName::EXTRA, [
            PermissionName::DOCUMENTS_VIEW->value,
            PermissionName::DOCUMENTS_DOWNLOAD->value,
        ]);

        $role = Role::findByName(RoleName::EXTRA->value);

        foreach ([
            PermissionName::DOCUMENTS_CREATE,
            PermissionName::DOCUMENTS_UPDATE_DRAFT,
            PermissionName::DOCUMENTS_SEND,
            PermissionName::DOCUMENTS_APPROVE,
            PermissionName::USERS_UPDATE,
            PermissionName::UNITS_UPDATE,
        ] as $permission) {
            $this->assertFalse($role->hasPermissionTo($permission->value));
        }
    }

    public function test_authorization_seeders_are_idempotent(): void
    {
        $roleHasPermissionsTable = config('permission.table_names.role_has_permissions', 'rol_tiene_permisos');

        $initialCounts = [
            'roles' => Role::query()->count(),
            'permissions' => Permission::query()->count(),
            'assignments' => DB::table($roleHasPermissionsTable)->count(),
        ];

        $this->seed(AuthorizationSeeder::class);
        $this->seed(AuthorizationSeeder::class);

        $this->assertSame($initialCounts['roles'], Role::query()->count());
        $this->assertSame($initialCounts['permissions'], Permission::query()->count());
        $this->assertSame($initialCounts['assignments'], DB::table($roleHasPermissionsTable)->count());
    }

    public function test_a_user_can_receive_a_role_and_permissions_through_that_role(): void
    {
        $user = User::factory()->create();

        $user->assignRole(RoleName::SECRETARY->value);

        $this->assertTrue($user->hasRole(RoleName::SECRETARY->value));
        $this->assertTrue($user->can(PermissionName::DOCUMENTS_SEND->value));
        $this->assertFalse($user->can(PermissionName::DOCUMENTS_APPROVE->value));
        $this->assertFalse($user->can(PermissionName::ROLES_MANAGE->value));
    }

    public function test_permission_middleware_allows_and_denies_in_the_backend(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole(RoleName::ADMINISTRATOR->value);

        $extra = User::factory()->create();
        $extra->assignRole(RoleName::EXTRA->value);

        $this->actingAs($administrator)
            ->get('/_tests/protected-by-permission')
            ->assertOk()
            ->assertSee('Acceso autorizado');

        $this->actingAs($extra)
            ->get('/_tests/protected-by-permission')
            ->assertForbidden();
    }

    /**
     * @param  array<int, string>  $expectedPermissions
     */
    private function assertRoleHasExactly(RoleName $roleName, array $expectedPermissions): void
    {
        $actual = Role::findByName($roleName->value)
            ->permissions
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        sort($expectedPermissions);

        $this->assertSame($expectedPermissions, $actual);
    }
}
