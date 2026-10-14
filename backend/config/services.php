<?php

return [
    'pawapay' => [
        'url' => env('PAWAPAY_API_URL', 'https://api.pawapay.io/v2'),
        'api_key' => env('PAWAPAY_JWT', env('PAWAPAY_API_KEY', '')),
        'currency' => env('PAWAPAY_CURRENCY', 'RWF'),
        'country' => env('PAWAPAY_COUNTRY', 'RWA'),
        'webhook_secret' => env('PAWAPAY_WEBHOOK_SECRET', ''),
        'relay_secret' => env('IFOTOSET_RELAY_SECRET'),
        // Reject callbacks that carry neither a valid relay secret nor a signature.
        'require_callback_auth' => (bool) env('PAWAPAY_REQUIRE_CALLBACK_AUTH', false),
    ],
    'google_analytics' => [
        'id' => env('GOOGLE_ANALYTICS_ID', 'G-HFSHFGP0LV'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],
    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],
];
