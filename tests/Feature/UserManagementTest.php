<?php

namespace Tests\Feature;

use App\Enums\OrganizationalUnitType;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\UserManagementService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\InstitutionalStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationalUnit $careerA;

    private OrganizationalUnit $careerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AuthorizationSeeder::class,
            InstitutionalStructureSeeder::class,
        ]);

        $this->careerA = $this->createCareer('Carrera Alfa', 'ALFA');
        $this->careerB = $this->createCareer('Carrera Beta', 'BETA');
    }

    public function test_administrator_and_co_administrator_can_list_all_users(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR, null, 'admin@test.local');
        $coAdministrator = $this->userWithRole(RoleName::CO_ADMINISTRATOR, null, 'coadmin@test.local');
        $secretary = $this->userWithRole(RoleName::SECRETARY, $this->careerA, 'secretaria@test.local');

        $this->actingAs($administrator)->get(route('users.index'))
            ->assertOk()->assertSee($coAdministrator->email)->assertSee($secretary->email);

        $this->actingAs($coAdministrator)->get(route('users.index'))
            ->assertOk()->assertSee($administrator->email)->assertSee($secretary->email);
    }

    public function test_career_head_only_lists_and_views_users_from_own_career(): void
    {
        $head = $this->userWithRole(RoleName::CAREER_HEAD, $this->careerA, 'jefe@test.local');
        $ownSecretary = $this->userWithRole(RoleName::SECRETARY, $this->careerA, 'propia@test.local');
        $otherSecretary = $this->userWithRole(RoleName::SECRETARY, $this->careerB, 'ajena@test.local');

        $this->actingAs($head)->get(route('users.index'))
            ->assertOk()
            ->assertSee($ownSecretary->email)
            ->assertDontSee($otherSecretary->email);

        $this->actingAs($head)->get(route('users.show', $otherSecretary))->assertForbidden();
    }

    public function test_administrator_can_create_each_of_the_six_roles(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        foreach (RoleName::cases() as $index => $role) {
            $response = $this->actingAs($administrator)->post(route('users.store'), [
                'name' => 'Usuario '.$role->label(),
                'email' => "rol{$index}@test.local",
                'password' => 'Clave#Segura2026',
                'password_confirmation' => 'Clave#Segura2026',
                'status' => UserStatus::ACTIVE->value,
                'role' => $role->value,
                'organizational_unit_id' => $this->careerA->id,
            ]);

            $created = User::query()->where('email', "rol{$index}@test.local")->firstOrFail();
            $response->assertRedirect(route('users.show', $created));
            $this->assertTrue($created->hasRole($role->value));
            $this->assertSame($role->isGlobal() ? null : $this->careerA->id, $created->organizational_unit_id);
            $this->assertTrue(Hash::check('Clave#Segura2026', $created->password));
        }
    }

    public function test_career_head_can_create_secondary_users_only_in_own_career(): void
    {
        $head = $this->userWithRole(RoleName::CAREER_HEAD, $this->careerA);

        foreach ([RoleName::SECRETARY, RoleName::SECRETARY_ASSISTANT, RoleName::EXTRA] as $index => $role) {
            $this->actingAs($head)->post(route('users.store'), [
                ...$this->validPayload($role, $this->careerA, "secundario{$index}@test.local"),
            ])->assertRedirect();

            $created = User::query()->where('email', "secundario{$index}@test.local")->firstOrFail();
            $this->assertTrue($created->hasRole($role->value));
            $this->assertSame($this->careerA->id, $created->organizational_unit_id);
        }
    }

    public function test_career_head_cannot_create_global_roles_or_users_in_another_career(): void
    {
        $head = $this->userWithRole(RoleName::CAREER_HEAD, $this->careerA);

        foreach ([RoleName::ADMINISTRATOR, RoleName::CO_ADMINISTRATOR, RoleName::CAREER_HEAD] as $index => $role) {
            $this->actingAs($head)
                ->from(route('users.create'))
                ->post(route('users.store'), $this->validPayload($role, $this->careerA, "prohibido{$index}@test.local"))
                ->assertRedirect(route('users.create'))
                ->assertSessionHasErrors('role');
        }

        $this->actingAs($head)
            ->from(route('users.create'))
            ->post(route('users.store'), $this->validPayload(RoleName::SECRETARY, $this->careerB, 'otra@test.local'))
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors('organizational_unit_id');
    }

    public function test_unit_roles_require_an_active_career_and_global_roles_store_null_unit(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        foreach ([RoleName::CAREER_HEAD, RoleName::SECRETARY, RoleName::SECRETARY_ASSISTANT, RoleName::EXTRA] as $index => $role) {
            $payload = $this->validPayload($role, null, "sinunidad{$index}@test.local");

            $this->actingAs($administrator)->post(route('users.store'), $payload)
                ->assertSessionHasErrors('organizational_unit_id');
        }

        foreach ([RoleName::ADMINISTRATOR, RoleName::CO_ADMINISTRATOR] as $index => $role) {
            $this->actingAs($administrator)->post(route('users.store'), [
                ...$this->validPayload($role, $this->careerA, "global{$index}@test.local"),
            ])->assertRedirect();

            $this->assertNull(User::query()->where('email', "global{$index}@test.local")->sole()->organizational_unit_id);
        }
    }

    public function test_kardex_or_an_inactive_career_cannot_be_assigned_to_a_unit_role(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $kardex = OrganizationalUnit::query()->where('type', OrganizationalUnitType::KARDEX)->sole();
        $this->careerB->update(['is_active' => false]);

        foreach ([$kardex, $this->careerB] as $index => $invalidUnit) {
            $this->actingAs($administrator)
                ->post(route('users.store'), $this->validPayload(RoleName::EXTRA, $invalidUnit, "unidadinvalida{$index}@test.local"))
                ->assertSessionHasErrors('organizational_unit_id');
        }
    }

    public function test_duplicate_email_is_rejected_ignoring_case_and_email_is_normalized(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        User::factory()->create(['email' => 'persona@test.local']);

        $this->actingAs($administrator)
            ->post(route('users.store'), $this->validPayload(RoleName::EXTRA, $this->careerA, 'PERSONA@TEST.LOCAL'))
            ->assertSessionHasErrors('email');

        $this->actingAs($administrator)
            ->post(route('users.store'), $this->validPayload(RoleName::EXTRA, $this->careerA, '  NUEVA@TEST.LOCAL  '))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'nueva@test.local']);
    }

    public function test_invalid_role_and_nonexistent_unit_are_rejected(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validPayload(RoleName::EXTRA, $this->careerA),
            'role' => 'super_admin',
        ])->assertSessionHasErrors('role');

        $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validPayload(RoleName::EXTRA, $this->careerA, 'unidad@test.local'),
            'organizational_unit_id' => 999999,
        ])->assertSessionHasErrors('organizational_unit_id');
    }

    public function test_user_without_permission_receives_forbidden(): void
    {
        $extra = $this->userWithRole(RoleName::EXTRA, $this->careerA);

        $this->actingAs($extra)->get(route('users.index'))->assertForbidden();
        $this->actingAs($extra)->get(route('users.create'))->assertForbidden();
    }

    public function test_deactivation_preserves_user_and_blocks_login(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $secretary = $this->userWithRole(RoleName::SECRETARY, $this->careerA, 'desactivar@test.local');
        $secretaryId = $secretary->id;

        $this->actingAs($administrator)->patch(route('users.deactivate', $secretary))->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $secretaryId, 'status' => UserStatus::INACTIVE->value]);
        $this->assertNotNull(User::query()->find($secretaryId));

        auth()->logout();
        $this->post(route('login.store'), ['email' => $secretary->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_role_changes_apply_the_required_unit_scope(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $secretary = $this->userWithRole(RoleName::SECRETARY, $this->careerA, 'cambiar@test.local');

        $this->actingAs($administrator)->put(route('users.update', $secretary), [
            'name' => $secretary->name,
            'email' => $secretary->email,
            'status' => UserStatus::ACTIVE->value,
            'role' => RoleName::ADMINISTRATOR->value,
            'organizational_unit_id' => $this->careerA->id,
        ])->assertRedirect();

        $secretary->refresh();
        $this->assertTrue($secretary->hasRole(RoleName::ADMINISTRATOR->value));
        $this->assertNull($secretary->organizational_unit_id);

        $this->actingAs($administrator)->put(route('users.update', $secretary), [
            'name' => $secretary->name,
            'email' => $secretary->email,
            'status' => UserStatus::ACTIVE->value,
            'role' => RoleName::CAREER_HEAD->value,
            'organizational_unit_id' => null,
        ])->assertSessionHasErrors('organizational_unit_id');
    }

    public function test_only_one_active_primary_career_head_is_allowed_per_career(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $this->userWithRole(RoleName::CAREER_HEAD, $this->careerA);

        $this->actingAs($administrator)
            ->post(route('users.store'), $this->validPayload(RoleName::CAREER_HEAD, $this->careerA, 'segundojefe@test.local'))
            ->assertSessionHasErrors('role');

        $this->actingAs($administrator)
            ->post(route('users.store'), [
                ...$this->validPayload(RoleName::CAREER_HEAD, $this->careerA, 'jefeinactivo@test.local'),
                'status' => UserStatus::INACTIVE->value,
            ])->assertRedirect();
    }

    public function test_co_administrator_cannot_modify_or_deactivate_an_administrator(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $coAdministrator = $this->userWithRole(RoleName::CO_ADMINISTRATOR);

        $this->actingAs($coAdministrator)->get(route('users.edit', $administrator))->assertForbidden();
        $this->actingAs($coAdministrator)->patch(route('users.deactivate', $administrator))->assertForbidden();
    }

    public function test_user_cannot_deactivate_self_or_change_own_role_and_status(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        $this->actingAs($administrator)->patch(route('users.deactivate', $administrator))->assertForbidden();

        $this->actingAs($administrator)->put(route('users.update', $administrator), [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'status' => UserStatus::INACTIVE->value,
            'role' => RoleName::CO_ADMINISTRATOR->value,
        ])->assertSessionHasErrors('status');
    }

    public function test_service_refuses_to_leave_the_system_without_an_active_administrator(): void
    {
        $onlyActiveAdministrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $inactiveAdministrator = $this->userWithRole(RoleName::ADMINISTRATOR, null, null, UserStatus::INACTIVE);

        $this->expectException(ValidationException::class);

        app(UserManagementService::class)->setActive($inactiveAdministrator, $onlyActiveAdministrator, false);
    }

    public function test_edit_does_not_change_password_and_reset_password_is_separate(): void
    {
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $secretary = $this->userWithRole(RoleName::SECRETARY, $this->careerA);
        $originalHash = $secretary->password;

        $this->actingAs($administrator)->put(route('users.update', $secretary), [
            'name' => 'Nombre Actualizado',
            'email' => $secretary->email,
            'status' => UserStatus::ACTIVE->value,
            'role' => RoleName::SECRETARY->value,
            'organizational_unit_id' => $this->careerA->id,
            'password' => 'NoDebeCambiar#2026',
        ])->assertRedirect();

        $this->assertSame($originalHash, $secretary->refresh()->password);

        $this->actingAs($administrator)->put(route('users.password.update', $secretary), [
            'password' => 'Nueva#Segura2026',
            'password_confirmation' => 'Nueva#Segura2026',
        ])->assertRedirect(route('users.show', $secretary));

        $this->assertTrue(Hash::check('Nueva#Segura2026', $secretary->refresh()->password));
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(RoleName $role, ?OrganizationalUnit $unit, ?string $email = null): array
    {
        return [
            'name' => 'Usuario de Prueba',
            'email' => $email ?? fake()->unique()->safeEmail(),
            'password' => 'Clave#Segura2026',
            'password_confirmation' => 'Clave#Segura2026',
            'status' => UserStatus::ACTIVE->value,
            'role' => $role->value,
            'organizational_unit_id' => $unit?->id,
        ];
    }

    private function userWithRole(
        RoleName $role,
        ?OrganizationalUnit $unit = null,
        ?string $email = null,
        UserStatus $status = UserStatus::ACTIVE,
    ): User {
        $user = User::factory()->create([
            'organizational_unit_id' => $unit?->id,
            'email' => $email ?? fake()->unique()->safeEmail(),
            'status' => $status->value,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function createCareer(string $name, string $acronym): OrganizationalUnit
    {
        $faculty = OrganizationalUnit::query()->where('type', OrganizationalUnitType::FACULTY)->sole();

        return OrganizationalUnit::query()->create([
            'parent_id' => $faculty->id,
            'type' => OrganizationalUnitType::CAREER,
            'name' => $name,
            'acronym' => $acronym,
            'is_active' => true,
        ]);
    }
}
