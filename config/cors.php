<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(
        explode(',', env('ALLOWED_ORIGINS', 'https://ifotoset.com,https://www.ifotoset.com'))
    ),

    // HTTPS photographer subdomains only; the CDN host never sends credentialed requests
    'allowed_origins_patterns' => array_filter([
        '#^https://(?!cdn\.)([a-z0-9-]+\.)?ifotoset\.com$#',
        env('APP_ENV') === 'local' ? '#^https?://([^/]+\.)?localhost(:[0-9]+)?$#' : null,
    ]),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
