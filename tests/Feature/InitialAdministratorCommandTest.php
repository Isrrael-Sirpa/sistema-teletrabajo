<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InitialAdministratorCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
    }

    public function test_command_creates_the_first_administrator_with_secure_normalized_data(): void
    {
        $password = 'Inicial#Segura2026';

        $this->artisan('app:create-initial-admin')
            ->expectsQuestion('Nombre completo', '  Ana   Administradora  ')
            ->expectsQuestion('Correo electrónico', '  ANA.ADMIN@EXAMPLE.TEST  ')
            ->expectsQuestion('Contraseña', $password)
            ->expectsQuestion('Confirme la contraseña', $password)
            ->expectsOutputToContain('Administrador inicial creado correctamente para ana.admin@example.test.')
            ->doesntExpectOutputToContain($password)
            ->assertExitCode(Command::SUCCESS);

        $administrator = User::query()->where('email', 'ana.admin@example.test')->sole();

        $this->assertSame('Ana Administradora', $administrator->name);
        $this->assertSame(UserStatus::ACTIVE, $administrator->status);
        $this->assertNull($administrator->organizational_unit_id);
        $this->assertTrue($administrator->hasRole(RoleName::ADMINISTRATOR->value));
        $this->assertTrue(Hash::check($password, $administrator->password));
        $this->assertNotSame($password, $administrator->password);
    }

    public function test_command_refuses_to_run_again_when_any_administrator_exists(): void
    {
        $existing = User::factory()->inactive()->create();
        $existing->assignRole(RoleName::ADMINISTRATOR->value);

        $this->artisan('app:create-initial-admin')
            ->expectsOutputToContain('ya existe una cuenta Administrador')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(1, User::query()->role(RoleName::ADMINISTRATOR->value)->count());
    }

    public function test_command_rejects_a_weak_password_without_creating_a_user(): void
    {
        $this->artisan('app:create-initial-admin')
            ->expectsQuestion('Nombre completo', 'Administrador de Prueba')
            ->expectsQuestion('Correo electrónico', 'admin@example.test')
            ->expectsQuestion('Contraseña', 'debil')
            ->expectsQuestion('Confirme la contraseña', 'debil')
            ->expectsOutputToContain('al menos 12 caracteres')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('users', 0);
    }
}
