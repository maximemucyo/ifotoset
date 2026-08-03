<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Inspiration is everywhere.');
})->purpose('Display an inspiring quote');
