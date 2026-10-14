<?php

namespace Tests\PostgreSQL;

use App\Enums\OrganizationalUnitType;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDO;
use PDOException;
use Tests\TestCase;

class PostgreSqlUserManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_postgresql_enforces_user_status_email_and_unit_constraints(): void
    {
        $email = 'restriccion-'.Str::uuid().'@test.local';
        $this->insertUser(['email' => $email]);

        $this->assertConstraintViolation(
            fn () => $this->insertUser(['email' => Str::upper($email)]),
            '23505',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertUser(['status' => 'blocked']),
            '23514',
        );

        $this->assertConstraintViolation(
            fn () => $this->insertUser(['organizational_unit_id' => 999999999]),
            '23503',
        );
    }

    public function test_postgresql_user_creation_assigns_role_scope_and_hashed_password_atomically(): void
    {
        $career = $this->createCareer('Carrera PostgreSQL Uno', 'PGU');
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        $user = app(UserManagementService::class)->create($administrator, [
            'name' => '  Secretaria PostgreSQL  ',
            'email' => 'secretaria-pg-'.Str::uuid().'@test.local',
            'password' => 'Clave#PostgreSQL2026',
            'status' => UserStatus::ACTIVE->value,
            'role' => RoleName::SECRETARY->value,
            'organizational_unit_id' => $career->id,
        ]);

        $this->assertTrue($user->hasRole(RoleName::SECRETARY->value));
        $this->assertSame('Secretaria PostgreSQL', $user->name);
        $this->assertSame($career->id, $user->organizational_unit_id);
        $this->assertTrue(Hash::check('Clave#PostgreSQL2026', $user->password));
    }

    public function test_postgresql_serializes_the_active_career_head_rule_per_career(): void
    {
        $career = $this->createCareer('Carrera PostgreSQL Dos', 'PGD');
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);
        $service = app(UserManagementService::class);

        $service->create($administrator, $this->payload(RoleName::CAREER_HEAD, $career, 'jefe-uno'));

        try {
            $service->create($administrator, $this->payload(RoleName::CAREER_HEAD, $career, 'jefe-dos'));
            $this->fail('Se permitió un segundo Jefe de Carrera principal activo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }

        $this->assertSame(
            1,
            User::query()
                ->where('organizational_unit_id', $career->id)
                ->where('status', UserStatus::ACTIVE->value)
                ->role(RoleName::CAREER_HEAD->value)
                ->count(),
        );
    }

    public function test_global_roles_are_persisted_without_an_organizational_unit(): void
    {
        $career = $this->createCareer('Carrera PostgreSQL Tres', 'PGT');
        $administrator = $this->userWithRole(RoleName::ADMINISTRATOR);

        $coAdministrator = app(UserManagementService::class)->create($administrator, [
            ...$this->payload(RoleName::CO_ADMINISTRATOR, $career, 'coadmin-global'),
            'organizational_unit_id' => $career->id,
        ]);

        $this->assertNull($coAdministrator->organizational_unit_id);
        $this->assertTrue($coAdministrator->hasRole(RoleName::CO_ADMINISTRATOR->value));
    }

    public function test_postgresql_administrator_mutex_serializes_concurrent_invariant_checks(): void
    {
        $roleId = DB::table('roles')->where('name', RoleName::ADMINISTRATOR->value)->value('id');
        $first = $this->newPostgreSqlConnection();
        $second = $this->newPostgreSqlConnection();

        $first->beginTransaction();
        $second->beginTransaction();

        try {
            $statement = $first->prepare('SELECT id FROM roles WHERE id = ? FOR UPDATE');
            $statement->execute([$roleId]);

            $second->exec("SET LOCAL lock_timeout = '250ms'");

            try {
                $statement = $second->prepare('SELECT id FROM roles WHERE id = ? FOR UPDATE');
                $statement->execute([$roleId]);
                $this->fail('La segunda transacción obtuvo el mutex del Administrador simultáneamente.');
            } catch (PDOException $exception) {
                $this->assertSame('55P03', (string) $exception->getCode());
            }
        } finally {
            if ($second->inTransaction()) {
                $second->rollBack();
            }

            if ($first->inTransaction()) {
                $first->rollBack();
            }
        }
    }

    /**
     * @param  callable(): void  $callback
     */
    private function assertConstraintViolation(callable $callback, string $sqlState): void
    {
        DB::beginTransaction();

        try {
            $callback();
            $this->fail('PostgreSQL aceptó datos de usuario inválidos.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        } finally {
            if (DB::transactionLevel() > 1) {
                DB::rollBack();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertUser(array $overrides = []): void
    {
        DB::table('users')->insert([
            'public_id' => (string) Str::uuid(),
            'organizational_unit_id' => null,
            'name' => 'Usuario PostgreSQL',
            'email' => 'usuario-'.Str::uuid().'@test.local',
            'password' => Hash::make('Clave#PostgreSQL2026'),
            'status' => UserStatus::ACTIVE->value,
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RoleName $role, OrganizationalUnit $career, string $prefix): array
    {
        return [
            'name' => 'Usuario PostgreSQL',
            'email' => $prefix.'-'.Str::uuid().'@test.local',
            'password' => 'Clave#PostgreSQL2026',
            'status' => UserStatus::ACTIVE->value,
            'role' => $role->value,
            'organizational_unit_id' => $career->id,
        ];
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create([
            'organizational_unit_id' => null,
            'email' => 'actor-'.Str::uuid().'@test.local',
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

    private function newPostgreSqlConnection(): PDO
    {
        /** @var array{host: string, port: int|string, database: string, username: string, password: string} $config */
        $config = config('database.connections.pgsql');
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
}
