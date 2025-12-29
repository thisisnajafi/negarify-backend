<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Services\Otp\MelipayamakOtpSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind OtpSender interface to MelipayamakOtpSender implementation
        $this->app->singleton(OtpSender::class, MelipayamakOtpSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
