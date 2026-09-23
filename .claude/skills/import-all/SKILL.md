---
name: import-all
description: Importe tous les sports (football, tennis, basketball, baseball, futsal, handball, hockey, rugby, volleyball) — cache local, sync serveur, import serveur.
---

# Import tous les sports — Cycle complet

Lance le cycle complet pour tous les sports : fetch Chrome (local) → sync vers le serveur o2switch → import-from-cache sur le serveur. Aucun import n'est fait en local (pas de BDD locale accessible sans Docker).

## Instructions

### 1. Phase 1 : cache local pour tous les sports (fetch Chrome headless)
```bash
BACKEND="/Volumes/WORKSPACE/NEW BET TRACKER/backend"
cd "$BACKEND"

echo "⚽ Football cache..." && FOOTBALL_FORCE=1 bash script/cache_football.sh 2>&1 | tail -10
echo "🎾 Tennis cache..." && TENNIS_FORCE=1 bash script/cache_tennis.sh 2>&1 | tail -10
echo "🏀 Basketball cache..." && BASKETBALL_FORCE=1 bash script/cache_basketball.sh 2>&1 | tail -10
echo "⚾ Baseball cache..." && BASEBALL_FORCE=1 bash script/cache_baseball.sh 2>&1 | tail -10
echo "🏟️ Futsal cache..." && FUTSAL_FORCE=1 bash script/cache_futsal.sh 2>&1 | tail -10
echo "🤾 Handball cache..." && HANDBALL_FORCE=1 bash script/cache_handball.sh 2>&1 | tail -10
echo "🏒 Ice Hockey cache..." && ICE_HOCKEY_FORCE=1 bash script/cache_ice_hockey.sh 2>&1 | tail -10
echo "🏉 Rugby cache..." && RUGBY_FORCE=1 bash script/cache_rugby.sh 2>&1 | tail -10
echo "🏐 Volleyball cache..." && VOLLEYBALL_FORCE=1 bash script/cache_volleyball.sh 2>&1 | tail -10
```

### 2. Sync + archive vers le serveur (tous les sports d'un coup)
```bash
cd "/Volumes/WORKSPACE/NEW BET TRACKER/backend"
bash script/send_cache_and_archive.sh football_schedule tennis_leagues tennis_players tennis_schedule basketball_schedule baseball_schedule futsal_schedule handball_schedule ice_hockey_schedule rugby_schedule volleyball_schedule 2>&1 | tail -20
```

### 3. Import sur le serveur (Phase 2 : cache → BDD, par sport)
```bash
SSH="ssh sc2vagr6376@bouteille.o2switch.net"

echo "🎾 Import tennis..." && $SSH "cd ~/api.auxotracker && php artisan tennis:import-from-cache --force --download-images --download-logos" 2>&1 | tail -10
echo "⚽ Import football..." && $SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache football --force --import-teams --download-logos" 2>&1 | tail -10
echo "🏀 Import basketball..." && $SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache basketball --force --import-teams --download-logos" 2>&1 | tail -10
echo "⚾ Import baseball..." && $SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache baseball --force --import-teams --download-logos" 2>&1 | tail -10
echo "🏟️ Import futsal..." && $SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache futsal --force --import-teams --download-logos" 2>&1 | tail -10
echo "🤾 Import handball..." && $SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache handball --force --import-teams --download-logos" 2>&1 | tail -10
echo "🏒 Import ice hockey..." && $SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache ice-hockey --force --import-teams --download-logos" 2>&1 | tail -10
echo "🏉 Import rugby..." && $SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache rugby --force --import-teams --download-logos" 2>&1 | tail -10
echo "🏐 Import volleyball..." && $SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache volleyball --force --import-teams --download-logos" 2>&1 | tail -10
echo "✅ Tous les imports terminés"
```

When done, present a summary table:

| Sport | Statut | Ligues | Équipes | Erreurs |
|-------|--------|--------|---------|---------|
| ⚽ Football | ... | ... | ... | ... |
| 🎾 Tennis | ... | ... | ... | ... |
| 🏀 Basketball | ... | ... | ... | ... |
| ⚾ Baseball | ... | ... | ... | ... |
| 🏟️ Futsal | ... | ... | ... | ... |
| 🤾 Handball | ... | ... | ... | ... |
| 🏒 Hockey sur glace | ... | ... | ... | ... |
| 🏉 Rugby | ... | ... | ... | ... |
| 🏐 Volleyball | ... | ... | ... | ... |

**Important**:
- Aucun import n'est fait en local (pas de BDD locale accessible sans Docker) — tout se joue en Phase 2 sur le serveur.
- Tennis garde sa commande dédiée (`tennis:import-from-cache`) ; tous les autres sports passent par la commande générique `sport:import-from-cache {sport}`.
- Ces scripts lisent uniquement depuis le cache — aucun appel direct à l'API Sofascore en production (contrainte 403 déjà documentée).
