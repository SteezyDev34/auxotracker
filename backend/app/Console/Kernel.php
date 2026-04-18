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
        // ─── TENNIS ─────────────────────────────────────────────────────
        // Phase 1 : Cache des joueurs depuis l'API
        $schedule->command('tennis:cache-players --download-images')
            ->dailyAt('01:00')
            ->timezone('Europe/Paris')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/tennis-cache.log'));

        // Phase 2 : Import des joueurs depuis le cache vers la BDD
        $schedule->command('tennis:import-from-cache --download-images --force')
            ->dailyAt('01:40')
            ->timezone('Europe/Paris')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/tennis-import.log'));

        // ─── FOOTBALL ───────────────────────────────────────────────────
        // Phase 1 : Cache des tournois depuis l'API
        $schedule->command('football:import-from-schedule --import-teams')
            ->dailyAt('02:00')
            ->timezone('Europe/Paris')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/football-cache.log'));

        // Phase 2 : Import depuis le cache vers la BDD
        $schedule->command('football:import-from-cache --import-teams --download-logos')
            ->dailyAt('02:40')
            ->timezone('Europe/Paris')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/football-import.log'));

        // ─── BASKETBALL ─────────────────────────────────────────────────
        // Phase 1 : Cache des tournois depuis l'API
        $schedule->command('basketball:import-from-schedule --import-teams')
            ->dailyAt('03:00')
            ->timezone('Europe/Paris')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/basketball-cache.log'));

        // Phase 2 : Import depuis le cache vers la BDD
        $schedule->command('basketball:import-from-cache --import-teams --download-logos')
            ->dailyAt('03:40')
            ->timezone('Europe/Paris')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/basketball-import.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
