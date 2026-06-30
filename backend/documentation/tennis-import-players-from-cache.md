# `tennis:import-from-cache` — Phase 2 : Import cache → Base de données

## Informations générales

| Propriété | Valeur |
|-----------|--------|
| **Commande** | `php artisan tennis:import-from-cache` |
| **Classe** | `App\Console\Commands\ImportTennisPlayersFromCache` |
| **Fichier** | `app/Console/Commands/ImportTennisPlayersFromCache.php` |
| **Environnement** | **Local et Production** |
| **Phase** | 2 / 2 |

## Description

Lit les fichiers de cache produits par `tennis:import-from-schedule` (Phase 1) et les importe dans la base de données. **Ne fait aucun appel à l'API Sofascore.** Compatible local et production.

---

## Prérequis

Les fichiers de cache doivent être présents dans `storage/app/sofascore_cache/` :
- `tennis_LEAGUE_DONE_{date}_{uniqueTournamentId}` — marqueurs de tournoi
- `tennis_players/tournaments/events/event_{id}.json` — events
- `tennis_players/players/player_basic_{id}.json` — données joueurs

En production, ces fichiers sont transférés par rsync depuis la machine locale (voir [Workflow local → production](#workflow-local--production)).

---

## Options

| Option | Défaut | Description |
|--------|--------|-------------|
| `--force` | `false` | Re-importe même les joueurs déjà traités |
| `--limit=` | `null` | Limiter le nombre de joueurs traités |
| `--download-images` | `false` | Copier les photos des joueurs depuis le cache vers `storage/app/public/` |
| `--download-logos` | `false` | Copier les logos des tournois/ligues depuis le cache vers `storage/app/public/` |
| `--skip-archive` | `false` | Ne pas archiver les fichiers de cache après traitement (utile si rsync doit suivre) |

---

## Ce que la commande importe

### 1. Ligues (`League`)

Lit chaque marqueur `tennis_LEAGUE_DONE_{date}_{uniqueTournamentId}` et crée/met à jour un enregistrement `League` :

| Champ marqueur | Champ BDD |
|----------------|-----------|
| `sofascore_id` | `sofascore_id` |
| `name` | `name` |
| `slug` | `slug` |
| `tennis_points` | `tennis_points` |
| `category_name` | stocké en métadonnée |

### 2. Matchs (`MatchModel`)

Lit les fichiers `tournaments/events/event_{id}.json`. Un match est persisté si son `status.type` **n'est pas** `finished` (les matchs live ont un `startTimestamp` passé mais un statut `inprogress` → ils sont traités).

Champs importants du match :

| Champ event | Champ BDD |
|-------------|-----------|
| `id` | `sofascore_id` |
| `slug` | lien sofascore |
| `startTimestamp` | `scheduled_at` |
| `homeTeam.id` / `awayTeam.id` | relation `Team` |
| sport tennis | `sport_id` → lookup `Sport::where('sofascore_id', 5)` |

Lien Sofascore généré : `https://www.sofascore.com/match/{slug}/{customId}#id:{eventId}`

### 3. Joueurs / Équipes (`Team`)

Lit les fichiers `player_basic_{id}.json` et crée/met à jour un enregistrement `Team` :

| Champ `player_basic` | Champ BDD |
|----------------------|-----------|
| `name` | `name` |
| `slug` | `slug` |
| `nickname` | `nickname` |
| `sofascore_id` | `sofascore_id` |
| `gender` | `gender` |
| `country_code` | `country_code` |
| `ranking` | `ranking` |

> `league_id` n'est **pas** dans le payload (c'était un ID Sofascore non lié à la BDD). La relation League ↔ Team passe par le pivot `league_team` via `syncWithoutDetaching`.

### 4. Détails joueur (si `player_details_{id}.json` présent)

Appel de `updatePlayerWithDetails()` avec les données de `team.playerTeamInfo` :

| Champ API | Champ BDD | Note |
|-----------|-----------|------|
| `birthDateTimestamp` | `birth_date` | timestamp Unix |
| `height` | `height` | cm |
| `weight` | `weight` | kg |
| `plays` | `plays` | ex: "right-handed" |
| `birthplace` | `birth_place` | string directe "Fort Worth, Texas, USA" |
| `birthCity.name` | `birth_place` | fallback si `birthplace` absent |
| `residence` | `residence` | string directe |
| `residenceCity.name` | `residence` | fallback si `residence` absent |
| `currentRanking` | `ranking` | prioritaire sur le ranking de l'event |

### 5. Assets (si options activées)

- `--download-images` : copie `players/logos/{id}.png` du cache vers `storage/app/public/tennis/players/`
- `--download-logos` : copie `tennis_leagues/logos/{uniqueTournamentId}.png` vers `storage/app/public/tennis/leagues/`

Si un logo est absent du cache, un avertissement est affiché et la commande continue (relancer Phase 1 avec `--download-logos`).

---

## Workflow local → production

```
[LOCAL]
  1. php artisan tennis:import-from-schedule --download-images --download-logos
     → Remplit storage/app/sofascore_cache/

  2. (optionnel — test local)
     php artisan tennis:import-from-cache --download-images --download-logos --skip-archive

  3. rsync -av --delete \
       storage/app/sofascore_cache/ \
       user@prod:/var/www/app/storage/app/sofascore_cache/

[PRODUCTION]
  4. php artisan tennis:import-from-cache --download-images --download-logos
     → Aucun appel API — lecture du cache uniquement
```

### Contrainte production

La Phase 2 **ne fait aucun appel réseau**. Les anciens fallbacks API (`LeagueLogoService::ensureLeagueLogos()`, etc.) ont été supprimés. Si un asset manque en cache, un log d'avertissement est émis et l'exécution continue.

---

## Exemples

```bash
# Import complet en production (après rsync)
php artisan tennis:import-from-cache --download-images --download-logos

# Import en local, conserver les fichiers pour rsync ensuite
php artisan tennis:import-from-cache --download-images --download-logos --skip-archive

# Test rapide, 10 joueurs, sans assets
php artisan tennis:import-from-cache --limit=10

# Forcer le re-traitement de tous les joueurs
php artisan tennis:import-from-cache --force --download-images --download-logos
```

---

## Comportements clés

### Matchs live (correction 2026-06-28)

Avant : les matchs live étaient skippés car leur `startTimestamp` est dans le passé.  
Après : seuls les matchs avec `status.type === 'finished'` sont ignorés. Les matchs `inprogress` sont persistés.

### Logos manquants

Si `tennis_leagues/logos/{id}.png` est absent du cache :
- L'avertissement `⚠️ Logo absent du cache — relancer Phase 1 avec --download-logos` est loggé.
- La commande **ne tente pas d'appel API** et passe au suivant.

### `sport_id`

Résolu dynamiquement : `Sport::where('sofascore_id', 5)->orWhere('slug', 'tennis')->first()?->id ?? 5`  
Évite que l'ID Sofascore (5) soit utilisé comme ID BDD si les sports ne sont pas dans le même ordre.

---

## Voir aussi

- [Phase 1 — Collecte cache](tennis-cache-players.md)
- [Routes API Sofascore](../docs/sofascore-api-tennis.md)
