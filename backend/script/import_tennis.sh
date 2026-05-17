#!/bin/bash
set -euo pipefail

# Import Phase 2: cache → BDD pour le tennis
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
LOG="$LOG_DIR/import_tennis_$(date +%Y-%m-%d_%H-%M-%S).log"

echo "=== Import tennis exécuté à $(date) ===" 2>&1 | tee -a "$LOG"

cd "$PROJECT_DIR" || { echo "$(date) : ❌ Impossible de changer de dossier vers $PROJECT_DIR" 2>&1 | tee -a "$LOG"; exit 1; }

LOCK_DIR="$PROJECT_DIR/.locks"
mkdir -p "$LOCK_DIR"
LOCK="$LOCK_DIR/import_tennis.lock"
if ! mkdir "$LOCK" 2>/dev/null; then
    echo "$(date) : Script import_tennis déjà en cours (verrou), sortie." 2>&1 | tee -a "$LOG"
    exit 0
fi
trap 'rm -rf "$LOCK"' EXIT

# Options d'import (modifiable via variable d'environnement TENNIS_IMPORT_OPTS)
# --skip-archive : les fichiers player_basic_*.json sont conservés en place
# afin que rsync (Phase 3) puisse les envoyer au serveur. L'archivage réel
# est effectué côté serveur après l'import distant (Phase 5 Jenkins).
TENNIS_IMPORT_OPTS="${TENNIS_IMPORT_OPTS:---force --skip-archive}"

# Contrôle du téléchargement des images des joueurs
# Par défaut activé (1). Pour désactiver: TENNIS_DOWNLOAD_IMAGES=0
TENNIS_DOWNLOAD_IMAGES="${TENNIS_DOWNLOAD_IMAGES:-1}"
if [ "${TENNIS_DOWNLOAD_IMAGES}" != "0" ]; then
    TENNIS_IMPORT_OPTS="$TENNIS_IMPORT_OPTS --download-images"
fi

echo "$(date) : Exécution artisan tennis:import-from-cache $TENNIS_IMPORT_OPTS" 2>&1 | tee -a "$LOG"
# Exécuter la commande sans arrêter le script (set -e est actif)
set +e
$PHP_CMD artisan tennis:import-from-cache $TENNIS_IMPORT_OPTS 2>&1 | tee -a "$LOG"
RC=${PIPESTATUS[0]:-${?}}
set -e
if [[ $RC -eq 0 ]]; then
    echo "$(date) : ✅ Tennis import terminé" 2>&1 | tee -a "$LOG"
    # NOTE : pas d'archivage ici — le cache doit rester en place pour être
    # envoyé au serveur via rsync (Phase 3) avant que le serveur importe (Phase 4).
    # L'archivage est effectué côté serveur après l'import distant (Phase 5 Jenkins).
    echo "$(date) : 🎉 Import terminé" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Erreur lors de l'import Tennis (code=$RC)" 2>&1 | tee -a "$LOG"
    exit $RC
fi

exit 0
