<?php

namespace Tests\PostgreSQL;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PostgreSqlAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_an_active_user_authenticates_against_postgresql_and_updates_last_login(): void
    {
        $user = User::factory()->create([
            'email' => 'prueba.postgresql.activo@example.test',
            'password' => 'contraseña-segura',
            'last_login_at' => null,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'contraseña-segura',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_an_inactive_user_is_rejected_by_postgresql_authentication(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'prueba.postgresql.inactivo@example.test',
            'password' => 'contraseña-segura',
            'last_login_at' => null,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'contraseña-segura',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }
}
