<?php

namespace App\Console\Commands;

use App\Models\FeedViewLimit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetFeedViewLimits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feed:reset-view-limits';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset daily feed view limits for all users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Log::info('Starting feed view limits reset');

        $resetCount = FeedViewLimit::query()
            ->where(function ($query) {
                // Reset if reset_at is before today's start
                $query->whereNull('reset_at')
                    ->orWhere('reset_at', '<', now()->startOfDay());
            })
            ->update([
                'views_remaining' => \DB::raw('daily_limit'),
                'reset_at' => now(),
            ]);

        $this->info("Reset view limits for {$resetCount} records");
        Log::info("Feed view limits reset completed. {$resetCount} records reset.");

        return Command::SUCCESS;
    }
}

