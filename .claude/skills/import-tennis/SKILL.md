---
name: import-tennis
description: Cycle complet d'import Tennis 🎾 — Phase 1 cache (local), sync prod, Phase 2 BDD (local + prod).
---

# Import Tennis 🎾 — Cycle complet

Lance le cycle complet : fetch Sofascore (2 tunnels possibles) → sync prod → import-from-cache (prod).

**L'import local (Phase 2 locale, `import_tennis.sh`) est SKIP par défaut** depuis le 2026-10-14 : Docker local est cassé sur cette machine (macOS 12.7.6 Monterey, incompatible avec la dernière version de Docker Desktop installable via `brew install --cask docker`, qui exige macOS Sonoma minimum — cf. tentative de réinstallation du 2026-10-14). Ça n'empêche pas le cycle d'aboutir : le sync + l'import prod (étapes 2 à 4 ci-dessous) ne dépendent pas de Docker local, seule la BDD locale (utile pour le dev/test en local) ne sera pas peuplée tant que Docker n'est pas réparé. Si Docker est réparé un jour, voir la section "Réactiver l'import local" en bas pour revenir au cycle complet.

## Instructions

### 0. Choix du tunnel de fetch (à demander AVANT de lancer l'étape 1)

Sofascore bloque les clients HTTP directs (403 IP ban) — le fetch doit passer par un vrai navigateur. Deux tunnels existent, demander à l'utilisateur lequel utiliser s'il ne l'a pas précisé :

- **`extension`** (défaut) — passe par l'extension Chrome `chrome_extension_sofascore` (voir `backend/script/chrome_extension_sofascore/`). Les fetch sont exécutés depuis un content script injecté dans un vrai onglet sofascore.com (pas depuis le service worker en arrière-plan — un fetch() lancé depuis le service worker a une signature de requête différente d'une navigation normale et se fait bloquer par l'anti-bot, cf. incident du 2026-09-29). **Prérequis : Chrome doit être ouvert avec l'extension chargée et connectée** (`chrome://extensions` → mode développeur → charger l'extension non empaquetée si pas déjà fait, ou recharger ↻ si le code de l'extension a changé) avant de lancer l'étape 1. Sans ça, le fetch bloque jusqu'à 120s puis échoue. Demander à l'utilisateur de confirmer que Chrome est prêt avant de lancer.
- **`selenium`** — Chrome headless piloté par Selenium (`fetch_sofascore_cache.py`), entièrement automatisé, ne nécessite aucune action manuelle. Plus lent à démarrer (warm-up de session) mais ne dépend pas d'un navigateur ouvert par l'utilisateur.

Une fois le choix fait, exporter `TENNIS_TRANSPORT=extension` ou `TENNIS_TRANSPORT=selenium` avant l'étape 1.

**Si le fetch échoue en boucle avec des `status=403 reason=challenge`** (sur les DEUX tunnels, extension et selenium) alors que l'utilisateur confirme avoir accès au site dans son propre navigateur : ce n'est PAS un ban IP (vérifié le 2026-09-29 : même IP bloquée pour le fetch automatisé mais site accessible manuellement) — c'est l'anti-bot qui détecte la signature de la requête automatisée elle-même. Vérifier que l'extension utilise bien le content script (voir `content_fetch.js` + relai via `chrome.tabs.sendMessage` dans `service_worker.js`), pas un fetch direct depuis le service worker. Si le blocage persiste malgré ça, demander à l'utilisateur de changer d'IP (VPN) en dernier recours.

### 1. Phase 1 : cache local (fetch + import-from-schedule)
```bash
cd "/Volumes/WORKSPACE/NEW BET TRACKER/backend"
TENNIS_FORCE=1 TENNIS_TRANSPORT=extension bash script/cache_tennis.sh 2>&1 | tail -20
# ou : TENNIS_TRANSPORT=selenium bash script/cache_tennis.sh
```

### 2. Sync + archive vers prod
```bash
cd "/Volumes/WORKSPACE/NEW BET TRACKER/backend"
bash script/send_cache_and_archive.sh 2>&1 | tail -5
```

### 3. Phase 2 prod : import-from-cache sur o2switch
```bash
ssh sc2vagr6376@bouteille.o2switch.net "cd ~/api.auxotracker && php artisan tennis:import-from-cache --force --download-images --download-logos 2>&1 | tail -15"
```

### 4. Recompute des scores "match serré" sur prod
```bash
ssh sc2vagr6376@bouteille.o2switch.net "cd ~/api.auxotracker && php artisan tennis:compute-tightness-scores --days=14 --force 2>&1 | tail -10"
```

### 5. Report
Show a summary: leagues, matches, players created/updated (prod), tightness scores computed (prod). Préciser que la BDD locale n'a pas été mise à jour (import local skip, Docker cassé).

**Important**: 
- Toujours demander/confirmer le tunnel (étape 0) avant de lancer l'étape 1 — ne pas supposer `extension` par défaut sans vérifier que Chrome est prêt.
- Ces scripts lisent uniquement depuis le cache — aucun appel à l'API Sofascore directement (contrainte prod).
- Le calcul des scores "match serré" (`tennis:compute-tightness-scores`) est indépendant de l'import : il faut le relancer explicitement après l'import (étape 4), pour prod uniquement tant que l'import local est skip.
- Le code PHP de prod (o2switch) n'est PAS redéployé par ce skill — seuls les fichiers de cache sont synchronisés (étape 2). Si des changements de code/migrations ont été faits en local (ex: nouvelles colonnes), ils ne sont pas automatiquement présents sur prod tant qu'un déploiement de code (`deploy-o2switch.sh`/`deploy-production.sh` + `php artisan migrate --force` sur prod) n'a pas été fait séparément.

## Réactiver l'import local (une fois Docker réparé)

Remettre l'étape suivante entre l'étape 1 (fetch) et l'étape 2 (sync), car le sync archive les fichiers cache :

```bash
cd "/Volumes/WORKSPACE/NEW BET TRACKER/backend"
bash script/import_tennis.sh
```

Ça recalcule aussi les scores "match serré" en local automatiquement (pas besoin de relancer l'étape 4 pour le local, uniquement pour prod).
