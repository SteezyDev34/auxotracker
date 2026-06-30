# Sofascore API — Routes Tennis

> Dernière mise à jour : 2026-06-28

---

## Routes utilisées

### Sources d'événements (Phase 1)

| Usage | URL | Clé JSON | Statut |
|-------|-----|----------|--------|
| ~~Matchs du jour~~ | ~~`GET /api/v1/sport/tennis/scheduled-events/{date}`~~ | `events` | ❌ 404 depuis 2026-06 |
| Matchs **live** | `GET /api/v1/sport/tennis/events/live` | `events` | ✅ OK |
| Matchs **featured** | `GET /api/v1/odds/1/featured-events/tennis` | `featuredEvents` | ✅ OK |

> Les deux sources sont fusionnées et dédupliquées par `event.id`.

### Routes joueurs

| Usage | URL | Statut |
|-------|-----|--------|
| Détails joueur | `GET /api/v1/team/{sofascoreId}` | ✅ OK |
| Statistiques annuelles | `GET /api/v1/team/{sofascoreId}/year-statistics/{year}` | ✅ OK |
| Image joueur | `GET /api/v1/team/{sofascoreId}/image` | ✅ OK |
| Logo tournoi | `GET /api/v1/unique-tournament/{tournamentId}/image` | ✅ OK |

---

## Résultats des tests (2026-06-28)

| URL | Statut | Clé réponse |
|-----|--------|-------------|
| `/api/v1/sport/tennis/scheduled-events/2026-06-28` | ❌ 404 | `error` |
| `/api/v1/sport/tennis/events/live` | ✅ 200 | `events` (4 résultats) |
| `/api/v1/odds/1/featured-events/tennis` | ✅ 200 | `featuredEvents` (5 résultats) |
| `/api/v1/sport/tennis/events/next/0` | ❌ 404 | `error` |
| `/api/v1/sport/tennis/featured-events` | ❌ 404 | `error` |
| `/api/v1/unique-tournament/2480/seasons` | ✅ 200 | `seasons` (ATP) |
| `/api/v1/unique-tournament/2481/seasons` | ✅ 200 | `seasons` (WTA) |
| `/api/v1/sport/tennis/categories` | ✅ 200 | `categories` |

---

## Notes sur la structure de l'API

- Le sport tennis a l'ID `5` dans certains endpoints legacy.
- Les joueurs ont deux IDs possibles : `team/{id}` (endpoint utilisé) vs `player/{id}` (nouveau, non utilisé).
- Les `uniqueTournament` = ligues/circuits (ATP Tour = 2480, WTA Tour = 2481).
- Les `categories` = groupes géographiques (France, USA, International…).
- `birthplace` et `residence` dans `playerTeamInfo` sont des **strings** ("Fort Worth, Texas, USA"), pas des objets.

---

## Mapping Leagues tennis connues

| Nom | uniqueTournamentId |
|-----|--------------------|
| ATP Tour | 2480 |
| WTA Tour | 2481 |
| ATP Challengers | 14426 |
| Davis Cup | 26 |

---

## Routes à explorer

| Description | URL candidate |
|-------------|---------------|
| Détail unique-tournament | `GET /api/v1/unique-tournament/{id}` |
| Standings d'une saison | `GET /api/v1/unique-tournament/{id}/season/{seasonId}/standings/total` |
| Classement ATP/WTA | `GET /api/v1/rankings/tennis` |
| H2H entre deux joueurs | `GET /api/v1/event/{eventId}/h2h` |

---

## Voir aussi

- [Phase 1 — Collecte cache](tennis-cache-players.md)
- [Phase 2 — Import BDD](tennis-import-players-from-cache.md)
- [Workflow fetch navigateur](tennis-browser-fetch.md)
