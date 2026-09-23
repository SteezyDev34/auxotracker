---
name: sync-cache
description: Synchronise le cache Sofascore local vers le serveur de production via ljdsync, puis archive les fichiers envoyés.
---

# Sync cache vers le serveur

Envoie les fichiers cache locaux vers le serveur de production via `ljdsync`, en excluant les markers (pour ne pas bloquer les imports serveur), puis archive les données envoyées localement.

## Instructions

Run the sync script. If specific sport directories are provided as arguments, pass them to the script; otherwise sync everything.

```bash
BACKEND="/Volumes/WORKSPACE/NEW BET TRACKER/backend"
cd "$BACKEND"

# Si des sports sont précisés en argument, les passer au script
# Sinon, sync tous les répertoires détectés automatiquement
bash script/send_cache_and_archive.sh 2>&1 | tail -20
```

Report:
- Whether ljdsync succeeded or failed
- Which directories were synced and archived
- Any errors from the script

**Important**: Ce script exclut automatiquement les markers locaux (`*_CACHE_DONE_*`, `IMPORT_DONE_*`) avant le sync pour ne pas polluer le serveur, puis les restaure ensuite.
