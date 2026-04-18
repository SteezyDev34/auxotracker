# `country:download-flags` — Télécharger les drapeaux des pays

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan country:download-flags` |
| **Classe** | `App\Console\Commands\DownloadCountryFlags` |
| **Fichier** | `app/Console/Commands/DownloadCountryFlags.php` |
| **Catégorie** | Assets / Drapeaux |
| **API externe** | Sofascore (via `CountryFlagService`) |

## Description

Télécharge les drapeaux des pays depuis l'API Sofascore. Peut traiter un pays spécifique ou l'ensemble des pays enregistrés en base qui possèdent un code pays.

---

## Signature

```bash
php artisan country:download-flags [country_id] [--force] [--all]
```

## Arguments

| Argument | Obligatoire | Description |
|---|---|---|
| `country_id` | Non | ID du pays spécifique à télécharger |

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer le téléchargement même si le fichier existe déjà |
| `--all` | `false` | Télécharger tous les drapeaux de tous les pays |

---

## Fonctionnement

### Mode pays unique (`country_id`)
1. Vérifie que le pays existe et possède un code pays
2. Vérifie si le drapeau existe déjà (sauf `--force`)
3. Télécharge via `CountryFlagService::downloadFlag()`
4. Stocke dans `country_flags/{country_id}.png`

### Mode tous les pays (`--all`)
1. Récupère tous les pays ayant un code (`whereNotNull('code')`)
2. Affiche une barre de progression
3. Télécharge chaque drapeau
4. Affiche les statistiques : succès, échecs, ignorés

### Sans argument ni option
Affiche un message d'erreur demandant de spécifier un ID ou `--all`.

---

## Dépendances

- `App\Services\CountryFlagService` — service de téléchargement des drapeaux
- `App\Models\Country`

---

## Exemples d'utilisation

```bash
# Télécharger le drapeau du pays ID 42
php artisan country:download-flags 42

# Forcer le re-téléchargement
php artisan country:download-flags 42 --force

# Télécharger tous les drapeaux
php artisan country:download-flags --all

# Tout re-télécharger
php artisan country:download-flags --all --force
```
