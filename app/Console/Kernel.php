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
        $schedule->command('school:cleanup-attendance-photos')
            ->daily()
            ->at('02:00');

        $schedule->command('holidays:remind')
            ->dailyAt(config('fcm.holiday_reminder_time', '17:00'))
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping();
        // ... jadwal command lain yang sudah ada tetap di sini
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
