<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Services\CountryFlagService;
use Illuminate\Console\Command;

class DownloadCountryFlags extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'country:download-flags 
                            {country_id? : ID du pays spécifique à télécharger}
                            {--force : Forcer le téléchargement même si le fichier existe}
                            {--all : Télécharger tous les drapeaux}';

    /**
     * Description de la commande console.
     *
     * @var string
     */
    protected $description = 'Télécharge les drapeaux des pays depuis l\'API Sofascore';

    /**
     * Service de téléchargement des drapeaux
     *
     * @var CountryFlagService
     */
    private CountryFlagService $flagService;

    /**
     * Constructeur
     */
    public function __construct(CountryFlagService $flagService)
    {
        parent::__construct();
        $this->flagService = $flagService;
    }

    /**
     * Exécute la commande console.
     */
    public function handle(): int
    {
        $countryId = $this->argument('country_id');
        $force = $this->option('force');
        $all = $this->option('all');

        if ($all) {
            return $this->downloadAllFlags($force);
        }

        if ($countryId) {
            return $this->downloadSingleFlag($countryId, $force);
        }

        $this->error('Veuillez spécifier un ID de pays ou utiliser l\'option --all');
        return Command::FAILURE;
    }

    /**
     * Télécharge le drapeau d'un pays spécifique
     *
     * @param int $countryId
     * @param bool $force
     * @return int
     */
    private function downloadSingleFlag(int $countryId, bool $force): int
    {
        $country = Country::find($countryId);
        
        if (!$country) {
            $this->error("Pays avec l'ID {$countryId} non trouvé.");
            return Command::FAILURE;
        }

        if (!$country->code) {
            $this->error("Le pays '{$country->name}' n'a pas de code pays défini.");
            return Command::FAILURE;
        }

        $this->info("Téléchargement du drapeau pour: {$country->name} (Code: {$country->code})");

        // Vérifier si le drapeau existe déjà
        if (!$force && $this->flagService->flagExists($country)) {
            $this->warn("Le drapeau pour '{$country->name}' existe déjà. Utilisez --force pour forcer le téléchargement.");
            return Command::SUCCESS;
        }

        if ($this->flagService->downloadFlag($country, $force)) {
            $this->info("✅ Drapeau téléchargé avec succès pour: {$country->name}");
            $this->line("   Fichier: country_flags/{$country->id}.png");
            return Command::SUCCESS;
        } else {
            $this->error("❌ Échec du téléchargement du drapeau pour: {$country->name}");
            return Command::FAILURE;
        }
    }

    /**
     * Télécharge tous les drapeaux
     *
     * @param bool $force
     * @return int
     */
    private function downloadAllFlags(bool $force): int
    {
        $this->info('Téléchargement de tous les drapeaux des pays...');
        
        $countries = Country::whereNotNull('code')->get();
        
        if ($countries->isEmpty()) {
            $this->warn('Aucun pays avec un code pays trouvé.');
            return Command::SUCCESS;
        }

        $this->info("Nombre de pays à traiter: {$countries->count()}");
        
        $progressBar = $this->output->createProgressBar($countries->count());
        $progressBar->start();

        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        foreach ($countries as $country) {
            // Vérifier si le drapeau existe déjà
            if (!$force && $this->flagService->flagExists($country)) {
                $results['skipped']++;
            } else {
                if ($this->flagService->downloadFlag($country, $force)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Afficher les résultats
        $this->info('📊 Résultats du téléchargement:');
        $this->line("   ✅ Succès: {$results['success']}");
        $this->line("   ❌ Échecs: {$results['failed']}");
        $this->line("   ⏭️  Ignorés: {$results['skipped']}");
        
        if ($results['failed'] > 0) {
            $this->warn('Certains téléchargements ont échoué. Consultez les logs pour plus de détails.');
        }

        return Command::SUCCESS;
    }
}