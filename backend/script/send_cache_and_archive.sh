#!/usr/bin/env bash
set -euo pipefail

# Script: send_cache_and_archive.sh
# But: envoie le cache vers le serveur (via ljdsync ou SYNC_CMD),
#       restaure les marqueurs locaux, puis archive les données envoyées.
# Usage: ./script/send_cache_and_archive.sh [target1 target2 ...]
# Si aucun target fourni, le script détecte les répertoires top-level
# sous storage/app/sofascore_cache/ et les archive séparément.

PROJECT_DIR="${PROJECT_DIR:-/Volumes/WORKSPACE/NEW BET TRACKER/backend}"
CACHE_ROOT="${CACHE_ROOT:-$PROJECT_DIR/storage/app/sofascore_cache}"
LOCK_DIR="${LOCK_DIR:-$PROJECT_DIR/.locks}"
APP_SERVICE="${APP_SERVICE:-web}"
LOG_DIR="$PROJECT_DIR/script/logs"
mkdir -p "$LOG_DIR"
LOG="$LOG_DIR/send_cache_and_archive_$(date +%Y-%m-%d_%H-%M-%S).log"

usage() {
  cat <<EOF
Usage: $(basename "$0") [targets...]

targets : noms des sous-dossiers relatifs à storage/app/sofascore_cache à
          synchroniser et archiver (ex: tennis_players football basketball)
Si aucun target fourni, tous les top-level dirs sous sofascore_cache sont
traités individuellement (sauf 'archives').

Environnement:
  SYNC_CMD - commande custom pour synchroniser (ex: "rsync -avz ...").
             Si non défini, utilise `ljdsync`.
EOF
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
  usage
  exit 0
fi

echo "=== Envoi cache + archivage démarré à $(date) ===" 2>&1 | tee -a "$LOG"

cd "$PROJECT_DIR" || { echo "$(date) : ❌ Impossible de changer de dossier vers $PROJECT_DIR" 2>&1 | tee -a "$LOG"; exit 1; }

# Lock
mkdir -p "$LOCK_DIR"
LOCK="$LOCK_DIR/send_cache_and_archive.lock"
if ! mkdir "$LOCK" 2>/dev/null; then
    echo "$(date) : Script déjà en cours (verrou présent), sortie." 2>&1 | tee -a "$LOG"
    exit 0
fi
trap 'rm -rf "$LOCK"' EXIT

# Targets
if [ "$#" -gt 0 ]; then
    TARGETS=("$@")
else
    # lister les dossiers top-level sous sofascore_cache (exclure archives)
    if [ -d "$CACHE_ROOT" ]; then
        TARGETS=()
        for d in "$CACHE_ROOT"/*; do
            if [ -d "$d" ]; then
                name="$(basename "$d")"
                if [ "$name" != "archives" ]; then
                    TARGETS+=("$name")
                fi
            fi
        done
    else
        echo "$(date) : ❌ Répertoire de cache introuvable: $CACHE_ROOT" 2>&1 | tee -a "$LOG"
        exit 1
    fi
fi

if [ "${#TARGETS[@]}" -eq 0 ]; then
    echo "$(date) : Aucun target détecté, rien à faire." 2>&1 | tee -a "$LOG"
    exit 0
fi

echo "$(date) : Targets = ${TARGETS[*]}" 2>&1 | tee -a "$LOG"

# Exclure temporairement les fichiers marker avant envoi
MARKER_TMP_DIR="$LOCK_DIR/sofascore_markers_tmp_$(date +%s)"
mkdir -p "$MARKER_TMP_DIR"
echo "$(date) : 🔒 Déplacement temporaire des markers vers $MARKER_TMP_DIR" 2>&1 | tee -a "$LOG"

find "$CACHE_ROOT" -type f \( -name "*_CACHE_DONE_*" -o -name "*_LEAGUE_DONE_*" -o -name "IMPORT_DONE_*" -o -name ".synced_at" \) -print0 | \
while IFS= read -r -d '' file; do
    rel="${file#"$CACHE_ROOT/"}"
    destdir="$MARKER_TMP_DIR/$(dirname "$rel")"
    mkdir -p "$destdir"
    mv -f "$file" "$destdir/" 2>&1 | tee -a "$LOG" || true
done

# Exécuter la commande de sync (SYNC_CMD ou ljdsync)
echo "$(date) : 📤 Exécution de la commande de synchronisation" 2>&1 | tee -a "$LOG"
if [[ -n "${SYNC_CMD:-}" ]]; then
    echo "$(date) : Utilisation de SYNC_CMD='$SYNC_CMD'" 2>&1 | tee -a "$LOG"
    eval "$SYNC_CMD" 2>&1 | tee -a "$LOG"
    SYNC_EXIT=${PIPESTATUS[0]:-${?}}
else
    ljdsync 2>&1 | tee -a "$LOG"
    SYNC_EXIT=${PIPESTATUS[0]:-${?}}
fi

# Restaurer les markers
echo "$(date) : 🔓 Restauration des markers locaux depuis $MARKER_TMP_DIR" 2>&1 | tee -a "$LOG"
if [[ -d "$MARKER_TMP_DIR" ]]; then
    find "$MARKER_TMP_DIR" -type f -print0 | while IFS= read -r -d '' f; do
        rel="${f#"$MARKER_TMP_DIR/"}"
        destdir="$CACHE_ROOT/$(dirname "$rel")"
        mkdir -p "$destdir"
        mv -f "$f" "$destdir/" 2>&1 | tee -a "$LOG" || true
    done
    find "$MARKER_TMP_DIR" -depth -type d -exec rmdir {} \; 2>/dev/null || true
fi

if [[ $SYNC_EXIT -ne 0 ]]; then
    echo "$(date) : ❌ Synchronisation échouée (code=$SYNC_EXIT) — archivage annulé" 2>&1 | tee -a "$LOG"
    exit $SYNC_EXIT
fi

# Sur succès : créer .synced_at (epoch) et archiver les targets
date +%s > "$CACHE_ROOT/.synced_at" 2>/dev/null || true
echo "$(date) : ✅ Synchronisation réussie — marqueur .synced_at créé" 2>&1 | tee -a "$LOG"

TS="$(date +%Y-%m-%d_%H-%M-%S)"
for t in "${TARGETS[@]}"; do
    SRC="$CACHE_ROOT/$t"
    if [[ ! -d "$SRC" ]]; then
        echo "$(date) : ⚠️ Target introuvable, skip: $SRC" 2>&1 | tee -a "$LOG"
        continue
    fi

    ARCHIVE_DIR="$CACHE_ROOT/archives/$t/$TS"
    mkdir -p "$ARCHIVE_DIR"
    echo "$(date) : 🗄️ Archivage de $SRC → $ARCHIVE_DIR" 2>&1 | tee -a "$LOG"

    # Déplacer les enfants immédiats (fichiers et répertoires) sauf archives/processed
    find "$SRC" -mindepth 1 -maxdepth 1 ! -name 'archives' ! -name 'processed*' -print0 | \
    while IFS= read -r -d '' item; do
        mv -f "$item" "$ARCHIVE_DIR/" 2>&1 | tee -a "$LOG" || true
    done

    echo "$(date) : ✅ Archivage $t terminé (destination: $ARCHIVE_DIR)" 2>&1 | tee -a "$LOG"
done

echo "$(date) : 🎉 Envoi + archivage terminés" 2>&1 | tee -a "$LOG"
exit 0
