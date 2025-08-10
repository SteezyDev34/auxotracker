<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CountryFlagService
{
    /**
     * URL de base pour l'API des drapeaux Sofascore
     */
    private const BASE_URL = 'https://img.sofascore.com/api/v1/country';

    /**
     * Télécharge le drapeau d'un pays depuis l'API Sofascore
     *
     * @param Country $country
     * @param bool $force
     * @return bool
     */
    public function downloadFlag(Country $country, bool $force = false): bool
    {
        if (!$country->code) {
            Log::warning("Pays {$country->name} (ID: {$country->id}) n'a pas de code pays");
            return false;
        }

        $filename = "{$country->id}.png";
        $filePath = "country_flags/{$filename}";

        // Vérifier si le fichier existe déjà
        if (!$force && Storage::disk('public')->exists($filePath)) {
            Log::info("Drapeau pour {$country->name} existe déjà: {$filePath}");
            return true;
        }

        try {
            $url = self::BASE_URL . "/{$country->code}/flag";
            
            Log::info("Téléchargement du drapeau depuis: {$url}");

            // Configuration des headers pour éviter les erreurs 403
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'image/webp,image/apng,image/*,*/*;q=0.8',
                'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Referer' => 'https://www.sofascore.com/',
                'Origin' => 'https://www.sofascore.com',
                'Sec-Fetch-Dest' => 'image',
                'Sec-Fetch-Mode' => 'no-cors',
                'Sec-Fetch-Site' => 'same-site',
            ])->timeout(30)->get($url);

            if ($response->successful()) {
                // Créer le répertoire s'il n'existe pas
                $directory = dirname($filePath);
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                }

                // Sauvegarder le fichier
                Storage::disk('public')->put($filePath, $response->body());
                
                // Mettre à jour le champ img du pays
                $country->update(['img' => $filePath]);
                
                Log::info("Drapeau téléchargé avec succès: {$filePath}");
                return true;
            } else {
                Log::error("Erreur lors du téléchargement du drapeau pour {$country->name}: HTTP {$response->status()}");
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Exception lors du téléchargement du drapeau pour {$country->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Télécharge les drapeaux pour tous les pays
     *
     * @param bool $force
     * @return array
     */
    public function downloadAllFlags(bool $force = false): array
    {
        $countries = Country::whereNotNull('code')->get();
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        foreach ($countries as $country) {
            if ($this->downloadFlag($country, $force)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Vérifie si le drapeau d'un pays existe
     *
     * @param Country $country
     * @return bool
     */
    public function flagExists(Country $country): bool
    {
        $filename = "{$country->id}.png";
        $filePath = "country_flags/{$filename}";
        
        return Storage::disk('public')->exists($filePath);
    }

    /**
     * Obtient l'URL publique du drapeau d'un pays
     *
     * @param Country $country
     * @return string|null
     */
    public function getFlagUrl(Country $country): ?string
    {
        if ($this->flagExists($country)) {
            $filename = "{$country->id}.png";
            $filePath = "country_flags/{$filename}";
            return asset('storage/' . $filePath);
        }
        
        return null;
    }
}