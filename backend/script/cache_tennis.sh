#!/bin/bash
set -euo pipefail

# Détection automatique du répertoire du projet (parent du dossier script)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="${PROJECT_DIR:-$(dirname "$SCRIPT_DIR")}"
APP_SERVICE="${APP_SERVICE:-web}"
PHP_CLI_OPTS="${PHP_CLI_OPTS:--d memory_limit=-1 -d max_execution_time=0}"

# Détection environnement : Docker ou PHP direct
if [[ -n "${USE_DOCKER:-}" ]] || { [[ -f "$PROJECT_DIR/docker-compose.yml" ]] && command -v docker >/dev/null 2>&1; }; then
    PHP_CMD="docker compose exec -T $APP_SERVICE php $PHP_CLI_OPTS"
    echo "Mode: Docker détecté"
else
    PHP_CMD="php"
    echo "Mode: PHP direct (serveur)"
fi
LOG_DIR="$PROJECT_DIR/script/logs"
mkdir -p "$LOG_DIR"
LOG="$LOG_DIR/cache_tennis_$(date +%Y-%m-%d_%H-%M-%S).log"
echo "=== Cache tennis exécuté à $(date) ===" 2>&1 | tee -a "$LOG"

cd "$PROJECT_DIR" || { echo "$(date) : ❌ Impossible de changer de dossier vers $PROJECT_DIR" 2>&1 | tee -a "$LOG"; exit 1; }

LOCK_DIR="$PROJECT_DIR/.locks"
mkdir -p "$LOCK_DIR"
LOCK="$LOCK_DIR/cache_tennis.lock"
if ! mkdir "$LOCK" 2>/dev/null; then
    echo "$(date) : Script cache_tennis déjà en cours (verrou), sortie." 2>&1 | tee -a "$LOG"
    exit 0
fi
trap 'rm -rf "$LOCK"' EXIT

TENNIS_CACHE_MARKER="$PROJECT_DIR/storage/app/sofascore_cache/tennis_CACHE_DONE_$(date +%Y-%m-%d)"

if [[ "${TENNIS_FORCE:-}" != "1" ]]; then
    if [[ -f "$TENNIS_CACHE_MARKER" ]]; then
        echo "$(date) : ⏭️ Tennis Phase 1 déjà faite (marker présent). Skip." 2>&1 | tee -a "$LOG"
        exit 0
    fi
else
    echo "$(date) : ⚠️ Tennis force demandé (TENNIS_FORCE=1) — exécution Phase 1" 2>&1 | tee -a "$LOG"
fi

# Construire les paramètres pour tennis:cache-players (configurable via variables d'environnement)
TEN_PARAMS=""
if [ "${TENNIS_FORCE:-}" = "1" ]; then TEN_PARAMS="--force"; fi
[ -z "${TENNIS_DOWNLOAD_IMAGES:-}" ] && TENNIS_DOWNLOAD_IMAGES="--download-images"
[ -n "${TENNIS_DOWNLOAD_IMAGES:-}" ] && TEN_PARAMS="$TEN_PARAMS ${TENNIS_DOWNLOAD_IMAGES}"
[ -n "${TENNIS_LIMIT:-}" ] && TEN_PARAMS="$TEN_PARAMS --limit=${TENNIS_LIMIT}"
[ -n "${TENNIS_DELAY:-}" ] && TEN_PARAMS="$TEN_PARAMS --delay=${TENNIS_DELAY}"

echo "$(date) : Exécution artisan tennis:cache-players $TEN_PARAMS" 2>&1 | tee -a "$LOG"
$PHP_CMD artisan tennis:cache-players $TEN_PARAMS 2>&1 | tee -a "$LOG"
if [[ $? -eq 0 ]]; then
    printf "%s" "done" > "$TENNIS_CACHE_MARKER" 2>/dev/null || true
    echo "$(date) : ✅ Tennis Phase 1 (cache) terminée" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Erreur lors du cache Tennis Phase 1" 2>&1 | tee -a "$LOG"
    exit 1
fi

exit 0
