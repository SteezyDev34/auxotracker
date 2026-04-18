# `football:import-from-cache` — Importer le football depuis le cache (Phase 2)

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan football:import-from-cache` |
| **Classe** | `App\Console\Commands\ImportFootballFromCache` |
| **Fichier** | `app/Console/Commands/ImportFootballFromCache.php` |
| **Catégorie** | Import / Football |
| **Phase** | **Phase 2** — Cache → BDD (aucun appel API) |

## Description

Importe les ligues et équipes de football depuis les fichiers de cache local vers la base de données. C'est la **Phase 2** du processus d'import en 2 phases. La Phase 1 (`football:import-from-schedule`) collecte les données depuis l'API Sofascore et les stocke en cache ; cette commande lit le cache et persiste les données en BDD.

**Aucun appel API n'est effectué** par cette commande.

---

## Architecture 2 phases

```
Phase 1 : football:import-from-schedule  →  API Sofascore → fichiers cache (JSON)
Phase 2 : football:import-from-cache     →  fichiers cache (JSON) → BDD (Country, League, Team)
```

---

## Signature

```bash
php artisan football:import-from-cache [date] [--force] [--import-teams] [--download-logos]
```

## Arguments

| Argument | Obligatoire | Description |
|---|---|---|
| `date` | Non | Date au format `YYYY-MM-DD` (défaut : aujourd'hui) |

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer la mise à jour même si les données existent |
| `--import-teams` | `false` | Importer aussi les équipes depuis les standings en cache |
| `--download-logos` | `false` | Télécharger les logos des ligues et des équipes |

---

## Fonctionnement détaillé

1. **Chargement du cache** : lit les fichiers `page_{n}.json` depuis `storage/app/sofascore_cache/football_schedule/{date}/`
2. **Récupération du sport** : cherche le sport Football en base (sofascore_id=1)
3. **Déduplication** : par `uniqueTournament.id`
4. **Traitement** : pour chaque ligue unique :
   - Crée/met à jour le pays (`Country`)
   - Crée/met à jour la ligue (`League`)
   - Si `--import-teams` : lit les standings depuis le cache et importe les équipes
   - Si `--download-logos` : télécharge les logos via `LeagueLogoService` / `TeamLogoService`
5. **Statistiques détaillées** en fin d'exécution

---

## Fichiers de cache lus

```
storage/app/sofascore_cache/football_schedule/{date}/
├── page_1.json                           # Tournois programmés (page 1)
├── page_2.json                           # Tournois programmés (page 2)
├── ...
├── featured_events_{leagueId}.json       # Saison en cours (pour obtenir seasonId)
└── standings_{leagueId}_{seasonId}.json  # Classement avec équipes
```

---

## Statistiques suivies

| Métrique | Description |
|---|---|
| `pages_loaded` | Nombre de pages de cache chargées |
| `tournaments_processed` | Nombre de tournois traités |
| `countries_created` | Pays créés |
| `leagues_created` / `updated` / `skipped` | Ligues créées / mises à jour / ignorées |
| `teams_created` / `updated` / `skipped` / `processed` | Équipes traitées |
| `duplicates_detected` | Doublons détectés |
| `logos_downloaded` | Logos téléchargés |
| `season_not_found` | Saisons non trouvées en cache |
| `api_errors` | Erreurs (normalement 0, pas d'appels API) |
| `errors` | Autres erreurs |

---

## Dépendances

- `App\Models\Country`, `League`, `Sport`, `Team`
- `App\Services\LeagueLogoService`, `TeamLogoService`

---

## Exemples d'utilisation

```bash
# Workflow complet en 2 phases :
# Phase 1 : collecter les données
php artisan football:import-from-schedule --import-teams

# Phase 2 : importer en BDD
php artisan football:import-from-cache --import-teams --download-logos

# Import forcé pour une date spécifique
php artisan football:import-from-cache 2025-06-01 --force --import-teams --download-logos
```

---

## Voir aussi

- [`football:import-from-schedule`](football-import-from-schedule.md) — Phase 1 (API → cache)
- [`basketball:import-from-cache`](basketball-import-from-cache.md) — Équivalent basketball
- [`tennis:import-from-cache`](tennis-import-players-from-cache.md) — Même pattern pour le tennis
