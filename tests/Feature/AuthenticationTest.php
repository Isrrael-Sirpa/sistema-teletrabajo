<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_the_public_home_page_and_contains_csrf_protection(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Iniciar sesión')
            ->assertSee('name="_token"', escape: false)
            ->assertSee('Facultad de Tecnología');
    }

    public function test_an_active_user_can_authenticate_and_last_login_is_recorded(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario.activo@example.test',
            'password' => 'contraseña-segura',
            'last_login_at' => null,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'contraseña-segura',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_incorrect_credentials_are_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'contraseña-correcta',
            'last_login_at' => null,
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'contraseña-incorrecta',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_an_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->inactive()->create([
            'password' => 'contraseña-segura',
            'last_login_at' => null,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'contraseña-segura',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_a_guest_is_redirected_from_private_routes_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_logout_ends_the_session_and_regenerates_the_csrf_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->withSession(['temporary_value' => 'must disappear']);
        $previousToken = session()->token();

        $response = $this->post(route('logout'));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionMissing('temporary_value');
        $this->assertGuest();
        $this->assertNotSame($previousToken, session()->token());
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $email = 'intentos@example.test';
        $key = Str::transliterate(Str::lower($email).'|127.0.0.1');

        RateLimiter::clear($key);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => $email,
                'password' => 'credencial-incorrecta',
            ])->assertSessionHasErrors('email');
        }

        $response = $this->post(route('login.store'), [
            'email' => $email,
            'password' => 'credencial-incorrecta',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Demasiados intentos',
            session('errors')->first('email'),
        );
        $this->assertSame(5, RateLimiter::attempts($key));

        RateLimiter::clear($key);
    }

    public function test_session_identifier_is_regenerated_after_authentication(): void
    {
        $user = User::factory()->create([
            'password' => 'contraseña-segura',
        ]);

        $this->withSession(['pre_authentication' => true]);
        $previousSessionId = session()->getId();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'contraseña-segura',
        ])->assertRedirect(route('dashboard'));

        $this->assertNotSame($previousSessionId, session()->getId());
    }

    public function test_an_authenticated_user_is_logged_out_if_the_account_becomes_inactive(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
