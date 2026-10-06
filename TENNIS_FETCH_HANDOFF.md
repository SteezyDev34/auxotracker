# Handoff — Fetch tennis via extension Chrome (2026-09-29)

Point de reprise après compactage de session. Contexte : cycle `/import-tennis`,
passage de Selenium à une extension Chrome pour contourner le blocage 403
Sofascore, puis débogage de blocages anti-bot récurrents.

## État actuel : NON VALIDÉ EN RUN COMPLET

Le dernier gros changement (interleaving fetch/import/sync — voir plus bas)
vient d'être écrit et **syntax-checké seulement**, jamais testé en conditions
réelles. **Prochaine étape immédiate : lancer un run complet et vérifier que
ça fonctionne** avant de considérer quoi que ce soit comme acquis.

```bash
cd "/Volumes/WORKSPACE/NEW BET TRACKER/backend"
# Docker doit tourner (docker info) + Chrome ouvert avec l'extension chargée
rmdir .locks/cache_tennis.lock 2>/dev/null
SKIP_LOGOS=1 TENNIS_FORCE=1 TENNIS_TRANSPORT=extension bash script/cache_tennis.sh
```

## Ce qui a été fait dans l'ordre

### 1. Extension Chrome (`backend/script/chrome_extension_sofascore/`)
Remplace le fetch Selenium par un pont WebSocket (`sofascore_extension_bridge.py`
côté Python ↔ `service_worker.js`/`content_fetch.js` côté extension). Plusieurs
itérations de bug ont eu lieu :

1. **v1** : fetch() direct depuis le service worker → bloqué par l'anti-bot
   (signature de requête différente d'une vraie navigation).
2. **v2** : fetch() depuis un content script injecté dans un onglet
   sofascore.com → mieux, mais un onglet déjà ouvert AVANT le rechargement de
   l'extension n'a pas le content script (`Could not establish connection`).
   → Fix : injection à la volée via `chrome.scripting.executeScript`
   (permission `scripting` ajoutée au manifest).
3. **v3 (actuel)** : le fetch() lui-même (même en page) se faisait quand même
   bloquer par moments. **Changement de mécanisme** : au lieu d'un `fetch()`,
   le service worker fait une VRAIE NAVIGATION de l'onglet
   (`chrome.tabs.update({url})`) vers l'URL cible — exactement comme taper
   l'URL dans la barre d'adresse — puis le content script lit le JSON déjà
   affiché par le navigateur (`document.body.innerText`). Le vrai code HTTP
   est lu via `performance.getEntriesByType('navigation')[0].responseStatus`
   (repli sur le code d'erreur Sofascore `{"error":{"code":404,...}}` si absent).
4. Les images (`api.sofascore.com`, domaine différent) passent par un fetch
   direct du service worker (`fetchDirect`) — CORS bypass via host_permissions,
   ce domaine n'a jamais semblé soumis au même anti-bot que les endpoints JSON.
   **Mais** ce fetch d'images bloquait en silence (~65s par échec × 80 logos =
   très long) → **contournement temporaire : `SKIP_LOGOS=1`** désactive tout
   téléchargement d'image (`_browser_fetch_image` retourne `None` direct).
   Le vrai bug CORS n'a jamais été confirmé ni corrigé — à reprendre si les
   logos/images sont nécessaires.

**Si un futur blocage réapparaît** : demander à l'utilisateur d'ouvrir la
console DevTools du service worker (`chrome://extensions` → "Service worker")
PENDANT qu'un fetch tourne, pas après — les logs post-mortem ne montrent que le
bruit de reconnexion WebSocket.

### 2. Rate-limiting / anti-bot sur le volume de requêtes
Indépendamment du mécanisme extension, Sofascore bloque (403 "challenge") au
delà d'un certain volume de requêtes **cumulé sur toute la session**, pas par
endpoint précis (vérifié : le blocage apparaît aussi bien en pleine étape
"joueurs" qu'en étape "point-by-point" — c'est le volume total qui compte, pas
le type d'URL). Correctifs apportés à `fetch_sofascore_cache.py` :

- Tous les délais fixes (`time.sleep(0.1/0.2/0.3/0.5)`) remplacés par des
  délais aléatoires plus longs (`random.uniform(0.7, 2.2)` selon les endroits).
- Pause longue de 90s (`LONG_PAUSE_SECONDS`) après 3 x 403 consécutifs dans
  `fetch_player_point_by_point_history`.
- **Cache ajouté pour `team/{id}/events/last/0`** (jamais caché avant — c'était
  la requête la plus fréquente et non cachée du pipeline). TTL 20h, dossier
  `players/last_events/`. **Important** : ne PAS écrire de cache négatif sur un
  403 (bloqué temporairement ≠ vraie absence de données) — seul un vrai statut
  définitif (200 sans events, ou autre) est mis en cache négatif.
- **File de retry** : les joueurs dont `events/last/0` échoue en 403 sont
  mémorisés (`retry_queue_403`) et retentés UNE fois après une pause de 3 min
  à la toute fin de `fetch_player_point_by_point_history`, plutôt que perdus.

### 3. Interleaving fetch/import/sync (dernier chantier, NON TESTÉ)
Idée de l'utilisateur : au lieu de tout fetcher puis tout importer/synchroniser
d'un coup (rafale de requêtes sans interruption = repérable comme scraping),
intercaler l'import PHP local (et périodiquement le sync+import prod) PENDANT
le fetch — le temps de calcul/réseau de ces étapes crée un délai "utile" entre
les paquets de requêtes Sofascore.

Implémenté dans `fetch_sofascore_cache.py` :
- `maybe_run_local_import()` : tous les 15 joueurs/événements traités (compteur
  `_interleave_counter["since_local_import"]`), lance
  `docker compose exec -T web php artisan tennis:import-from-cache --force --skip-archive`.
  Appelé dans les 4 boucles principales (détails joueurs, rankings, H2H/cotes,
  point-by-point).
- `maybe_run_prod_sync()` : tous les 60 (via `maybe_run_local_import`), lance
  `send_cache_and_archive.sh` puis le `tennis:import-from-cache --force` sur
  prod via SSH.
- Flush final (`force=True` sur les deux) à la fin de `fetch_tennis()` pour ne
  pas perdre le dernier lot partiel.

**Risques non vérifiés** :
- Overhead réel de `docker compose exec` + import PHP appelé ~85+ fois sur un
  run complet (1280 joueurs / 15 ≈ 85 déclenchements) — durée totale du run
  potentiellement bien plus longue qu'avant, à mesurer.
- `subprocess.run(cwd=PROJECT_DIR, timeout=180)` — un import qui dépasse 180s
  échouera silencieusement (juste un warning stderr, le fetch continue).
- Le sync prod par lot de 60 fait ~1280/60 ≈ 21 connexions SSH sur un run
  complet — à voir si c'est acceptable en pratique niveau durée totale.

## Fichiers modifiés cette session

- `backend/script/chrome_extension_sofascore/manifest.json`
- `backend/script/chrome_extension_sofascore/service_worker.js`
- `backend/script/chrome_extension_sofascore/content_fetch.js`
- `backend/script/fetch_sofascore_cache.py` (délais, cache last_events, retry
  queue, interleaving import/sync, `SKIP_LOGOS`)
- `backend/script/cache_tennis.sh` (transport extension par défaut)
- `backend/script/sofascore_extension_bridge.py` (pont WS, import paresseux)
- `backend/app/Console/Commands/ComputeTennisTightnessScores.php` (blend tier)
- `backend/app/Console/Commands/ImportTennisPlayersFromCache.php` (compteurs
  match-level cappés à 10 jeux, tier stats)
- `backend/app/Console/Commands/BacktestTennisScores.php` (validation LOO du
  nouvel indicateur "in_set")
- `backend/app/Http/Controllers/MatchController.php` +
  `TennisPlayerTensionStatController.php` (exposent les nouveaux champs
  `prob_*_in_set` / `stats_in_set`)
- `.claude/skills/import-tennis/SKILL.md` (documente les 2 tunnels + le
  diagnostic anti-bot)
- `/Users/steeven/PycharmProjects/martingale-40A1/CLAUDE.md` (indication pour
  ce projet séparé sur le nouveau champ `stats_in_set`)

Migrations Laravel (déjà appliquées local + prod) : nouvelle table
`tennis_player_game_tension_stats_by_tier`, compteurs `count_matches_reach_*`,
champs `prob_*_in_set` sur `tennis_match_tightness_scores`. **Attention** : une
migration a un nom d'index MySQL trop long, corrigé dans le fichier source
mais nécessite un fix manuel en prod si rejouée depuis zéro (voir historique
de conversation si besoin — `tension_stats_by_tier_team_tier_unique`).

## Prochaine étape

1. Lancer le run complet (commande en haut de ce fichier).
2. Vérifier que l'interleaving ne casse rien (DB lockée, conflits d'écriture
   concurrente entre le fetch Python qui tourne et l'import PHP qu'il déclenche
   lui-même en sous-process pendant que... — à surveiller, pas testé).
3. Si ça fonctionne : terminer le cycle (étapes 3-5 du skill `/import-tennis` :
   sync final, import prod final, `tennis:compute-tightness-scores` prod).
4. Si les logos sont nécessaires : reprendre le diagnostic CORS sur
   `api.sofascore.com` (actuellement contourné via `SKIP_LOGOS=1`, jamais
   vraiment résolu).
