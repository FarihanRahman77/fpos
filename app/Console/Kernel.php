<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Runs when Windows Task Scheduler calls `php artisan schedule:run` every minute (see CLAUDE.md).
        $schedule->command('backup:run')->dailyAt(\App\Http\Controllers\Admin\BackupController::scheduleTime())->withoutOverlapping();
        $schedule->call(fn () => \App\Http\Controllers\Admin\ActivityLogController::prune())->dailyAt('03:30');
        $schedule->call(fn () => \App\Http\Controllers\Admin\AttendanceDeviceController::syncAll())->everyThirtyMinutes()->name('attendance-device-sync')->withoutOverlapping(60);   // lock expires after 1 h so a crashed run cannot block syncing for a day
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
