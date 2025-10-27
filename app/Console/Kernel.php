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
