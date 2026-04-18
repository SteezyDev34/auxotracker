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
LOG="$LOG_DIR/cache_rugby_$(date +%Y-%m-%d_%H-%M-%S).log"
echo "=== Cache rugby exécuté à $(date) ===" 2>&1 | tee -a "$LOG"

cd "$PROJECT_DIR" || { echo "$(date) : ❌ Impossible de changer de dossier vers $PROJECT_DIR" 2>&1 | tee -a "$LOG"; exit 1; }

LOCK_DIR="$PROJECT_DIR/.locks"
mkdir -p "$LOCK_DIR"
LOCK="$LOCK_DIR/cache_rugby.lock"
if ! mkdir "$LOCK" 2>/dev/null; then
    echo "$(date) : Script cache_rugby déjà en cours (verrou), sortie." 2>&1 | tee -a "$LOG"
    exit 0
fi
trap 'rm -rf "$LOCK"' EXIT

RUGBY_CACHE_MARKER="$PROJECT_DIR/storage/app/sofascore_cache/rugby_CACHE_DONE_$(date +%Y-%m-%d)"
RUGBY_LEAGUE_PATTERN="$PROJECT_DIR/storage/app/sofascore_cache/rugby_LEAGUE_DONE_$(date +%Y-%m-%d)_*"

if [[ "${RUGBY_FORCE:-}" != "1" ]]; then
    if [[ -f "$RUGBY_CACHE_MARKER" ]] || compgen -G "$RUGBY_LEAGUE_PATTERN" >/dev/null; then
        echo "$(date) : ⏭️  Rugby Phase 1 déjà faite (marker présent). Skip API." 2>&1 | tee -a "$LOG"
        exit 0
    fi
else
    echo "$(date) : ⚠️ Rugby force demandé (RUGBY_FORCE=1) — exécution Phase 1" 2>&1 | tee -a "$LOG"
fi

echo "$(date) : Exécution artisan rugby:import-from-schedule --import-teams --download-logos" 2>&1 | tee -a "$LOG"
$PHP_CMD artisan rugby:import-from-schedule --import-teams --download-logos 2>&1 | tee -a "$LOG"
if [[ $? -eq 0 ]]; then
    printf "%s" "done" > "$RUGBY_CACHE_MARKER" 2>/dev/null || true
    echo "$(date) : ✅ Rugby Phase 1 (cache) terminée" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Erreur lors du cache Rugby Phase 1" 2>&1 | tee -a "$LOG"
    exit 1
fi

exit 0
