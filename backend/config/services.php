<?php

return [
    'pawapay' => [
        'url' => env('PAWAPAY_API_URL', 'https://api.pawapay.io'),
        'api_key' => env('PAWAPAY_API_KEY', 'test-api-key'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],
];
