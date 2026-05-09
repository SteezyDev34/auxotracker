#!/usr/bin/env bash
set -euo pipefail

# Archive sofaScore cache targets into timestamped folders.
# Usage: archive_sofascore_cache.sh [target1 target2 ...]
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="${PROJECT_DIR:-$(dirname "$SCRIPT_DIR") }"
CACHE_ROOT="${CACHE_ROOT:-$PROJECT_DIR/storage/app/sofascore_cache}"
TS="$(date +%Y-%m-%d_%H-%M-%S)"

if [ "$#" -gt 0 ]; then
  TARGETS=("$@")
else
  if [ -d "$CACHE_ROOT" ]; then
    TARGETS=()
    for d in "$CACHE_ROOT"/*; do
      [ -d "$d" ] || continue
      name="$(basename "$d")"
      [ "$name" != "archives" ] && TARGETS+=("$name")
    done
  else
    echo "Cache root not found: $CACHE_ROOT" >&2
    exit 1
  fi
fi

for t in "${TARGETS[@]}"; do
  SRC="$CACHE_ROOT/$t"
  if [ ! -d "$SRC" ]; then
    echo "Skip missing target: $SRC"
    continue
  fi
  ARCHIVE_DIR="$CACHE_ROOT/archives/$t/$TS"
  mkdir -p "$ARCHIVE_DIR"
  echo "Archiving $SRC -> $ARCHIVE_DIR"
  find "$SRC" -mindepth 1 -maxdepth 1 ! -name 'archives' ! -name 'processed*' -print0 | \
  while IFS= read -r -d '' item; do
    mv -f "$item" "$ARCHIVE_DIR/" || true
  done
  echo "Archived $t"
done

exit 0
