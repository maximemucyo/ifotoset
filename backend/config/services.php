<?php

return [
    'pawapay' => [
        'url' => env('PAWAPAY_API_URL', 'https://api.pawapay.io/v2'),
        'api_key' => env('PAWAPAY_JWT', env('PAWAPAY_API_KEY', '')),
        'currency' => env('PAWAPAY_CURRENCY', 'RWF'),
        'country' => env('PAWAPAY_COUNTRY', 'RWA'),
        'webhook_secret' => env('PAWAPAY_WEBHOOK_SECRET', ''),
        'relay_secret' => env('IFOTOSET_RELAY_SECRET', '7f9b8c6a5e4d3c2b1a0f9e8d7c6b5a4e'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],
];
