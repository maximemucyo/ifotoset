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
            'use_path_style_endpoint' => true,
            'cdn_domain' => env('CLOUDFLARE_CDN_DOMAIN', 'cdn.ifotoset.com'),
        ],
    ],
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
