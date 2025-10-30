<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Monitor Google Drive token every 6 hours to ensure it's always valid
        $schedule->command('googledrive:monitor-token')
                 ->everySixHours()
                 ->withoutOverlapping()
                 ->runInBackground();
        
        // Clean old logs weekly (keep 30 days)
        $schedule->command('logs:clean --days=30')
                 ->weekly()
                 ->sundays()
                 ->at('02:00')
                 ->runInBackground();
        
        // Cleanup temp files every 6 hours
        $schedule->command('googledrive:cleanup-temp --minutes=60')
                 ->everySixHours()
                 ->runInBackground();
        
        // Ensure queue worker is running every minute
        $schedule->exec(base_path('scripts/ensure-queue-worker.sh'))
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->runInBackground();
        
        // Auto-sync files from Google Drive every 5 minutes
        // This ensures Server B automatically gets updated files from Server A via shared DB
        $schedule->command('files:sync-from-drive --limit=50')
                 ->everyFiveMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
