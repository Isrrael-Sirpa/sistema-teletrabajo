<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        return $this->apply($request, $next($request));
    }

    public function apply(Request $request, Response $response): Response
    {

        $privateInline = $request->routeIs('documents.files.download')
            && $request->attributes->get('authorized_private_file_inline') === true
            && $response instanceof StreamedResponse
            && $response->isSuccessful();
        $csp = (string) config('security.headers.content_security_policy');

        if ($privateInline) {
            $directives = array_filter(explode(';', $csp), fn (string $directive): bool => trim($directive) !== '' && ! preg_match('/^frame-ancestors(?:\s|$)/i', trim($directive)));
            $csp = "frame-ancestors 'self'; ".implode('; ', array_map('trim', $directives));
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', $privateInline ? 'SAMEORIGIN' : 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            (string) config('security.headers.permissions_policy'),
        );
        $response->headers->set(
            'Content-Security-Policy',
            $csp,
        );

        if ($request->isSecure() && config('security.hsts.enabled')) {
            $value = 'max-age='.(int) config('security.hsts.max_age');

            if (config('security.hsts.include_subdomains')) {
                $value .= '; includeSubDomains';
            }

            $response->headers->set('Strict-Transport-Security', $value);
        }

        return $response;
    }
}
