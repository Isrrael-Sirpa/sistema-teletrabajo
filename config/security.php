<?php

return [
    'headers' => [
        'content_security_policy' => env(
            'SECURITY_CONTENT_SECURITY_POLICY',
            "frame-ancestors 'none'; base-uri 'self'; object-src 'none'",
        ),
        'permissions_policy' => env(
            'SECURITY_PERMISSIONS_POLICY',
            'camera=(), geolocation=(), microphone=()',
        ),
    ],

    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS_ENABLED', false),
        'max_age' => max(0, (int) env('SECURITY_HSTS_MAX_AGE', 31_536_000)),
        'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', false),
    ],
];
