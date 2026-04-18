#!/bin/bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/Volumes/WORKSPACE/NEW BET TRACKER/backend}"
CACHE_DIR="$PROJECT_DIR/storage/app/sofascore_cache"
LOG_DIR="$PROJECT_DIR/script/logs"
LOCK_DIR="$PROJECT_DIR/.locks"

printf '=== Rapport de stats — %s ===\n\n' "$(date '+%Y-%m-%d %H:%M:%S')"

printf 'Cache racine: %s\n\n' "$CACHE_DIR"

if [[ -d "$CACHE_DIR" ]]; then
  for sportdir in "$CACHE_DIR"/*; do
    if [[ -d "$sportdir" ]]; then
      sport=$(basename "$sportdir")
      files=$(find "$sportdir" -type f 2>/dev/null | wc -l)
      size=$(du -sh "$sportdir" 2>/dev/null | awk '{print $1}')
      markers_count=$(find "$sportdir" -type f \( -name '*_CACHE_DONE_*' -o -name '*_LEAGUE_DONE_*' -o -name 'IMPORT_DONE_*' \) 2>/dev/null | wc -l)
      last_marker=$(find "$sportdir" -type f \( -name '*_CACHE_DONE_*' -o -name '*_LEAGUE_DONE_*' -o -name 'IMPORT_DONE_*' \) -print0 2>/dev/null | xargs -0 ls -1t 2>/dev/null | head -n1 || echo '(aucun)')
      printf 'Sport: %s\n - path: %s\n - files: %s\n - size: %s\n - markers: %s\n - last marker: %s\n\n' "$sport" "$sportdir" "$files" "$size" "$markers_count" "$last_marker"
    fi
  done
else
  echo "Aucun dossier de cache trouvé: $CACHE_DIR"
fi

printf 'Logs (dernier 10):\n'
if [[ -d "$LOG_DIR" ]]; then
  ls -1t "$LOG_DIR" | head -n10 | sed 's/^/ - /'
else
  echo ' - Aucun répertoire de logs'
fi

printf '\nLocks (%s):\n' "$LOCK_DIR"
if [[ -d "$LOCK_DIR" ]]; then
  ls -la "$LOCK_DIR" | sed 's/^/ - /'
else
  echo ' - Aucun dossier de verrous'
fi

printf '\nMarkers globaux (extraits, up to 100):\n'
if [[ -d "$CACHE_DIR" ]]; then
  find "$CACHE_DIR" -maxdepth 2 -type f \( -name '*_CACHE_DONE_*' -o -name '*_LEAGUE_DONE_*' -o -name 'IMPORT_DONE_*' \) -print 2>/dev/null | sed 's/^/ - /' | head -n100 || true
else
  echo ' - Aucun'
fi

printf '\nFin du rapport\n'

exit 0
