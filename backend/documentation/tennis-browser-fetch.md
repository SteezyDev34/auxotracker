# Tennis — Fetch via navigateur (contournement 403 Sofascore)

> Depuis 2026-06, Sofascore bloque les requêtes HTTP directes depuis PHP (Docker ou serveur).
> Les appels retournent 403. Le workaround est de passer par un navigateur réel qui dispose des cookies et headers Sofascore.

---

## Pourquoi ce problème

Sofascore a renforcé sa détection anti-bot. Les requêtes PHP (`Http::get()`) sont bloquées même avec des headers imitant un navigateur, car elles n'ont pas les cookies de session Sofascore. Un navigateur réel (Chrome ouvert sur sofascore.com) dispose de ces cookies et passe sans problème.

---

## Vue d'ensemble du workflow

```
Avant (PHP direct — ne fonctionne plus)
  PHP → HTTP → Sofascore API → ❌ 403

Maintenant (via navigateur)
  1. PHP génère la liste des URLs à fetcher
  2. Chrome fetch les URLs (avec cookies Sofascore)
  3. PHP lit le cache et importe en BDD
```

---

## Étapes détaillées

### Étape 1 — Générer la liste des URLs manquantes

```bash
# Dans Docker
docker compose exec web php artisan tennis:generate-fetch-list [options]

# Ou PHP direct
php artisan tennis:generate-fetch-list [options]
```

**Options disponibles :**

| Option | Description |
|--------|-------------|
| `--force` | Inclure les URLs déjà en cache (re-fetch tout) |
| `--with-images` | Inclure les URLs des photos joueurs |
| `--with-stats` | Inclure les URLs des statistiques annuelles |
| `--date-offset=1` | Générer pour J+1 (utile proche de minuit) |

**Ce que la commande produit :**

- `storage/app/sofascore_cache/urls_to_fetch.json` — liste structurée des URLs
- `storage/app/sofascore_cache/urls_to_fetch_script.js` — script JS prêt à coller dans Chrome

**Contenu de `urls_to_fetch.json` :**

```json
{
  "generated_at": "2026-06-28 23:45:00",
  "date": "2026-06-28",
  "count": 42,
  "urls": [
    {
      "url": "https://www.sofascore.com/api/v1/sport/tennis/events/live",
      "save_path": "/var/www/.../sofascore_cache/source_live_2026-06-28.json",
      "type": "event_source",
      "label": "Source live",
      "response_type": "json"
    },
    {
      "url": "https://www.sofascore.com/api/v1/team/52276",
      "save_path": ".../players/player_details_52276.json",
      "type": "player_details",
      "player_id": 52276,
      "player_name": "Mitchell Krueger",
      "response_type": "json"
    }
  ]
}
```

---

### Étape 2 — Fetch via Chrome (demander à Claude)

Demander à Claude d'exécuter le script JS généré via Claude in Chrome. Claude :

1. Lit `urls_to_fetch_script.js`
2. L'exécute dans Chrome (qui est connecté à sofascore.com)
3. Récupère toutes les réponses JSON/images
4. Écrit chaque réponse dans le `save_path` correspondant

**Le script JS intégré** fait des fetch par batch de 5 en parallèle avec `credentials: 'include'` (utilise les cookies Chrome), puis retourne un rapport `{ ok, errors, results }`.

> Sofascore doit être ouvert dans Chrome pour que les cookies soient présents. Si le navigateur n'a pas de session active, les 403 persistent.

---

### Étape 3 — Importer depuis le cache

```bash
# Importer avec zéro appel réseau
php artisan tennis:import-from-schedule --offline --download-images --download-logos
```

**Mode `--offline` :**
- Lit les fichiers `source_live_{date}.json` et `source_featured_{date}.json` au lieu d'appeler l'API
- Bloque tout appel `makeHttpRequest()` (log d'avertissement si tenté)
- Compatible avec toutes les autres options (`--force`, `--limit`, `--date-offset`)

Puis Phase 2 comme d'habitude :

```bash
php artisan tennis:import-from-cache --download-images --download-logos
```

---

## Workflow complet (résumé)

```bash
# 1. Générer la liste des URLs
php artisan tennis:generate-fetch-list --with-images

# 2. Demander à Claude de fetcher via Chrome
#    → Claude exécute urls_to_fetch_script.js dans Chrome
#    → Les fichiers sont écrits dans sofascore_cache/

# 3. Vérifier que les fichiers sources sont bien là
ls storage/app/sofascore_cache/source_*_$(date +%Y-%m-%d).json

# 4. Importer en mode offline
php artisan tennis:import-from-schedule --offline --download-images

# 5. Persister en BDD (Phase 2)
php artisan tennis:import-from-cache --download-images --download-logos
```

---

## Fichiers impliqués

| Fichier | Rôle |
|---------|------|
| `app/Console/Commands/GenerateTennisFetchList.php` | Génère `urls_to_fetch.json` + script JS |
| `storage/app/sofascore_cache/urls_to_fetch.json` | Liste des URLs à fetcher |
| `storage/app/sofascore_cache/urls_to_fetch_script.js` | Script JS pour Chrome |
| `storage/app/sofascore_cache/source_live_{date}.json` | Réponse source live (écrite par Chrome) |
| `storage/app/sofascore_cache/source_featured_{date}.json` | Réponse source featured (écrite par Chrome) |

---

## Types d'URLs gérés

| Type | URL pattern | `response_type` | Destination cache |
|------|-------------|-----------------|-------------------|
| `event_source` | `/sport/tennis/events/live` | `json` | `source_live_{date}.json` |
| `event_source` | `/odds/1/featured-events/tennis` | `json` | `source_featured_{date}.json` |
| `player_details` | `/team/{id}` | `json` | `players/player_details_{id}.json` |
| `player_stats` | `/team/{id}/year-statistics/{year}` | `json` | `players/statistics/player_statistics_{id}.json` |
| `player_image` | `/team/{id}/image` | `binary` | `players/logos/{id}.png` |

---

## Gestion des erreurs

- Si un `save_path` n'est pas écrit après le fetch → la commande `--offline` loggue un avertissement et passe au joueur suivant
- Si `source_live_{date}.json` ou `source_featured_{date}.json` est absent → `--offline` affiche une erreur et conseille de relancer `tennis:generate-fetch-list`
- Les tombstones négatifs (24h) sont respectés par `generate-fetch-list` : un joueur dont le fetch a échoué hier ne sera pas retenté avant demain, sauf `--force`

---

## Option `--date-offset` (proche de minuit)

Si l'import est lancé à 23h50 et que les matchs à mettre en cache commencent à minuit (J+1) :

```bash
# Générer pour demain
php artisan tennis:generate-fetch-list --with-images --date-offset=1

# Après fetch Chrome, importer pour demain
php artisan tennis:import-from-schedule --offline --date-offset=1 --download-images
```

Les fichiers sources et marqueurs seront nommés avec la date J+1.

---

## Voir aussi

- [Phase 1 — Collecte cache](tennis-cache-players.md)
- [Phase 2 — Import BDD](tennis-import-players-from-cache.md)
- [Routes API Sofascore](sofascore-api-tennis.md)
