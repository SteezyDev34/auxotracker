# `teams:import-by-league` — Importer les équipes par ligue

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan teams:import-by-league` |
| **Classe** | `App\Console\Commands\ImportTeamsByLeague` |
| **Fichier** | `app/Console/Commands/ImportTeamsByLeague.php` |
| **Catégorie** | Import / Équipes |
| **API externe** | Sofascore (standings / featured events) |

## Description

Importe les équipes depuis l'API Sofascore en passant par les standings (classements) d'une ligue. Peut traiter une ligue spécifique ou toutes les ligues en base (hors tennis, sport_id = 2).

---

## Signature

```bash
php artisan teams:import-by-league [league_id] [--force] [--delay=0] [--no-cache] [--from-cache] [--limit=] [--download-logos]
```

## Arguments

| Argument | Obligatoire | Description |
|---|---|---|
| `league_id` | Non | ID de la ligue à traiter. Sans argument, traite toutes les ligues |

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer l'importation même si l'équipe existe |
| `--delay=` | `0` | Délai en secondes entre chaque requête API |
| `--no-cache` | `false` | Désactiver le cache |
| `--from-cache` | `false` | Importer depuis les fichiers de cache |
| `--limit=` | `null` | Limiter le nombre d'équipes (mode from-cache) |
| `--download-logos` | `false` | Télécharger les logos après import |

---

## Fonctionnement détaillé

### Processus par ligue
1. **Exclusion du tennis** : les ligues de sport_id = 2 sont automatiquement ignorées
2. **Récupération de la saison** : appel aux featured events de la ligue pour obtenir le `seasonId`
3. **Récupération des standings** : avec le `seasonId`, récupère les classements contenant les équipes
4. **Traitement des équipes** : pour chaque équipe dans les standings :
   - Vérifie l'existence par `sofascore_id`
   - Détection de doublons par nom/slug dans la même ligue
   - Crée ou met à jour l'équipe
   - Synchronise la table pivot `league_team`
5. **Téléchargement de logos** (si `--download-logos`)

### Mode cache
- **Répertoire** : `storage/app/sofascore_cache/leagues_teams/{name}-{sofascore_id}/`
- Parse les fichiers `standings` pour extraire les équipes

---

## Statistiques suivies

| Métrique | Description |
|---|---|
| `leagues_processed` | Ligues traitées |
| `teams_processed` / `created` / `updated` / `skipped` | Équipes |
| `duplicates_detected` | Doublons |
| `logos_downloaded` | Logos |
| `season_not_found` | Saisons non trouvées |
| `api_errors` | Erreurs API |

---

## Dépendances

- `App\Models\League`, `Team`
- `App\Services\TeamLogoService`

---

## Exemples d'utilisation

```bash
# Importer les équipes d'une ligue spécifique
php artisan teams:import-by-league 42

# Importer toutes les ligues
php artisan teams:import-by-league

# Depuis le cache
php artisan teams:import-by-league 42 --from-cache

# Avec logos et délai
php artisan teams:import-by-league 42 --download-logos --delay=1

# Import forcé
php artisan teams:import-by-league 42 --force
```
