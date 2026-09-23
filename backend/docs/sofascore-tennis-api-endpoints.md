# Endpoints Sofascore utiles — score "match serré" tennis

Découverts et testés le 2026-08-05 via un vrai contexte navigateur (`https://www.sofascore.com` origin,
`credentials: 'include'`) — mêmes contraintes que `script/fetch_sofascore_cache.py` (accès direct
`api.sofascore.com` bloqué par CORS depuis ce contexte, mais les routes miroir sous
`www.sofascore.com/api/v1/...` fonctionnent).

Objectif : calculer un score de "match serré" (proximité de niveau, qualité de service, conversion
des balles de break, historique H2H, tie-breaks) — voir conversation du 2026-08-05 pour la
méthodologie complète.

## Routes confirmées fonctionnelles

| Donnée | Endpoint | Paramètre clé | Contenu |
|---|---|---|---|
| H2H détaillé (liste des confrontations passées + scores) | `event/{customId}/h2h/events` | **`customId`** (string, PAS l'event id numérique — ex: `PCKcsDJmd`) ; déjà présent dans les fichiers `event_*.json` du cache existant | Liste des matchs passés entre les 2 joueurs, avec `homeScore`/`awayScore` par set, `winnerCode`, tournoi, statut |
| H2H résumé (agrégat) | `event/{id}/h2h` | event id numérique | `{teamDuel: {homeWins, awayWins, draws}}` — pas de détail par match |
| Stats détaillées par match (post-match) | `event/{id}/statistics` | event id numérique | Groupes : Service (aces, doubleFaults, firstServeAccuracy, breakPointsSaved...), Points, Games, Return (breakPointsScored...), Miscellaneous (tiebreaks) |
| Point par point | `event/{id}/point-by-point` | event id numérique | Détail 15-0/30-0/40-0/deuce par jeu et par set — base pour un indice de tension historique |
| Cotes bookmaker | `event/{id}/odds/1/all` | event id numérique | Marchés (1X2, totaux jeux, etc.) |
| Classement + rang UTR | `team/{id}/rankings` | team/player id numérique | Tableau de 3 systèmes en parallèle : `rankingClass: "team"` (ATP/WTA officiel), `"livetennis"` (classement live alternatif), `"utr"` (**rang** dans le classement UTR — ex: 10e, 19e — PAS la note UTR elle-même, qui serait ~1-16. Corrigé le 2026-08-09, avait été mal interprété comme "proxy Elo" initialement) |
| Historique des matchs d'un joueur | `team/{id}/events/last/{page}` | team/player id, page (0 = plus récent) | 30 events/page, score détaillé par set (`period1/2/3`), `winnerCode`, statut — sert à construire soi-même le H2H si besoin ou la forme récente |
| Indice de forme propriétaire Sofascore | `event/{id}/tennis-power` | event id numérique | Contenu non exploré en détail, à creuser |

## Routes testées et NON fonctionnelles (404) — pour éviter de retester

`team/{id}/team-duel/{oppId}`, `event/{id}/h2h/events` (avec ID numérique — fonctionne seulement
avec `customId`), `event/{id}/h2h-events`, `event/{id}/h2h/general`, `event/{id}/h2h/tournament/events`,
`event/{id}/versus`, `event/{id}/duel`, `team/{id}/duel/{oppId}`, `team/{id}/h2h/{oppId}`,
`team/{id}/next-h2h-events`, `event/{id}/pregame-form`.

## État actuel du pipeline (audit du 2026-08-05)

- BDD : aucune colonne `ranking`, `elo`/`utr`, `odds`, ni table H2H. Seuls attributs statiques joueur
  (taille, main, date de naissance) et infos de match brutes (id, date, lien) sont persistés.
- `team.ranking` est déjà présent dans les payloads `team/{id}` fetchés (`player_details_*.json`)
  mais jamais extrait vers la BDD.
- `year-statistics` (déjà fetché en cache, `player_statistics_*.json`) contient déjà, par surface :
  aces, doubleFaults, first/secondServePoints, breakPointsScored/Total,
  opponentBreakPointsScored/Total (retour), tiebreaksWon/Losses, winners, unforcedErrors — mais
  jamais parsé/persisté, seulement compté présent/absent.
- H2H, odds, point-by-point, tennis-power : pas encore fetchés du tout.

## Implémentation (2026-08-07)

Fait :
- Migrations : `teams.ranking/utr_rating/livetennis_ranking/ranking_updated_at`,
  `tennis_player_season_stats`, `tennis_h2h_matches`, `tennis_match_odds`,
  `tennis_match_tightness_scores`.
- `script/fetch_sofascore_cache.py` : `fetch_player_rankings()` (TTL 7j, `team/{id}/rankings`),
  `fetch_h2h_and_odds_for_events()` (par match du jour, pas par joueur — `event/{customId}/h2h/events`
  + `event/{id}/odds/1/all`). `ranking` (ATP/WTA) ajouté à `_build_player_basic()` (flux existant).
- `ImportTennisPlayersFromCache.php` : `processPlayerRankings()`, `processSeasonStats()` (parse
  réel de `year-statistics`), `processH2hMatches()`, `processMatchOdds()` — appelées dans `handle()`
  après l'import joueurs.
- Nouvelle commande `tennis:compute-tightness-scores {--days=1} {--force}` : calcule un score /100
  (méthodologie du 2026-08-05) pour les matchs à venir, sans appel réseau (lecture BDD seule).
  Testé de bout en bout en local avec données réelles (Shapovalov/Merida/Lehečka/Michelsen) : score
  cohérent et détaillé par critère.

Pas fait (hors scope de cette passe) :
- Frontend (`TodayMatches.vue`) : score/proba pas encore affiché.
- Pas de wiring cron (`Kernel.php`) pour `tennis:compute-tightness-scores` — à lancer manuellement
  après `tennis:import-from-cache`, ou à ajouter au schedule si validé.
- `event/{id}/tennis-power` et point-by-point (indice de tension fin) non exploités.
