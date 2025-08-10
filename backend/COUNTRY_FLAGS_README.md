# Téléchargement des Drapeaux des Pays

Cette fonctionnalité permet de télécharger automatiquement les drapeaux des pays depuis l'API Sofascore.

## Structure

### Modèle Country
- **Fichier**: `app/Models/Country.php`
- **Colonnes**: `id`, `name`, `code`, `slug`, `img`, `created_at`, `updated_at`
- **Relation**: Un pays peut avoir plusieurs ligues

### Service CountryFlagService
- **Fichier**: `app/Services/CountryFlagService.php`
- **Fonctions principales**:
  - `downloadFlag(Country $country, bool $force = false)`: Télécharge le drapeau d'un pays
  - `downloadAllFlags(bool $force = false)`: Télécharge tous les drapeaux
  - `flagExists(Country $country)`: Vérifie si un drapeau existe
  - `getFlagUrl(Country $country)`: Obtient l'URL publique du drapeau

### Commandes Artisan

#### 1. Téléchargement des drapeaux
```bash
# Télécharger le drapeau d'un pays spécifique
php artisan country:download-flags {country_id}

# Forcer le téléchargement même si le fichier existe
php artisan country:download-flags {country_id} --force

# Télécharger tous les drapeaux
php artisan country:download-flags --all

# Forcer le téléchargement de tous les drapeaux
php artisan country:download-flags --all --force
```

#### 2. Test de la fonctionnalité
```bash
# Tester la fonctionnalité de téléchargement
php artisan country:test-flags
```

## API Sofascore

### URL de base
```
https://img.sofascore.com/api/v1/country/{CODE}/flag
```

### Exemple
- France (FR): `https://img.sofascore.com/api/v1/country/FR/flag`
- Espagne (ES): `https://img.sofascore.com/api/v1/country/ES/flag`
- Allemagne (DE): `https://img.sofascore.com/api/v1/country/DE/flag`

## Stockage

### Répertoire
```
storage/app/public/country_flags/
```

### Format des fichiers
```
{country_id}.png
```

### Exemples
- `221.png` (Chine)
- `222.png` (Colombie)
- `193.png` (Albanie)

## Configuration

### Headers HTTP
Pour éviter les erreurs 403, les headers suivants sont utilisés :
- `User-Agent`: Mozilla/5.0 (navigateur moderne)
- `Referer`: https://www.sofascore.com/
- `Origin`: https://www.sofascore.com
- `Accept`: image/webp,image/apng,image/*,*/*;q=0.8

### Timeout
- **Délai d'attente**: 30 secondes par requête

## Utilisation

### Téléchargement d'un drapeau spécifique
```bash
php artisan country:download-flags 221 --force
```
**Résultat**:
```
Téléchargement du drapeau pour: China (Code: CN)
✅ Drapeau téléchargé avec succès pour: China
   Fichier: country_flags/221.png
```

### Téléchargement de tous les drapeaux
```bash
php artisan country:download-flags --all
```
**Résultat**:
```
Téléchargement de tous les drapeaux des pays...
Nombre de pays à traiter: 242
 242/242 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

📊 Résultats du téléchargement:
   ✅ Succès: 240
   ❌ Échecs: 0
   ⏭️  Ignorés: 2
```

### Test de la fonctionnalité
```bash
php artisan country:test-flags
```
**Résultat**:
```
🏁 Test de la fonctionnalité de téléchargement des drapeaux des pays

📊 Statistiques générales:
   Total des pays: 321
   Pays avec code: 242
   Pays avec drapeaux: 242

🧪 Test de pays spécifiques:
   ✅ Albania (Code: AL)
      URL: http://api.auxotracker.lan/storage/country_flags/193.png
   ✅ Algeria (Code: DZ)
      URL: http://api.auxotracker.lan/storage/country_flags/194.png

📁 Répertoire de stockage: /path/to/storage/app/public/country_flags
   Nombre de drapeaux stockés: 242

✅ Test terminé avec succès!
```

## Logs

Tous les téléchargements sont enregistrés dans les logs Laravel :
- **Succès**: `Log::info()`
- **Erreurs**: `Log::error()`
- **Avertissements**: `Log::warning()`

## Gestion des erreurs

### Pays sans code
Si un pays n'a pas de code défini, le téléchargement est ignoré avec un avertissement.

### Erreurs HTTP
Les erreurs HTTP (403, 404, etc.) sont capturées et enregistrées dans les logs.

### Exceptions
Toutes les exceptions sont capturées et enregistrées pour éviter l'arrêt du processus.

## Intégration

### Dans le code
```php
use App\Services\CountryFlagService;
use App\Models\Country;

$flagService = new CountryFlagService();
$country = Country::find(221);

// Télécharger le drapeau
$success = $flagService->downloadFlag($country);

// Vérifier si le drapeau existe
$exists = $flagService->flagExists($country);

// Obtenir l'URL du drapeau
$url = $flagService->getFlagUrl($country);
```

### Dans les vues
```php
@if($country->img)
    <img src="{{ Storage::url($country->img) }}" alt="Drapeau {{ $country->name }}">
@endif
```

## Maintenance

### Mise à jour des drapeaux
Pour mettre à jour tous les drapeaux existants :
```bash
php artisan country:download-flags --all --force
```

### Vérification de l'intégrité
```bash
php artisan country:test-flags
```

Cette fonctionnalité est maintenant prête à être utilisée et intégrée dans l'application !