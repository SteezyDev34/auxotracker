#!/usr/bin/env bash
set -euo pipefail

# clear_import_markers.sh
# Liste et supprime les fichiers marker d'import (IMPORT_DONE_*)
# Usage: clear_import_markers.sh [--dry-run|-n] [--all] [--yes|-y]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
MARKER_DIR="$PROJECT_DIR/storage/app/sofascore_cache"

usage() {
  cat <<'USAGE'
Usage: clear_import_markers.sh [--dry-run|-n] [--all] [--yes|-y] [-h|--help]

Options:
  --dry-run, -n   : Lister les markers sans les supprimer
  --all           : Inclure les markers CACHE_DONE_* en plus des IMPORT_DONE_*
  --sport, -s     : Limiter la suppression au sport spécifié (ex: football, basketball, tennis)
  --yes, -y       : Ne pas demander de confirmation (force)
  -h, --help      : Afficher cette aide
USAGE
}

DRY_RUN=0
INCLUDE_CACHE=1
FORCE=1
SPORT=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run|-n) DRY_RUN=1; shift ;;
    --all) INCLUDE_CACHE=1; shift ;;
    --sport|-s)
      shift
      if [[ $# -eq 0 ]]; then
        echo "Option --sport nécessite un argument"
        usage
        exit 2
      fi
      SPORT="$1"
      shift
      ;;
    --yes|-y) FORCE=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Option inconnue: $1"; usage; exit 2 ;;
  esac
done

if [[ ! -d "$MARKER_DIR" ]]; then
  echo "Répertoire de markers introuvable : $MARKER_DIR"
  exit 0
fi

# Construire l'expression find (recherche récursive pour inclure les markers créés sous-dossiers)
if [[ $INCLUDE_CACHE -eq 1 ]]; then
  # IMPORT_DONE_*, CACHE_DONE_*, marqueurs par ligue, caches négatifs de logos et tombstones images
  FIND_EXPR=( -name '*IMPORT_DONE_*' -o -name '*CACHE_DONE_*' -o -name '*LEAGUE_DONE_*' -o -path '*/logo_negative/*' -o -name 'player_image_*.meta' )
  PATTERN_DESC='IMPORT_DONE_*, CACHE_DONE_*, LEAGUE_DONE_*, logo_negative/* et player_image_*.meta'
else
  FIND_EXPR=( -name '*IMPORT_DONE_*' -o -name '*LEAGUE_DONE_*' -o -path '*/logo_negative/*' -o -name 'player_image_*.meta' )
  PATTERN_DESC='IMPORT_DONE_*, LEAGUE_DONE_*, logo_negative/* et player_image_*.meta'
fi

# Récupérer la liste (nul-delimited pour gérer les espaces)
markers=()

# Utiliser un fichier temporaire plutôt que la substitution de processus (< <(...))
# afin d'éviter les erreurs sur des environnements sans /dev/fd (ex: appel via sh)
tmpfile="$(mktemp)"
trap 'rm -f "$tmpfile"' EXIT

if [[ -n "$SPORT" ]]; then
  # Filtrer par sport: noms de fichiers contenant le sport OU chemins contenant des dossiers typiques
  SPORT_EXPR=( -iname "*${SPORT}*" -o -path "*/${SPORT}/*" -o -path "*/leagues_${SPORT}/*" -o -path "*/${SPORT}_schedule/*" -o -name "${SPORT}_*" )
  find "$MARKER_DIR" -type f \( "${FIND_EXPR[@]}" \) -a \( "${SPORT_EXPR[@]}" \) -print0 2>/dev/null > "$tmpfile" || true
  while IFS= read -r -d '' f; do
    markers+=("$f")
  done < "$tmpfile"
  PATTERN_DESC="$PATTERN_DESC (filtré: $SPORT)"
else
  find "$MARKER_DIR" -type f \( "${FIND_EXPR[@]}" \) -print0 2>/dev/null > "$tmpfile" || true
  while IFS= read -r -d '' f; do
    markers+=("$f")
  done < "$tmpfile"
fi

if [[ ${#markers[@]} -eq 0 ]]; then
  echo "Aucun marker trouvé (pattern: $PATTERN_DESC) dans $MARKER_DIR"
  exit 0
fi

echo "Markers trouvés: ${#markers[@]}"
for f in "${markers[@]}"; do
  printf " - %s\n" "$f"
done

if [[ $DRY_RUN -eq 1 ]]; then
  echo "Mode dry-run : aucun fichier supprimé."
  exit 0
fi

if [[ $FORCE -ne 1 ]]; then
  read -rp $'Confirmer la suppression de ces fichiers ? [y/N] ' ans
  if [[ ! "$ans" =~ ^[Yy] ]]; then
    echo "Abandon. Aucun fichier supprimé."
    exit 0
  fi
fi

deleted=0
errors=0
for f in "${markers[@]}"; do
  if rm -f -- "$f"; then
    echo "Supprimé : $f"
    deleted=$((deleted+1))
  else
    echo "Échec suppression : $f" >&2
    errors=$((errors+1))
  fi
done

echo "Terminé. Supprimés: $deleted. Erreurs: $errors."
exit 0
