#!/bin/bash
set -euo pipefail

# Import Phase 2: cache → BDD pour volleyball
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
LOG="$LOG_DIR/import_volleyball_$(date +%Y-%m-%d_%H-%M-%S).log"

echo "=== Import volleyball exécuté à $(date) ===" 2>&1 | tee -a "$LOG"

cd "$PROJECT_DIR" || { echo "$(date) : ❌ Impossible de changer de dossier vers $PROJECT_DIR" 2>&1 | tee -a "$LOG"; exit 1; }

LOCK_DIR="$PROJECT_DIR/.locks"
mkdir -p "$LOCK_DIR"
LOCK="$LOCK_DIR/import_volleyball.lock"
if ! mkdir "$LOCK" 2>/dev/null; then
    echo "$(date) : Script import_volleyball déjà en cours (verrou), sortie." 2>&1 | tee -a "$LOG"
    exit 0
fi
trap 'rm -rf "$LOCK"' EXIT

VOLLEYBALL_IMPORT_OPTS="${VOLLEYBALL_IMPORT_OPTS:---force --import-teams --download-logos}"

echo "$(date) : Exécution artisan volleyball:import-from-cache $VOLLEYBALL_IMPORT_OPTS" 2>&1 | tee -a "$LOG"
$PHP_CMD artisan volleyball:import-from-cache $VOLLEYBALL_IMPORT_OPTS 2>&1 | tee -a "$LOG"
if [[ $? -eq 0 ]]; then
    echo "$(date) : ✅ Volleyball import terminé" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Erreur lors de l'import Volleyball" 2>&1 | tee -a "$LOG"
    exit 1
fi

exit 0
