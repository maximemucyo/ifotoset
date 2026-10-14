<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'trash_retention_days' => env('TRASH_RETENTION_DAYS', 7),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],
        'b2' => [
            'driver' => 's3',
            'key' => env('BACKBLAZE_B2_KEY_ID'),
            'secret' => env('BACKBLAZE_B2_APPLICATION_KEY'),
            'region' => env('BACKBLAZE_B2_REGION', 'us-east-005'),
            'bucket' => env('BACKBLAZE_B2_BUCKET'),
            'endpoint' => env('BACKBLAZE_B2_ENDPOINT'), // e.g. https://s3.us-east-005.backblazeb2.com
            // Browser-facing endpoint for presigned URLs when it differs from the server-side one (local docker dev)
            'public_endpoint' => env('BACKBLAZE_B2_PUBLIC_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'cdn_domain' => env('CLOUDFLARE_CDN_DOMAIN', 'cdn.ifotoset.com'),
            'cdn_protocol' => env('CDN_PROTOCOL', 'https'),
            // Serve HLS playlists to players. Requires the edge worker that understands /_t/ path tokens (cdn_cloudflare_worker.js).
            'hls_streaming' => env('CDN_HLS_STREAMING', true),
            'cdn_secret' => env('CLOUDFLARE_CDN_SECRET', env('APP_KEY')),
            'use_cdn_downloads' => env('USE_CDN_DOWNLOADS', true),
            'http' => [
                'connect_timeout' => (int) env('B2_CONNECT_TIMEOUT', 30),
                'timeout' => (int) env('B2_TIMEOUT', 900),
            ],
        ],
    ],
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
