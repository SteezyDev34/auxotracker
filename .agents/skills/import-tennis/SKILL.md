---
name: import-tennis
description: Cycle complet d'import Tennis 🎾 — Phase 1 cache (local), sync prod, Phase 2 BDD (local + prod).
---

# Import Tennis 🎾 — Cycle complet

Lance le cycle complet : fetch Chrome → import-from-schedule (local) → sync prod → import-from-cache (local + prod).

## Instructions

Execute the following steps in order:

### 1. Phase 1 : cache local (fetch + import-from-schedule)
```bash
cd "/Volumes/WORKSPACE/NEW BET TRACKER/backend"
TENNIS_FORCE=1 bash script/cache_tennis.sh 2>&1 | tail -20
```

### 2. Phase 2 locale : import-from-cache (avant le sync qui archive)
```bash
cd "/Volumes/WORKSPACE/NEW BET TRACKER/backend"
bash script/import_tennis.sh
```

### 3. Sync + archive vers prod
```bash
cd "/Volumes/WORKSPACE/NEW BET TRACKER/backend"
bash script/send_cache_and_archive.sh 2>&1 | tail -5
```

### 4. Phase 2 prod : import-from-cache sur o2switch
```bash
ssh sc2vagr6376@bouteille.o2switch.net "cd ~/api.auxotracker && php artisan tennis:import-from-cache --force --download-images --download-logos 2>&1 | tail -15"
```

### 5. Recompute des scores "match serré" (local déjà fait par import_tennis.sh — refaire ici pour prod)
```bash
ssh sc2vagr6376@bouteille.o2switch.net "cd ~/api.auxotracker && php artisan tennis:compute-tightness-scores --days=14 --force 2>&1 | tail -10"
```

### 6. Report
Show a summary: leagues, matches, players created/updated (local and prod), tightness scores computed (local and prod).

**Important**: 
- L'import local (étape 2) doit tourner AVANT le sync (étape 3), car le sync archive les fichiers cache.
- Ces scripts lisent uniquement depuis le cache — aucun appel à l'API Sofascore directement (contrainte prod).
- Le calcul des scores "match serré" (`tennis:compute-tightness-scores`) est indépendant de l'import : `import_tennis.sh` le fait automatiquement en local (étape 2), mais côté prod il faut le relancer explicitement (étape 5) après l'import.
