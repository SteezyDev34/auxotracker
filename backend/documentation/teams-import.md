# `teams:import` — Importer des équipes par plage d'IDs

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan teams:import` |
| **Classe** | `App\Console\Commands\ImportTeams` |
| **Fichier** | `app/Console/Commands/ImportTeams.php` |
| **Catégorie** | Import / Équipes |
| **API externe** | Sofascore (endpoint équipe individuelle) |

## Description

Importe les équipes depuis l'API Sofascore en parcourant une plage d'IDs Sofascore. Chaque ID est testé individuellement pour récupérer les données de l'équipe. Supporte le cache local, l'import depuis le cache, et le téléchargement des logos.

---

## Signature

```bash
php artisan teams:import [debut=14411] [fin=500000] [--force] [--delay=0] [--no-cache] [--from-cache] [--limit=] [--download-logos]
```

## Arguments

| Argument | Valeur par défaut | Description |
|---|---|---|
| `debut` | `14411` | ID Sofascore de début de la plage |
| `fin` | `500000` | ID Sofascore de fin de la plage |

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer l'importation même si l'équipe existe |
| `--delay=` | `0` | Délai en secondes entre chaque requête API |
| `--no-cache` | `false` | Ne pas écrire le cache |
| `--from-cache` | `false` | Importer depuis les fichiers de cache |
| `--limit=` | `null` | Limiter le nombre d'équipes (en mode from-cache) |
| `--download-logos` | `false` | Télécharger les logos après import |

---

## Fonctionnement détaillé

### Mode API (défaut)
1. Pour chaque ID dans la plage `[debut, fin]` :
   - Vérifie si l'équipe existe déjà en base (par `sofascore_id`)
   - Si existante et pas `--force` : skip
   - Récupère les données via l'API Sofascore
   - Extrait : `name`, `slug`, `shortName`, `uniqueTournament.id` (ou `primaryUniqueTournament.id`)
   - Vérifie que la ligue existe en base
   - Détection de doublons par nom et slug dans la même ligue
   - `create` ou `update` en base
   - Synchronise la table pivot `league_team`
2. Barre de progression avec message

### Mode cache (`--from-cache`)
- Parcourt les fichiers JSON dans le répertoire de cache
- Parse les données et crée/met à jour les équipes

---

## Détection de doublons

La commande vérifie :
- Doublon par **nom** dans la même ligue avec un `sofascore_id` différent
- Doublon par **slug** dans la même ligue avec un `sofascore_id` différent
- Les doublons sont logués mais n'empêchent pas l'import

---

## Statistiques suivies

| Métrique | Description |
|---|---|
| `teams_processed` | Équipes traitées |
| `teams_created` | Équipes créées |
| `teams_updated` | Équipes mises à jour |
| `teams_skipped` | Équipes ignorées |
| `duplicates_detected` | Doublons détectés |
| `logos_downloaded` | Logos téléchargés |
| `league_not_found` | Ligues non trouvées en base |
| `api_errors` | Erreurs API |

---

## Dépendances

- `App\Models\Team`, `League`
- `App\Services\TeamLogoService`

---

## Exemples d'utilisation

```bash
# Import standard (plage par défaut)
php artisan teams:import

# Import d'une plage spécifique
php artisan teams:import 1000 5000

# Import avec délai et téléchargement de logos
php artisan teams:import 1000 5000 --delay=1 --download-logos

# Import depuis le cache
php artisan teams:import --from-cache --limit=100

# Import forcé
php artisan teams:import 1000 5000 --force
```

---

## Notes

- La plage par défaut (14411 à 500000) est très large et peut prendre un temps considérable.
- Les équipes dont la ligue n'est pas trouvée en base sont silencieusement ignorées.
- La table pivot `league_team` est synchronisée automatiquement via `syncWithoutDetaching`.
