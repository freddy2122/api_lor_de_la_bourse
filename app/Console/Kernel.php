<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\RefreshBrvmData;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Rafraîchit les données BRVM toutes les 2 minutes (adaptable)
        $schedule->call(function () {
            if (config('services.market.source') === 'scraper') {
                RefreshBrvmData::dispatch();
            }
        })->everyTwoMinutes()->name('refresh-brvm-data');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
