---
name: import-rugby
description: Cycle complet d'import Rugby 🏉 — Phase 1 cache (local), sync serveur, Phase 2 import (serveur o2switch).
---

# Import Rugby 🏉 — Cycle complet

Lance le cycle complet : fetch Chrome (local) → sync vers le serveur o2switch → import-from-cache sur le serveur (la BDD locale n'est pas utilisée, Docker n'étant pas actif sur ce poste).

## Instructions

Execute the following steps in order:

### 1. Phase 1 : cache local (fetch Chrome headless)
```bash
cd "/Volumes/WORKSPACE/NEW BET TRACKER/backend"
RUGBY_FORCE=1 bash script/cache_rugby.sh 2>&1 | tail -20
```

### 2. Sync + archive vers le serveur
```bash
cd "/Volumes/WORKSPACE/NEW BET TRACKER/backend"
bash script/send_cache_and_archive.sh rugby_schedule 2>&1 | tail -10
```

### 3. Import sur le serveur (Phase 2 : cache → BDD)
```bash
ssh sc2vagr6376@bouteille.o2switch.net "cd ~/api.auxotracker && php artisan sport:import-from-cache rugby --force --import-teams --download-logos 2>&1 | tail -30"
```

### 4. Report
Show a summary: leagues, teams and matches created/updated (from the server output).

**Important**:
- Aucun import n'est fait en local (pas de BDD locale accessible sans Docker) — tout se joue en Phase 2 sur le serveur.
- Ces scripts lisent uniquement depuis le cache — aucun appel direct à l'API Sofascore en production (contrainte 403 déjà documentée).
