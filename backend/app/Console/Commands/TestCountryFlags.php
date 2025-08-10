<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Services\CountryFlagService;
use Illuminate\Console\Command;

class TestCountryFlags extends Command
{
    /**
     * Le nom et la signature de la commande console.
     *
     * @var string
     */
    protected $signature = 'country:test-flags';

    /**
     * Description de la commande console.
     *
     * @var string
     */
    protected $description = 'Teste la fonctionnalité de téléchargement des drapeaux des pays';

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
        $this->info('🏁 Test de la fonctionnalité de téléchargement des drapeaux des pays');
        $this->newLine();

        // Statistiques générales
        $totalCountries = Country::count();
        $countriesWithCode = Country::whereNotNull('code')->count();
        $countriesWithFlags = Country::whereNotNull('img')->count();

        $this->info('📊 Statistiques générales:');
        $this->line("   Total des pays: {$totalCountries}");
        $this->line("   Pays avec code: {$countriesWithCode}");
        $this->line("   Pays avec drapeaux: {$countriesWithFlags}");
        $this->newLine();

        // Test de quelques pays spécifiques
        $testCountries = Country::whereNotNull('code')->take(5)->get();
        
        if ($testCountries->isEmpty()) {
            $this->warn('Aucun pays avec code trouvé pour les tests.');
            return Command::SUCCESS;
        }

        $this->info('🧪 Test de pays spécifiques:');
        
        foreach ($testCountries as $country) {
            $flagExists = $this->flagService->flagExists($country);
            $flagUrl = $this->flagService->getFlagUrl($country);
            
            $status = $flagExists ? '✅' : '❌';
            $this->line("   {$status} {$country->name} (Code: {$country->code})");
            
            if ($flagExists && $flagUrl) {
                $this->line("      URL: {$flagUrl}");
            }
        }
        
        $this->newLine();

        // Vérification du répertoire de stockage
        $flagsDirectory = storage_path('app/public/country_flags');
        $flagsCount = 0;
        
        if (is_dir($flagsDirectory)) {
            $flagsCount = count(glob($flagsDirectory . '/*.png'));
            $this->info("📁 Répertoire de stockage: {$flagsDirectory}");
            $this->line("   Nombre de drapeaux stockés: {$flagsCount}");
        } else {
            $this->warn("Répertoire de drapeaux non trouvé: {$flagsDirectory}");
        }

        $this->newLine();
        $this->info('✅ Test terminé avec succès!');
        
        return Command::SUCCESS;
    }
}