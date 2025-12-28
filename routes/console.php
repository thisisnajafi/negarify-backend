<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule: Cleanup expired OTPs hourly
Schedule::command('otp:cleanup --hours=1')->hourly();

// Schedule: Fetch USD to Toman rate from TGJU.org every 5 minutes
Schedule::call(function () {
    app(\App\Services\TgjuScraperService::class)->fetchUsdRate();
})->everyFiveMinutes();

// Schedule: Reset feed view limits daily at midnight
Schedule::command('feed:reset-view-limits')->daily();
