# `players:import-by-team` — Importer les joueurs par équipe

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan players:import-by-team` |
| **Classe** | `App\Console\Commands\ImportPlayersByTeam` |
| **Fichier** | `app/Console/Commands/ImportPlayersByTeam.php` |
| **Catégorie** | Import / Joueurs |
| **API externe** | Sofascore (standings / players par équipe) |

## Description

Importe les joueurs depuis l'API Sofascore par équipe. Peut traiter une équipe spécifique, toutes les équipes d'une ligue, ou l'ensemble des équipes en base.

---

## Signature

```bash
php artisan players:import-by-team [team_id] [--league-id=] [--force] [--delay=0] [--no-cache]
```

## Arguments

| Argument | Obligatoire | Description |
|---|---|---|
| `team_id` | Non | ID de l'équipe spécifique à traiter |

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--league-id=` | `null` | ID de la ligue pour importer toutes les équipes de cette ligue |
| `--force` | `false` | Forcer l'importation même si le joueur existe |
| `--delay=` | `0` | Délai en secondes entre chaque requête API |
| `--no-cache` | `false` | Désactiver le cache |

---

## Fonctionnement détaillé

1. **Sélection des équipes** :
   - `team_id` fourni → une seule équipe
   - `--league-id` fourni → toutes les équipes de la ligue ayant un `sofascore_id`
   - Aucun → toutes les équipes avec `sofascore_id`
2. **Pour chaque équipe** :
   - Récupère l'ID de saison via les featured events de la ligue
   - Récupère les joueurs via l'API Sofascore
   - Traite chaque joueur : création ou mise à jour dans la table `Player`
3. **Système de cache** : `storage/app/sofascore_cache/teams_players/{name}-{sofascore_id}/`

---

## Statistiques suivies

| Métrique | Description |
|---|---|
| `teams_processed` | Équipes traitées |
| `players_processed` / `created` / `updated` / `skipped` | Joueurs |
| `duplicates_detected` | Doublons |
| `season_not_found` | Saisons non trouvées |
| `api_errors` | Erreurs API |

---

## Dépendances

- `App\Models\Team`, `Player`, `League`

---

## Exemples d'utilisation

```bash
# Importer les joueurs d'une équipe
php artisan players:import-by-team 123

# Importer les joueurs d'une ligue entière
php artisan players:import-by-team --league-id=42

# Importer tous les joueurs
php artisan players:import-by-team

# Avec délai et force
php artisan players:import-by-team 123 --force --delay=1
```
