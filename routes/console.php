<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Saved estimates are kept for 90 days. Needs a cron entry for `php artisan schedule:run` once hosted.
Schedule::command('estimates:prune')->daily()->timezone('Africa/Lusaka');
