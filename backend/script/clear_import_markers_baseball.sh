#!/usr/bin/env bash
set -euo pipefail

# Wrapper: clear_import_markers_baseball.sh
# Appelle clear_import_markers.sh en ciblant le sport "baseball"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MARKER_SCRIPT="$SCRIPT_DIR/clear_import_markers.sh"

if [[ ! -f "$MARKER_SCRIPT" ]]; then
  echo "Fichier introuvable: $MARKER_SCRIPT" >&2
  exit 2
fi

exec bash "$MARKER_SCRIPT" --sport baseball --all "$@"
