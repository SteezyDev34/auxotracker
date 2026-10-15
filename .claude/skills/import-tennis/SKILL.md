---
name: import-tennis
description: Cycle complet d'import Tennis 🎾 — fetch Sofascore, sync + import prod automatique par lots, BDD locale (MAMP) rafraîchie depuis un dump prod en fin de run.
---

# Import Tennis 🎾 — Cycle complet

Depuis le 2026-10-15, **tout le cycle est automatisé dans un seul run** (`cache_tennis.sh` → `fetch_sofascore_cache.py`) : plus d'étapes manuelles séparées de sync/import prod/import local à enchaîner.

Ce qui se passe pendant le run, automatiquement :
1. Fetch Sofascore (cache local des matchs/joueurs/stats/H2H/point-by-point).
2. Tous les `MATCH_BATCH_SIZE` matchs : sync du cache vers prod (`send_cache_and_archive.sh`) puis, **sur prod** via SSH, `tennis:import-from-schedule --offline` (génère les matchs du jour) + `tennis:import-from-cache --force --download-images --download-logos` + `tennis:compute-tightness-scores --force`. Prod est donc à jour en continu pendant tout le run.
3. Flush final : sync + archivage, puis **BDD locale (MAMP) entièrement rafraîchie depuis un dump de prod** (`refresh_local_db_from_prod()` — mysqldump sur prod via SSH, téléchargé, importé dans MySQL local sur le port `8889`). La BDD locale devient donc une copie exacte de prod une fois le run terminé, sans jamais être alimentée incrémentalement en local pendant le run (plus simple, prod étant déjà la seule source de vérité).

Docker local n'est plus un blocage même s'il est cassé : `cache_tennis.sh` détecte si son daemon répond réellement (`docker info`, pas juste la présence du binaire) et bascule sur PHP natif sinon — mais ça ne concerne plus que des commandes ponctuelles, l'essentiel tourne côté prod.

## Instructions

### 0. Choix du tunnel de fetch (à demander AVANT de lancer l'étape 1)

Sofascore bloque les clients HTTP directs (403 IP ban) — le fetch doit passer par un vrai navigateur. Deux tunnels existent, demander à l'utilisateur lequel utiliser s'il ne l'a pas précisé :

- **`extension`** (défaut) — passe par l'extension Chrome `chrome_extension_sofascore` (voir `backend/script/chrome_extension_sofascore/`). Les fetch sont exécutés depuis un content script injecté dans un vrai onglet sofascore.com (pas depuis le service worker en arrière-plan — un fetch() lancé depuis le service worker a une signature de requête différente d'une navigation normale et se fait bloquer par l'anti-bot, cf. incident du 2026-09-29). **Prérequis : Chrome doit être ouvert avec l'extension chargée et connectée** (`chrome://extensions` → mode développeur → charger l'extension non empaquetée si pas déjà fait, ou recharger ↻ si le code de l'extension a changé) avant de lancer l'étape 1. Sans ça, le fetch bloque jusqu'à 120s puis échoue. Demander à l'utilisateur de confirmer que Chrome est prêt avant de lancer.
- **`selenium`** — Chrome headless piloté par Selenium (`fetch_sofascore_cache.py`), entièrement automatisé, ne nécessite aucune action manuelle. Plus lent à démarrer (warm-up de session) mais ne dépend pas d'un navigateur ouvert par l'utilisateur.

Une fois le choix fait, exporter `TENNIS_TRANSPORT=extension` ou `TENNIS_TRANSPORT=selenium` avant l'étape 1.

**Si le fetch échoue en boucle avec des `status=403 reason=challenge`** (sur les DEUX tunnels, extension et selenium) alors que l'utilisateur confirme avoir accès au site dans son propre navigateur : ce n'est PAS un ban IP (vérifié le 2026-09-29 : même IP bloquée pour le fetch automatisé mais site accessible manuellement) — c'est l'anti-bot qui détecte la signature de la requête automatisée elle-même. Vérifier que l'extension utilise bien le content script (voir `content_fetch.js` + relai via `chrome.tabs.sendMessage` dans `service_worker.js`), pas un fetch direct depuis le service worker. Si le blocage persiste malgré ça, demander à l'utilisateur de changer d'IP (VPN) en dernier recours.

### 1. Lancer le cycle complet
```bash
cd "/Volumes/WORKSPACE/NEW BET TRACKER/backend"
TENNIS_FORCE=1 TENNIS_TRANSPORT=extension bash script/cache_tennis.sh 2>&1 | tail -20
# ou : TENNIS_TRANSPORT=selenium bash script/cache_tennis.sh
```
C'est la SEULE commande à lancer — sync, import prod et rafraîchissement de la BDD locale se font tout seuls (voir ci-dessus).

### 2. Report
Show a summary: leagues, matches, players created/updated (prod), tightness scores computed (prod), et confirmer que la BDD locale a bien été rafraîchie en fin de run (chercher `✅ BDD locale rafraîchie depuis prod` dans la sortie).

**Important**:
- Toujours demander/confirmer le tunnel (étape 0) avant de lancer l'étape 1 — ne pas supposer `extension` par défaut sans vérifier que Chrome est prêt.
- Ces scripts lisent uniquement depuis le cache — aucun appel à l'API Sofascore directement (contrainte prod).
- Le code PHP de prod (o2switch) n'est PAS redéployé par ce skill — seuls les fichiers de cache sont synchronisés. Si des changements de code/migrations ont été faits en local (ex: nouvelles colonnes), ils ne sont pas automatiquement présents sur prod tant qu'un déploiement de code (`deploy-o2switch.sh`/`deploy-production.sh` + `php artisan migrate --force` sur prod) n'a pas été fait séparément.
- Si le run est interrompu avant la fin (Ctrl+C, crash), la BDD locale ne sera PAS rafraîchie (ça n'arrive qu'au flush final) — relancer le run, ou rafraîchir manuellement :
  ```bash
  cd "/Volumes/WORKSPACE/NEW BET TRACKER/backend/script"
  /usr/bin/python3 -c "import fetch_sofascore_cache as f; f.refresh_local_db_from_prod()"
  ```
- MAMP doit être démarré (MySQL sur le port `8889`) pour que ce rafraîchissement fonctionne — sinon il échoue proprement (message d'erreur, pas de crash) et la BDD locale reste simplement pas à jour jusqu'au prochain run réussi.
