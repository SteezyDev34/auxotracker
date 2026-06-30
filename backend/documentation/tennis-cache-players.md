# `tennis:import-from-schedule` — Phase 1 : Collecte et mise en cache

## Informations générales

| Propriété | Valeur |
|-----------|--------|
| **Commande** | `php artisan tennis:import-from-schedule` |
| **Classe** | `App\Console\Commands\ImportTennisPlayers` |
| **Fichier** | `app/Console/Commands/ImportTennisPlayers.php` |
| **Environnement** | **Local uniquement** (fait des appels API Sofascore) |
| **Phase** | 1 / 2 |

## Description

Collecte les données des joueurs et tournois de tennis depuis l'API Sofascore et les écrit dans des fichiers de cache. **Ne touche pas la base de données.** Le cache est ensuite transféré en production via rsync, puis importé par `tennis:import-from-cache` (Phase 2).

---

## Sources API utilisées (depuis 2026-06)

> ⚠️ L'endpoint `scheduled-events/{date}` retourne **404** depuis juin 2026. Deux sources remplacent :

| Source | URL | Clé JSON | Rôle |
|--------|-----|----------|------|
| Live | `GET /api/v1/sport/tennis/events/live` | `events` | Matchs en cours |
| Featured | `GET /api/v1/odds/1/featured-events/tennis` | `featuredEvents` | Matchs mis en avant |

Les deux sources sont fusionnées et dédupliquées par `event.id` avant traitement.

---

## Options

| Option | Défaut | Description |
|--------|--------|-------------|
| `--force` | `false` | Ignore le cache existant et re-télécharge tout |
| `--no-cache` | `false` | Désactive le cache intelligent (toujours appeler l'API) |
| `--delay=` | `1` | Délai en secondes entre requêtes API |
| `--limit=` | `null` | Limiter le nombre de joueurs collectés |
| `--download-images` | `false` | Télécharger les photos des joueurs |
| `--download-logos` | `false` | Télécharger les logos des tournois |

---

## Ce que la commande écrit dans le cache

```
storage/app/sofascore_cache/
│
├── tennis_LEAGUE_DONE_{date}_{uniqueTournamentId}     ← marqueur par tournoi
│
└── tennis_players/
    ├── tournaments/
    │   └── events/
    │       └── event_{id}.json                         ← event complet Sofascore
    ├── players/
    │   ├── player_basic_{id}.json                      ← données joueur (Phase 2 → Team)
    │   ├── player_details_{id}.json                    ← détails /api/v1/team/{id}
    │   ├── logos/
    │   │   └── {id}.png                                ← photo joueur (--download-images)
    │   └── statistics/
    │       └── player_statistics_{id}.json             ← stats annuelles
    ├── metadata/                                        ← métadonnées cache + tombstones
    └── compressed/                                      ← fichiers volumineux compressés gzip
│
└── tennis_leagues/
    └── logos/
        └── {uniqueTournamentId}.png                    ← logo tournoi (--download-logos)
```

### Structure `player_basic_{id}.json`

```json
{
  "name": "Mitchell Krueger",
  "slug": "krueger-mitchell",
  "nickname": "M. Krueger",
  "sofascore_id": 52276,
  "gender": "M",
  "country_code": "US",
  "ranking": 350
}
```

### Structure du marqueur `tennis_LEAGUE_DONE_{date}_{id}`

```json
{
  "done_at": 1782673200,
  "sofascore_id": 6636,
  "name": "Cary, USA",
  "slug": "cary-usa",
  "tennis_points": 75,
  "category_id": 72,
  "category_name": "Challenger",
  "category_slug": "challenger",
  "category_flag": "challenger"
}
```

---

## Fonctionnement étape par étape

1. Nettoyage du cache expiré (> 7 jours)
2. Appel des deux sources API → fusion des events par `event.id`
3. Groupement des events par `uniqueTournament.id`
4. Pour chaque tournoi :
   - Vérification du marqueur `LEAGUE_DONE` : si présent et `--force` absent → skip
   - Pour chaque match : cache de l'event, cache des joueurs (homeTeam + awayTeam)
   - Écriture du marqueur enrichi
   - Téléchargement du logo tournoi si `--download-logos`
5. Pour chaque joueur non déjà en cache :
   - `player_basic_{id}.json` (données de l'event)
   - `player_details_{id}.json` (appel `GET /api/v1/team/{id}`)
   - `player_statistics_{id}.json` (appel `GET /api/v1/team/{id}/year-statistics/{year}`)
   - Photo joueur si `--download-images`

### Cache intelligent

- **TTL adaptatif** : tournois = 1h, joueurs = 7j, métadonnées = 30min
- **Cache négatif (tombstone)** : si une requête API échoue, un tombstone est écrit dans `metadata/` valide 24h → évite les re-tentatives inutiles
- **Déduplication inter-exécutions** : `processedPlayerIds[]` en mémoire évite de traiter deux fois le même joueur dans la même exécution

---

## Exemples

```bash
# Collecte standard
php artisan tennis:import-from-schedule

# Tout forcer (ignore cache + tombstones)
php artisan tennis:import-from-schedule --force

# Avec images et logos
php artisan tennis:import-from-schedule --download-images --download-logos

# Limiter à 20 joueurs (test rapide)
php artisan tennis:import-from-schedule --limit=20 --download-images
```

---

## Script de déclenchement (`cache_tennis.sh`)

Le script `backend/script/cache_tennis.sh` encapsule la Phase 1 avec :
- Vérouillage (fichier `.lock`) pour éviter les exécutions parallèles
- Marqueur journalier `tennis_CACHE_DONE_{date}` : si présent, le script skip sauf si `TENNIS_FORCE=1`
- Détection automatique Docker / PHP direct

Variables d'environnement utilisables dans le cron :

| Variable | Valeur exemple | Effet |
|----------|---------------|-------|
| `TENNIS_FORCE=1` | `1` | Passe `--force`, ignore le marqueur journalier |
| `TENNIS_DOWNLOAD_IMAGES` | `--download-images` | Active le téléchargement des photos |
| `TENNIS_LIMIT` | `100` | Passe `--limit=100` |
| `TENNIS_DELAY` | `2` | Passe `--delay=2` |

```bash
# Forcer une re-collecte complète
TENNIS_FORCE=1 bash backend/script/cache_tennis.sh

# Collecte sans images
TENNIS_DOWNLOAD_IMAGES= bash backend/script/cache_tennis.sh
```

---

## Notes importantes

- Cette commande **ne modifie jamais la base de données**.
- Ne pas exécuter en production : elle fait des appels HTTP à Sofascore.
- Pour forcer le re-téléchargement d'une image dont le tombstone bloque : supprimer `metadata/player_image_{id}.meta` ou utiliser `--force`.
