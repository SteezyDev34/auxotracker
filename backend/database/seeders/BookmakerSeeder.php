<?php

namespace Database\Seeders;

use App\Models\Bookmaker;
use Illuminate\Database\Seeder;

class BookmakerSeeder extends Seeder
{
    /**
     * Exécute le seeder pour créer des bookmakers par défaut.
     */
    public function run(): void
    {
        // Liste des bookmakers populaires avec leurs images
        $bookmakers = [
            ['bookmaker_name' => 'Winamax', 'bookmaker_img' => 'winamax.png'],
            ['bookmaker_name' => 'Betclic', 'bookmaker_img' => 'betclic.png'],
            ['bookmaker_name' => 'Unibet', 'bookmaker_img' => 'unibet.png'],
            ['bookmaker_name' => 'Bwin', 'bookmaker_img' => 'bwin.png'],
            ['bookmaker_name' => 'PMU', 'bookmaker_img' => 'pmu.png'],
            ['bookmaker_name' => 'Parions Sport', 'bookmaker_img' => 'parions-sport.png'],
            ['bookmaker_name' => 'Zebet', 'bookmaker_img' => 'zebet.png'],
            ['bookmaker_name' => 'Bet365', 'bookmaker_img' => 'bet365.png'],
            ['bookmaker_name' => 'NetBet', 'bookmaker_img' => 'netbet.png'],
            ['bookmaker_name' => 'Vbet', 'bookmaker_img' => 'vbet.png'],
        ];

        // Création des bookmakers s'ils n'existent pas déjà
        foreach ($bookmakers as $bookmakerData) {
            Bookmaker::firstOrCreate(
                ['bookmaker_name' => $bookmakerData['bookmaker_name']],
                ['bookmaker_img' => $bookmakerData['bookmaker_img']]
            );
        }
    }
}