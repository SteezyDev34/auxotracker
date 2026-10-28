#!/bin/bash
set -euo pipefail

# Détection automatique du répertoire du projet (parent du dossier script)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="${PROJECT_DIR:-$(dirname "$SCRIPT_DIR")}"
APP_SERVICE="${APP_SERVICE:-web}"
PHP_CLI_OPTS="${PHP_CLI_OPTS:--d memory_limit=-1 -d max_execution_time=0}"

# Détection environnement : Docker ou PHP direct — `docker info` (pas juste
# `command -v docker`) car le binaire reste présent sur le PATH même quand le
# daemon/VM Docker Desktop est down (cf. incident 2026-10-14, macOS Monterey
# incompatible avec Docker Desktop récent) : `command -v` seul tombait à tort
# en mode Docker et plantait sur chaque appel `docker compose exec`.
if [[ -n "${USE_DOCKER:-}" ]] || { [[ -f "$PROJECT_DIR/docker-compose.yml" ]] && docker info >/dev/null 2>&1; }; then
    PHP_CMD="docker compose exec -T $APP_SERVICE php $PHP_CLI_OPTS"
    echo "Mode: Docker détecté"
elif command -v php >/dev/null 2>&1; then
    PHP_CMD="php"
    echo "Mode: PHP direct (serveur)"
else
    # Ni Docker ni PHP local (ex: machine sans environnement PHP/MySQL local,
    # juste Python + Chrome pour le fetch) — l'étape artisan locale ci-dessous
    # devient optionnelle : prod régénère de toute façon ses propres matchs du
    # jour via maybe_run_prod_sync() côté fetch_sofascore_cache.py, donc rien
    # n'est perdu, seule la BDD locale (si elle existe) ne sera pas alimentée.
    PHP_CMD=""
    echo "Mode: ni Docker ni PHP local disponibles — étape artisan locale sautée (prod s'en charge)"
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

# Date cible du run, calculée UNE SEULE FOIS ici (pas recalculée plus loin, ni côté
# artisan) — TENNIS_FETCH_DATE (absolue, YYYY-MM-DD) est prioritaire sur
# TENNIS_DATE_OFFSET (relatif à "maintenant"). Nécessaire pour un enchaînement
# multi-jours : incident 2026-10-18/19, un fetch de 8h a fait franchir minuit en
# plein run, et un --date-offset recalculé à chaque étape (ici ET côté artisan,
# exécuté des heures plus tard) a glissé d'un jour entier par rapport à la date
# réellement visée au lancement — un enchaînement offset=0/1/2 a fini par couvrir
# J+1/J+2/J+3 au lieu de J/J+1/J+2, sans jamais traiter le jour courant.
if [ -n "${TENNIS_FETCH_DATE:-}" ]; then
    FETCH_DATE="$TENNIS_FETCH_DATE"
else
    FETCH_DATE="${TENNIS_DATE_OFFSET:+$(date -v+${TENNIS_DATE_OFFSET}d +%Y-%m-%d 2>/dev/null || date +%Y-%m-%d)}"
    FETCH_DATE="${FETCH_DATE:-$(date +%Y-%m-%d)}"
fi

TENNIS_CACHE_MARKER="$PROJECT_DIR/storage/app/sofascore_cache/tennis_CACHE_DONE_${FETCH_DATE}"

if [[ "${TENNIS_FORCE:-}" != "1" ]]; then
    if [[ -f "$TENNIS_CACHE_MARKER" ]]; then
        echo "$(date) : ⏭️ Tennis Phase 1 déjà faite pour ${FETCH_DATE} (marker présent). Skip." 2>&1 | tee -a "$LOG"
        exit 0
    fi
else
    echo "$(date) : ⚠️ Tennis force demandé (TENNIS_FORCE=1) — exécution Phase 1 pour ${FETCH_DATE}" 2>&1 | tee -a "$LOG"
fi

# Construire les paramètres pour tennis:cache-players (configurable via variables d'environnement)
TEN_PARAMS=""
if [ "${TENNIS_FORCE:-}" = "1" ]; then TEN_PARAMS="--force"; fi
[ -z "${TENNIS_DOWNLOAD_IMAGES:-}" ] && TENNIS_DOWNLOAD_IMAGES="--download-images"
[ -n "${TENNIS_DOWNLOAD_IMAGES:-}" ] && TEN_PARAMS="$TEN_PARAMS ${TENNIS_DOWNLOAD_IMAGES}"
[ -n "${TENNIS_LIMIT:-}" ] && TEN_PARAMS="$TEN_PARAMS --limit=${TENNIS_LIMIT}"
[ -n "${TENNIS_DELAY:-}" ] && TEN_PARAMS="$TEN_PARAMS --delay=${TENNIS_DELAY}"
# --date (absolue) plutôt que --date-offset côté artisan : évite que PHP, exécuté
# potentiellement des heures après le début du fetch, recalcule "aujourd'hui" à un
# moment différent (voir commentaire FETCH_DATE ci-dessus).
TEN_PARAMS="$TEN_PARAMS --date=${FETCH_DATE}"

# Phase 0 : fetch des données Sofascore (contourne le ban IP/TLS)
echo "$(date) : Fetch Sofascore cache via $TENNIS_TRANSPORT..." 2>&1 | tee -a "$LOG"

# Retry automatique (jusqu'à 2 tentatives supplémentaires) si le script Python
# sort avec le code 42 : ça signifie que Chrome a crashé en cours de route
# (session morte) — une nouvelle session Chrome fraîche repart du cache déjà
# écrit sur disque (pas de perte), plutôt que de laisser tourner un process
# qui boucle pendant des heures sans plus rien récupérer.
CRASH_RETRIES=0
MAX_CRASH_RETRIES=2
while true; do
    # Avec pipefail, le statut du pipeline est celui de python (tee réussit) :
    # le "|| FETCH_EXIT=$?" le capture sans déclencher set -e. L'ancien
    # "|| true" puis ${PIPESTATUS[0]} lisait le statut de `true` (toujours 0) :
    # un fetch en échec (ex. extension non connectée) passait pour réussi.
    FETCH_EXIT=0
    /usr/bin/python3 -u "$SCRIPT_DIR/fetch_sofascore_cache.py" --sport tennis --date "$FETCH_DATE" --transport "${TENNIS_TRANSPORT:-extension}" 2>&1 | tee -a "$LOG" || FETCH_EXIT=$?
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
    # Sortie propre : enchaîner import-from-schedule sans --offline lancerait des
    # appels Sofascore directs depuis PHP (bloqués, caches négatifs) et pourrait
    # écrire le marker "Phase 1 faite" à tort. Le cache déjà écrit est conservé.
    echo "$(date) : ❌ Fetch échoué (code $FETCH_EXIT) — arrêt sans import ni marker. Relancer après correction (extension connectée, etc.)." 2>&1 | tee -a "$LOG"
    exit "$FETCH_EXIT"
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

if [[ -z "$PHP_CMD" ]]; then
    echo "$(date) : ⏭️  Pas de PHP/Docker local — artisan tennis:import-from-schedule sauté (prod s'en charge)" 2>&1 | tee -a "$LOG"
    printf "%s" "done" > "$TENNIS_CACHE_MARKER" 2>/dev/null || true
    echo "$(date) : ✅ Tennis Phase 1 (cache) terminée" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : Exécution artisan tennis:import-from-schedule $TEN_PARAMS" 2>&1 | tee -a "$LOG"
    $PHP_CMD artisan tennis:import-from-schedule $TEN_PARAMS 2>&1 | tee -a "$LOG"
    if [[ $? -eq 0 ]]; then
        printf "%s" "done" > "$TENNIS_CACHE_MARKER" 2>/dev/null || true
        echo "$(date) : ✅ Tennis Phase 1 (cache) terminée" 2>&1 | tee -a "$LOG"
    else
        echo "$(date) : ❌ Erreur lors du cache Tennis Phase 1" 2>&1 | tee -a "$LOG"
        exit 1
    fi
fi

exit 0
