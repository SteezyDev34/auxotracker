# `basketball:import-from-schedule` — Collecter le basketball dans le cache (Phase 1)

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan basketball:import-from-schedule` |
| **Classe** | `App\Console\Commands\ImportBasketballFromSchedule` |
| **Fichier** | `app/Console/Commands/ImportBasketballFromSchedule.php` |
| **Catégorie** | Import / Basketball |
| **Phase** | **Phase 1** — API → Cache (aucune écriture en BDD) |
| **API externe** | Sofascore (`/api/v1/sport/basketball/scheduled-tournaments/{date}/page/{page}`) |

## Description

Collecte les données de basketball depuis les tournois programmés (scheduled-tournaments) de l'API Sofascore et les stocke en cache local (fichiers JSON). C'est la **Phase 1** du processus d'import en 2 phases. **Aucune écriture en base de données n'est effectuée.**

Pour importer les données en BDD, utiliser ensuite `basketball:import-from-cache` (Phase 2).

---

## Architecture 2 phases

```
Phase 1 : basketball:import-from-schedule  →  API Sofascore → fichiers cache (JSON)
Phase 2 : basketball:import-from-cache     →  fichiers cache (JSON) → BDD
```

---

## Signature

```bash
php artisan basketball:import-from-schedule [date] [--no-cache] [--import-teams] [--delay=1] [--max-pages=50]
```

## Arguments

| Argument | Obligatoire | Description |
|---|---|---|
| `date` | Non | Date au format `YYYY-MM-DD` (défaut : aujourd'hui) |

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--no-cache` | `false` | Désactiver le cache local |
| `--import-teams` | `false` | Pré-charger aussi les données d'équipes (saisons + standings) dans le cache |
| `--delay=` | `1` | Délai en secondes entre chaque requête API |
| `--max-pages=` | `50` | Nombre maximum de pages |

---

## Fonctionnement détaillé

1. **Validation** : format de date `YYYY-MM-DD`
2. **Fetch paginé** : récupère toutes les pages de scheduled-tournaments depuis l'API
3. **Déduplication** : par `uniqueTournament.id`
4. **Stockage en cache** : écrit chaque page dans `page_{n}.json`
5. Si `--import-teams` : pré-charge les données de saisons (`featured_events_{id}.json`) et standings (`standings_{id}_{seasonId}.json`) dans le cache
6. **Aucune écriture en BDD** — affiche un hint pour lancer la Phase 2

---

## Système de cache

- **Répertoire** : `storage/app/sofascore_cache/basketball_schedule/{date}/`
- **Fichiers** : `page_{n}.json`, `featured_events_{id}.json`, `standings_{id}_{seasonId}.json`
- **Cache négatif (tombstone)** : les requêtes échouées sont marquées pour éviter de re-tenter (24h TTL)
- Nettoyage automatique des caches expirés (> 7 jours)

---

## Statistiques suivies

| Métrique | Description |
|---|---|
| `pages_fetched` | Nombre de pages API récupérées |
| `leagues_discovered` | Ligues uniques trouvées |
| `seasons_cached` | Saisons mises en cache |
| `standings_cached` | Standings mis en cache |
| `api_errors` | Erreurs API |

---

## Dépendances

Aucun modèle Eloquent (pas d'écriture BDD). Utilise uniquement `Http`, `Log`, et le système de fichiers pour le cache.

---

## Exemples d'utilisation

```bash
# Collecter les données pour aujourd'hui
php artisan basketball:import-from-schedule

# Collecter avec pré-chargement des équipes
php artisan basketball:import-from-schedule --import-teams

# Collecter pour une date spécifique
php artisan basketball:import-from-schedule 2025-06-01 --import-teams --delay=2

# Puis importer en BDD (Phase 2)
php artisan basketball:import-from-cache --import-teams --download-logos
```

---

## Voir aussi

- [`basketball:import-from-cache`](basketball-import-from-cache.md) — Phase 2 (cache → BDD)
- [`football:import-from-schedule`](football-import-from-schedule.md) — Équivalent football
- [`tennis:cache-players`](tennis-cache-players.md) — Même pattern pour le tennis
