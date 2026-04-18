#!/bin/bash
set -euo pipefail

# Import Phase 2: cache → BDD pour futsal
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
LOG="$LOG_DIR/import_futsal_$(date +%Y-%m-%d_%H-%M-%S).log"

echo "=== Import futsal exécuté à $(date) ===" 2>&1 | tee -a "$LOG"

cd "$PROJECT_DIR" || { echo "$(date) : ❌ Impossible de changer de dossier vers $PROJECT_DIR" 2>&1 | tee -a "$LOG"; exit 1; }

LOCK_DIR="$PROJECT_DIR/.locks"
mkdir -p "$LOCK_DIR"
LOCK="$LOCK_DIR/import_futsal.lock"
if ! mkdir "$LOCK" 2>/dev/null; then
    echo "$(date) : Script import_futsal déjà en cours (verrou), sortie." 2>&1 | tee -a "$LOG"
    exit 0
fi
trap 'rm -rf "$LOCK"' EXIT

# Options d'import (modifiable via variable d'environnement FUTSAL_IMPORT_OPTS)
# Par défaut : forcer + importer les équipes + télécharger les logos
FUTSAL_IMPORT_OPTS="${FUTSAL_IMPORT_OPTS:---force --import-teams --download-logos}"

echo "$(date) : Exécution artisan futsal:import-from-cache $FUTSAL_IMPORT_OPTS" 2>&1 | tee -a "$LOG"
$PHP_CMD artisan futsal:import-from-cache $FUTSAL_IMPORT_OPTS 2>&1 | tee -a "$LOG"
if [[ $? -eq 0 ]]; then
    echo "$(date) : ✅ Futsal import terminé" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Erreur lors de l'import Futsal" 2>&1 | tee -a "$LOG"
    exit 1
fi

exit 0
