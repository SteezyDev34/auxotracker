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
[ -n "${TENNIS_DATE_OFFSET:-}" ] && TEN_PARAMS="$TEN_PARAMS --date-offset=${TENNIS_DATE_OFFSET}"

# Phase 0 : fetch des données Sofascore via Chrome headless (contourne le ban IP/TLS)
echo "$(date) : Fetch Sofascore cache via Chrome headless..." 2>&1 | tee -a "$LOG"
FETCH_DATE="${TENNIS_DATE_OFFSET:+$(date -v+${TENNIS_DATE_OFFSET}d +%Y-%m-%d 2>/dev/null || date +%Y-%m-%d)}"
FETCH_DATE="${FETCH_DATE:-$(date +%Y-%m-%d)}"

# Retry automatique (jusqu'à 2 tentatives supplémentaires) si le script Python
# sort avec le code 42 : ça signifie que Chrome a crashé en cours de route
# (session morte) — une nouvelle session Chrome fraîche repart du cache déjà
# écrit sur disque (pas de perte), plutôt que de laisser tourner un process
# qui boucle pendant des heures sans plus rien récupérer.
CRASH_RETRIES=0
MAX_CRASH_RETRIES=2
while true; do
    /usr/bin/python3 -u "$SCRIPT_DIR/fetch_sofascore_cache.py" --sport tennis --date "$FETCH_DATE" 2>&1 | tee -a "$LOG" || true
    FETCH_EXIT=${PIPESTATUS[0]:-${?}}
    if [[ "$FETCH_EXIT" -eq 42 && "$CRASH_RETRIES" -lt "$MAX_CRASH_RETRIES" ]]; then
        CRASH_RETRIES=$((CRASH_RETRIES + 1))
        echo "$(date) : 💥 Chrome a crashé — relance automatique (tentative $CRASH_RETRIES/$MAX_CRASH_RETRIES)" 2>&1 | tee -a "$LOG"
        pkill -f chromedriver 2>/dev/null || true
        sleep 2
        continue
    fi
    break
done

if [[ "$FETCH_EXIT" -ne 0 ]]; then
    echo "$(date) : ⚠️  Fetch Chrome échoué, tentative sans --offline" 2>&1 | tee -a "$LOG"
else
    # Le fetch Python a réussi : on passe en mode --offline et on retire --force
    # (en --offline --force, PHP ignorerait les caches Python et créerait des caches négatifs)
    # On supprime les markers de tournois pour que artisan retraite sans --force
    CACHE_DIR="$PROJECT_DIR/storage/app/sofascore_cache"
    find "$CACHE_DIR" -name "tennis_LEAGUE_DONE_${FETCH_DATE}_*" -delete 2>/dev/null || true
    echo "$(date) : Markers de tournois supprimés pour ${FETCH_DATE}" 2>&1 | tee -a "$LOG"
    TEN_PARAMS="${TEN_PARAMS//--force/} --offline"
    TEN_PARAMS="$(echo "$TEN_PARAMS" | xargs)"  # trim whitespace
    echo "$(date) : Fetch Chrome OK — artisan lancé en mode --offline (sans --force)" 2>&1 | tee -a "$LOG"
fi

echo "$(date) : Exécution artisan tennis:import-from-schedule $TEN_PARAMS" 2>&1 | tee -a "$LOG"
$PHP_CMD artisan tennis:import-from-schedule $TEN_PARAMS 2>&1 | tee -a "$LOG"
if [[ $? -eq 0 ]]; then
    printf "%s" "done" > "$TENNIS_CACHE_MARKER" 2>/dev/null || true
    echo "$(date) : ✅ Tennis Phase 1 (cache) terminée" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Erreur lors du cache Tennis Phase 1" 2>&1 | tee -a "$LOG"
    exit 1
fi

exit 0
