<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProductionSecurityTest extends TestCase
{
    public function test_public_responses_include_compatible_security_headers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; object-src 'none'")
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_emitted_only_for_secure_requests_when_explicitly_enabled(): void
    {
        config([
            'security.hsts.enabled' => true,
            'security.hsts.max_age' => 31_536_000,
            'security.hsts.include_subdomains' => false,
        ]);

        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    #[DataProvider('friendlyErrorStatuses')]
    public function test_expected_http_errors_have_simple_institutional_pages(int $status, string $message): void
    {
        config(['app.debug' => false]);
        Route::get('/_fase23/error-'.$status, fn () => abort($status));

        $this->get('/_fase23/error-'.$status)
            ->assertStatus($status)
            ->assertSee((string) $status)
            ->assertSee($message)
            ->assertDontSee('vendor/laravel')
            ->assertDontSee('Stack trace');
    }

    public function test_production_500_page_does_not_expose_exception_or_internal_data(): void
    {
        config(['app.debug' => false]);
        Route::get('/_fase23/server-error', function (): never {
            throw new RuntimeException('SQLSTATE secreto /srv/app/.env password=interno');
        });

        $this->get('/_fase23/server-error')
            ->assertStatus(500)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('No pudimos completar la operación')
            ->assertDontSee('SQLSTATE secreto')
            ->assertDontSee('/srv/app/.env')
            ->assertDontSee('password=interno')
            ->assertDontSee('RuntimeException');
    }

    public function test_health_check_is_generic_and_receives_security_headers(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDontSee('Laravel')
            ->assertDontSee('PHP')
            ->assertDontSee('PostgreSQL')
            ->assertDontSee('APP_KEY')
            ->assertDontSee('DB_PASSWORD');
    }

    public function test_production_example_has_safe_session_database_and_log_defaults_without_secrets(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('APP_ENV=production', $example);
        $this->assertStringContainsString('APP_DEBUG=false', $example);
        $this->assertStringContainsString('DB_CONNECTION=pgsql', $example);
        $this->assertStringContainsString('DB_USERNAME=sistema_teletrabajo_app', $example);
        $this->assertStringContainsString('SESSION_DRIVER=database', $example);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $example);
        $this->assertStringContainsString('SESSION_HTTP_ONLY=true', $example);
        $this->assertStringContainsString('SESSION_SAME_SITE=lax', $example);
        $this->assertStringContainsString('LOG_STACK=daily', $example);
        $this->assertStringNotContainsString('DB_USERNAME=postgres', $example);
        $this->assertDoesNotMatchRegularExpression('/^APP_KEY=.+$/m', $example);
        $this->assertNotContains('*', config('trustedproxy.proxies'));
    }

    public function test_document_storage_remains_outside_public_and_sensitive_downloads_are_limited(): void
    {
        $documentRoot = str_replace('\\', '/', (string) config('filesystems.disks.documents.root'));
        $publicRoot = rtrim(str_replace('\\', '/', public_path()), '/').'/';

        $this->assertFalse(str_starts_with($documentRoot.'/', $publicRoot));
        $this->assertFalse(config('filesystems.disks.documents.serve'));
        $this->assertNotContains(config('filesystems.disks.documents.root'), config('filesystems.links'));
        $this->assertContains(
            'throttle:120,1',
            Route::getRoutes()->getByName('documents.files.download')->gatherMiddleware(),
        );
        $this->assertContains(
            'throttle:10,1',
            Route::getRoutes()->getByName('reports.documents.csv')->gatherMiddleware(),
        );
    }

    /** @return array<string, array{int, string}> */
    public static function friendlyErrorStatuses(): array
    {
        return [
            'forbidden' => [403, 'Acceso no autorizado'],
            'not found' => [404, 'Página no encontrada'],
            'expired' => [419, 'La sesión de la página venció'],
            'rate limited' => [429, 'Demasiadas solicitudes'],
            'maintenance' => [503, 'Servicio temporalmente no disponible'],
        ];
    }
}
