<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Inspiration is everywhere.');
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::command('ifotoset:purge-expired-trash')->daily();
\Illuminate\Support\Facades\Schedule::command('gallery:cleanup-expired-zips')->hourly();
