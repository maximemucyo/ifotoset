<?php

use Laravel\Sanctum\Sanctum;

return [
    // Photographer subdomains call the public gallery API from the browser and
    // need the session (PIN / invitation access), so they count as stateful.
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s,%s,*.%s',
        'localhost,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
        env('PUBLIC_ROOT_HOST', env('ROOT_DOMAIN', 'ifotoset.com')),
        env('PUBLIC_ROOT_HOST', env('ROOT_DOMAIN', 'ifotoset.com'))
    ))),

    'guard' => ['web'],
    'expiration' => null,
    'token_naming_pattern' => '/^[a-zA-Z0-9_:-]+$/',

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
