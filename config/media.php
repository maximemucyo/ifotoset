<?php

return [
    // Queue that ProcessVideoJob runs on (served by the ifotoset-video-queue worker)
    'queue' => env('MEDIA_QUEUE', 'media'),

    // ffmpeg -threads for transcoding and HLS packaging
    'ffmpeg_threads' => (int) env('MEDIA_FFMPEG_THREADS', 2),
];
