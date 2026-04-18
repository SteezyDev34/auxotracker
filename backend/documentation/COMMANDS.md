# Commandes Artisan — Vue d'ensemble

> Documentation complète des 29 commandes Artisan du projet **Bet Tracker**.
> Chaque commande possède sa propre page de documentation détaillée dans ce dossier.

---

## Table des matières

- [Bankroll & Paris](#bankroll--paris)
- [Import de données — Par sport](#import-de-données--par-sport)
- [Import de données — Équipes & Joueurs](#import-de-données--équipes--joueurs)
- [Téléchargement d'assets](#téléchargement-dassets)
- [Extraction & Parsing](#extraction--parsing)
- [Maintenance](#maintenance)
- [Tests & Diagnostics](#tests--diagnostics)

---

## Bankroll & Paris

| Commande | Description | Modifie la DB | Options clés |
|---|---|---|---|
| [`bankroll:list`](bankroll-list.md) | Liste toutes les bankrolls avec stats | ❌ Non | `--user=` |
| [`bankroll:adjust-stakes`](bankroll-adjust-stakes.md) | Ajuste les mises selon une stratégie de money management | ✅ Oui | `--strategy=`, `--dry-run`, `--sport-id=` |
| [`bets:import-json`](bets-import-json.md) | Importe des paris depuis un fichier JSON | ✅ Oui | `--bankroll-id=`, `--dry-run` |

---

## Import de données — Par sport

### Football

| Commande | Phase | Description | API | Cache |
|---|---|---|---|---|
| [`football:import-from-schedule`](football-import-from-schedule.md) | Phase 1 | Collecte les données football → cache (pas de BDD) | Sofascore `/sport/football/scheduled-tournaments` | ✅ Écriture |
| [`football:import-from-cache`](football-import-from-cache.md) | Phase 2 | Importe les données football cache → BDD | Cache local | ✅ Lecture |
| [`football:import-leagues`](football-import-leagues.md) | — | Importe les pays et ligues de football | Sofascore `/sport/football/categories` | ✅ Oui |

### Basketball

| Commande | Phase | Description | API | Cache |
|---|---|---|---|---|
| [`basketball:import-from-schedule`](basketball-import-from-schedule.md) | Phase 1 | Collecte les données basketball → cache (pas de BDD) | Sofascore `/sport/basketball/scheduled-tournaments` | ✅ Écriture |
| [`basketball:import-from-cache`](basketball-import-from-cache.md) | Phase 2 | Importe les données basketball cache → BDD | Cache local | ✅ Lecture |
| [`basketball:import-leagues`](basketball-import-leagues.md) | — | Importe les pays et ligues de basketball | Sofascore `/sport/basketball/categories` | ✅ Oui |

### Tennis

| Commande | Phase | Description | API | Cache |
|---|---|---|---|---|
| [`tennis:cache-players`](tennis-cache-players.md) | Phase 1 | Collecte et cache les données joueurs de tennis (pas de BDD) | Sofascore | ✅ Écriture |
| [`tennis:import-from-cache`](tennis-import-players-from-cache.md) | Phase 2 | Importe les joueurs de tennis depuis le cache vers la BDD | Cache local | ✅ Lecture |
| [`atp:import-players`](atp-import-players.md) | — | Importe les joueurs ATP depuis le classement | Sofascore `/rankings/type/5` | ❌ Non |
| [`wta:import-players`](wta-import-players.md) | — | Importe les joueuses WTA depuis le classement | Sofascore `/rankings/type/6` | ❌ Non |

> Remarques spécifiques (tennis)

- `tennis:cache-players` écrit désormais un marqueur par tournoi traité : `tennis_LEAGUE_DONE_{YYYY-MM-DD}_{sofascoreId}`. La commande vérifie ces marqueurs et évite de retraiter un tournoi pour la même date, sauf si `--force` est passé.
- Lors du téléchargement d'images, un tombstone `metadata/player_image_{sofascoreId}.meta` est créé en cas d'échec ; il est valable 24h et empêche de retenter le téléchargement pendant cette période. Utiliser `--force` ou `backend/script/clear_import_markers.sh --sport tennis --all` pour forcer un nouveau téléchargement.

### Autres sports

| Commande | Description | API | Cache |
|---|---|---|---|
| [`handball:import-leagues`](handball-import-leagues.md) | Importe les ligues de handball | Sofascore `/sport/handball/categories` | ❌ Non |
| [`ice-hockey:import-leagues`](ice-hockey-import-leagues.md) | Importe les ligues de hockey sur glace | Sofascore `/sport/ice-hockey/categories` | ❌ Non |
| [`rugby:import-leagues`](rugby-import-leagues.md) | Importe les ligues de rugby | Sofascore `/sport/rugby/categories` | ❌ Non |

### Générique

| Commande | Description | API | Cache |
|---|---|---|---|
| [`sport:import-leagues`](sport-import-leagues.md) | Importeur générique de ligues pour **tout sport** via son slug | Sofascore `/sport/{slug}/categories` | ✅ Oui |

---

## Import de données — Équipes & Joueurs

| Commande | Description | Mode | Cache |
|---|---|---|---|
| [`teams:import`](teams-import.md) | Importe les équipes par plage d'IDs Sofascore | ID par ID | ✅ Oui |
| [`teams:import-by-league`](teams-import-by-league.md) | Importe les équipes via les standings d'une ligue | Par ligue | ✅ Oui |
| [`players:import-by-team`](players-import-by-team.md) | Importe les joueurs d'une équipe | Par équipe | ✅ Oui |

---

## Téléchargement d'assets

| Commande | Description | Type d'asset | Options clés |
|---|---|---|---|
| [`team:download-logos`](team-download-logos.md) | Télécharge les logos d'équipes (filtrage avancé) | Logo d'équipe | `--league=`, `--empty-img`, `--force` |
| [`team:download-all-missing-logos`](team-download-all-missing-logos.md) | Télécharge tous les logos manquants en batch | Logo d'équipe | `--limit=`, `--delay=` |
| [`league:download-logos`](league-download-logos.md) | Télécharge les logos de ligues (light + dark) | Logo de ligue | `--empty-img`, `--force` |
| [`country:download-flags`](country-download-flags.md) | Télécharge les drapeaux des pays | Drapeau | `--all`, `--force` |

---

## Extraction & Parsing

| Commande | Description | Modifie la DB |
|---|---|---|
| [`bet:extract-market`](bet-extract-market.md) | Extrait les équipes d'un texte de pari (utilitaire de debug) | ❌ Non |
| [`bet:extract-market-all`](bet-extract-market-all.md) | Associe les équipes à tous les events sans `team1_id`/`team2_id` | ✅ Oui (sauf `--dry-run`) |

---

## Maintenance

| Commande | Description | Modifie la DB |
|---|---|---|
| [`league-team:sync`](league-team-sync.md) | Synchronise la table pivot `league_team` depuis `teams.league_id` | ✅ Oui |
| [`teams:update:nicknames`](teams-update-nicknames.md) | Met à jour les nicknames depuis `api.json` | ✅ Oui (sauf `--dry-run`) |

---

## Tests & Diagnostics

| Commande | Description | Modifie la DB |
|---|---|---|
| [`country:test-flags`](country-test-flags.md) | Teste la fonctionnalité de téléchargement des drapeaux | ❌ Non |
| [`docker:test-env`](docker-test-env.md) | Teste la détection d'environnement Docker et la connexion DB | ❌ Non |

---

## Options communes récurrentes

La plupart des commandes d'import partagent les options suivantes :

| Option | Description | Phase |
|---|---|---|
| `--force` | Forcer l'import/mise à jour même si les données existent déjà | Phase 2 |
| `--dry-run` | Simulation : affiche les changements sans modifier la base de données | Phase 2 |
| `--delay=N` | Délai en secondes entre chaque requête API (anti rate-limiting) | Phase 1 |
| `--no-cache` | Désactiver la lecture/écriture du cache local | Phase 1 |
| `--import-teams` | Pré-charger (Phase 1) ou importer (Phase 2) les équipes | Phase 1 & 2 |
| `--download-logos` | Télécharger les logos en même temps que l'import | Phase 2 |

---

## Architecture d'import en 2 phases

Les imports de données sportives suivent une architecture en **2 phases** séparées :

```
Phase 1 (API → Cache)       Phase 2 (Cache → BDD)
========================     ========================
tennis:cache-players    →    tennis:import-from-cache
football:import-from-schedule → football:import-from-cache
basketball:import-from-schedule → basketball:import-from-cache
```

**Avantages** :
- Découplage réseau/BDD : Phase 1 peut échouer sans corrompre la BDD
- Rejouabilité : Phase 2 peut être relancée sur le même cache
- Portabilité : les fichiers cache peuvent être synchronisés entre machines (ex. ljdsync)

---

## Architecture de cache

Les commandes d'import utilisent un système de cache local dans `storage/app/sofascore_cache/` :

```
storage/app/sofascore_cache/
├── basketball_schedule/{date}/       # basketball:import-from-schedule
├── football_schedule/{date}/         # football:import-from-schedule
├── categories_football.json          # football:import-leagues
├── leagues_country/{slug}-{id}/      # football:import-leagues
├── leagues_{sport_slug}/             # sport:import-leagues
├── leagues_teams/{name}-{id}/        # teams:import-by-league
├── teams_players/{name}-{id}/        # players:import-by-team
└── tennis_players/                   # tennis:cache-players
    ├── tournaments/
    ├── players/
    │   ├── logos/
    │   └── statistics/
    ├── metadata/
    └── compressed/
```

---

## API externe

Toutes les commandes d'import utilisent l'**API Sofascore** (`https://www.sofascore.com/api/v1/`). Points à retenir :

- **Rate limiting** : Sofascore bloque les requêtes trop fréquentes (HTTP 403). Utiliser `--delay` pour espacer les requêtes.
- **Rotation de User-Agent** : certaines commandes (basketball, sport générique) utilisent plusieurs User-Agents pour éviter la détection.
- **Les données ne sont pas officielles** : elles proviennent de l'API non documentée de Sofascore.

---

## Workflow recommandé pour un import complet

```bash
# 1. Phase 1 — Collecter les données dans le cache
php artisan football:import-from-schedule --import-teams
php artisan basketball:import-from-schedule --import-teams
php artisan tennis:cache-players --download-images

# 2. Phase 2 — Importer le cache en BDD
php artisan football:import-from-cache --import-teams --download-logos
php artisan basketball:import-from-cache --import-teams --download-logos
php artisan tennis:import-from-cache --download-images --force

# 3. (Optionnel) Importer les ligues via categories
php artisan sport:import-leagues football
php artisan sport:import-leagues basketball

# 4. Télécharger les assets manquants
php artisan league:download-logos --empty-img --delay=1
php artisan team:download-all-missing-logos --limit=500 --delay=2
php artisan country:download-flags --all

# 5. Synchroniser les relations
php artisan league-team:sync

# 6. Enrichir les données
php artisan teams:update:nicknames --dry-run
php artisan bet:extract-market-all --dry-run
```
