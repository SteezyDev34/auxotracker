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
        // Importation des joueurs de tennis tous les jours à 1h40
        $schedule->command('tennis:import-players --download-images')
                 ->dailyAt('01:40')
                 ->timezone('Europe/Paris')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/tennis-import.log'));
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
