<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Inspiration is everywhere.');
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::command('ifotoset:purge-expired-trash')->daily();
\Illuminate\Support\Facades\Schedule::command('gallery:cleanup-expired-zips')->hourly();

Artisan::command('b2:sync-cors', function () {
    $keyId = config('filesystems.disks.b2.key');
    $appKey = config('filesystems.disks.b2.secret');
    $bucketName = config('filesystems.disks.b2.bucket');

    $this->info("Authorizing with Backblaze B2...");
    $authRes = \Illuminate\Support\Facades\Http::withBasicAuth($keyId, $appKey)
        ->get('https://api.backblazeb2.com/b2api/v2/b2_authorize_account');

    if (!$authRes->ok()) {
        $this->error('Failed to authorize with B2: ' . $authRes->body());
        return 1;
    }

    $authData = $authRes->json();
    $apiUrl = $authData['apiUrl'];
    $authToken = $authData['authorizationToken'];
    $accountId = $authData['accountId'];

    $bucketRes = \Illuminate\Support\Facades\Http::withHeaders(['Authorization' => $authToken])
        ->post($apiUrl . '/b2api/v2/b2_list_buckets', [
            'accountId' => $accountId,
            'bucketName' => $bucketName,
        ]);

    $buckets = $bucketRes->json()['buckets'] ?? [];
    if (empty($buckets)) {
        $this->error("Bucket {$bucketName} not found.");
        return 1;
    }

    $bucketId = $buckets[0]['bucketId'];

    $newCorsRules = [
        [
            'corsRuleName' => 'allow-all-studio-uploads',
            'allowedOrigins' => [
                'https://*',
                'http://*',
                'http://localhost:8000',
                'http://127.0.0.1:8000',
                'http://localhost:3000',
                'http://127.0.0.1:3000',
                'https://ifotoset.com',
                'https://www.ifotoset.com',
            ],
            'allowedOperations' => [
                's3_head',
                's3_put',
                's3_post',
                's3_get',
                's3_delete',
                'b2_upload_file',
                'b2_upload_part',
            ],
            'allowedHeaders' => ['*'],
            'exposeHeaders' => ['etag', 'x-amz-checksum-sha256', 'x-amz-request-id', 'x-amz-id-2'],
            'maxAgeSeconds' => 86400,
        ]
    ];

    $updateRes = \Illuminate\Support\Facades\Http::withHeaders(['Authorization' => $authToken])
        ->post($apiUrl . '/b2api/v2/b2_update_bucket', [
            'accountId' => $accountId,
            'bucketId' => $bucketId,
            'corsRules' => $newCorsRules,
        ]);

    if ($updateRes->ok()) {
        $this->info("Successfully synced CORS rules for Backblaze B2 bucket: {$bucketName}");
        return 0;
    }

    $this->error('Failed to update bucket CORS: ' . $updateRes->body());
    return 1;
})->purpose('Sync Backblaze B2 bucket CORS rules for direct browser photo uploads');

