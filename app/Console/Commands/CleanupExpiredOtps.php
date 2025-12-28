<?php

namespace App\Console\Commands;

use App\Models\OtpVerification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredOtps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:cleanup {--hours=1 : Delete OTPs older than this many hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired OTP verification records older than specified hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoffTime = now()->subHours($hours);

        // Delete OTPs older than cutoff time
        $deletedCount = OtpVerification::where('created_at', '<', $cutoffTime)->delete();

        $this->info("Cleaned up {$deletedCount} expired OTP records older than {$hours} hour(s).");

        Log::info('OTP cleanup completed', [
            'deleted_count' => $deletedCount,
            'cutoff_time' => $cutoffTime->toISOString(),
            'hours' => $hours,
        ]);

        return Command::SUCCESS;
    }
}

