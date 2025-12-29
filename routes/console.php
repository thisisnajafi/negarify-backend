<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule: Fetch USD to Toman rate from TGJU.org every 5 minutes
Schedule::call(function () {
    try {
        app(\App\Services\TgjuScraperService::class)->fetchUsdRate();
        \Illuminate\Support\Facades\Log::info('Currency rate fetch completed');
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Currency rate fetch failed', [
            'error' => $e->getMessage(),
        ]);
    }
})->name('currency:fetch-rate')->everyFiveMinutes()->withoutOverlapping();

// Schedule: Reset feed view limits daily at midnight
Schedule::command('feed:reset-view-limits')
    ->name('feed:reset-view-limits')
    ->dailyAt('00:00')
    ->withoutOverlapping();

// Schedule: Aggregate models usage analytics daily
Schedule::command('analytics:aggregate-models-usage --period=daily')
    ->name('analytics:aggregate-models-usage')
    ->dailyAt('01:00')
    ->withoutOverlapping();

// Schedule: Cleanup expired OTPs hourly
Schedule::command('otp:cleanup --hours=1')
    ->name('otp:cleanup')
    ->hourly()
    ->withoutOverlapping();

// Schedule: Cleanup old generation jobs daily
Schedule::command('jobs:cleanup-old --days=90')
    ->name('jobs:cleanup-old')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Schedule: Record system health metrics every 5 minutes
Schedule::command('system:record-health')
    ->name('system:record-health')
    ->everyFiveMinutes()
    ->withoutOverlapping();
