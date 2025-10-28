<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Jalankan command setiap menit
        $schedule->command('meetings:send-reminders')->everyMinute();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        // Pastikan baris ini ADA
        require base_path('routes/console.php');
    }
}
