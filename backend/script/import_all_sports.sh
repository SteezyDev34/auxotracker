#!/bin/bash
#
# Script pour :
#  - Vérifier que Docker tourne, le démarrer sinon
#  - S'assurer que les conteneurs mariadb + nginx + projet Laravel sont lancés
#  - Exécuter les commandes Laravel
#  - Exécuter ljdsync
#  - Arrêter le conteneur du site Laravel
#  - Tout logger dans un fichier de log

# --- Configuration pour compatibilité cron ---
export PATH="/usr/local/bin:/usr/bin:/bin:/opt/homebrew/bin:$PATH"
export HOME="/Users/steeven"
export USER="steeven"

# Charger le profil utilisateur si disponible
if [ -f "$HOME/.zshrc" ]; then
    source "$HOME/.zshrc" 2>/dev/null || true
fi
if [ -f "$HOME/.bash_profile" ]; then
    source "$HOME/.bash_profile" 2>/dev/null || true
fi

# --- Paramètres ---
# Détection automatique du répertoire du projet (parent du dossier script)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="${PROJECT_DIR:-$(dirname "$SCRIPT_DIR")}"
SITE_CONTAINER="api.auxotracker"   # Conteneur Laravel
DB_CONTAINER="mariadb"             # Conteneur MariaDB
NGINX_CONTAINER="nginx-proxy"      # Conteneur Nginx
APP_SERVICE="web"                  # Nom du service applicatif dans docker-compose (utilisé pour docker compose exec)

# Options configurables (prévenir les runs trop longs)
# Pour accélérer une exécution cron, vous pouvez définir ces variables
# - TENNIS_DOWNLOAD_IMAGES : définir vide pour NE PAS télécharger les images
# - TENNIS_LIMIT : limiter le nombre de joueurs traités par exécution
# - TENNIS_DELAY : délai entre requêtes API (par défaut 1)
TENNIS_DOWNLOAD_IMAGES="${TENNIS_DOWNLOAD_IMAGES:---download-images}"
TENNIS_LIMIT="${TENNIS_LIMIT:-}"
TENNIS_DELAY="${TENNIS_DELAY:-1}"

# Optional: commande de synchronisation à exécuter après création du sentinel.
# Exemple: export SYNC_CMD="rsync -avz storage/app/sofascore_cache/ user@server:/path/to/project/storage/app/sofascore_cache/"

# Options PHP CLI pour permettre une exécution longue en CLI (mémoire / timeout)
PHP_CLI_OPTS="${PHP_CLI_OPTS:--d memory_limit=-1 -d max_execution_time=0}"

# Contrôle si on doit envoyer les fichiers "marker" (IMPORT_DONE_*, *_LEAGUE_DONE_*, *_CACHE_DONE_*)
# VALEUR PAR DÉFAUT: 0 — NE JAMAIS ENVOYER LES MARKERS AU SERVEUR.
# Pour forcer l'envoi (uniquement si vous comprenez les risques), exportez
# SEND_MARKERS=1 avant d'exécuter le script.
SEND_MARKERS="${SEND_MARKERS:-0}"

LOG="$PROJECT_DIR/script/logs/tennis_cache_sync_local_$(date +%Y-%m-%d_%H-%M-%S).log"

# --- Timestamp pour le log ---
echo "=== Cron exécuté à $(date) ===" 2>&1 | tee -a "$LOG"

# --- Se placer dans le bon dossier Laravel ---
cd "$PROJECT_DIR" || {
    echo "$(date) : ❌ Impossible de changer de dossier vers $PROJECT_DIR" 2>&1 | tee -a "$LOG"
    exit 1
}

# --- Verrou pour éviter exécutions concurrentes ---
LOCK_DIR="$PROJECT_DIR/.locks"
mkdir -p "$LOCK_DIR"
LOCK="$LOCK_DIR/tennis_cache_sync.lock"
if ! mkdir "$LOCK" 2>/dev/null; then
    echo "$(date) : Script déjà en cours d'exécution (verrou existant), sortie." 2>&1 | tee -a "$LOG"
    exit 0
fi
trap 'rm -rf "$LOCK"' EXIT

# --- Vérifier/relancer Docker Desktop ---
echo "$(date) : Vérification de l'état de Docker..." 2>&1 | tee -a "$LOG"

# Vérifier si Docker est déjà en cours d'exécution
if ! docker info >/dev/null 2>&1; then
    echo "$(date) : Docker n'est pas actif, tentative de démarrage..." 2>&1 | tee -a "$LOG"

    # Vérifier si Orbstack est installé
    if [ ! -d "/Applications/Orbstack.app" ]; then
        echo "$(date) : ❌ Orbstack n'est pas installé dans /Applications/" 2>&1 | tee -a "$LOG"
        exit 1
    fi

    # Démarrer Orbstack
    orb start 2>&1 | tee -a "$LOG"
    echo "$(date) : Orbstack lancé, attente de la disponibilité..." 2>&1 | tee -a "$LOG"

    # Attendre Docker (max 5 minutes)
    MAX_WAIT=300
    WAIT_TIME=0
    SLEEP_INTERVAL=5
    while ! docker info >/dev/null 2>&1; do
        sleep $SLEEP_INTERVAL
        WAIT_TIME=$((WAIT_TIME+SLEEP_INTERVAL))

        # Afficher le progrès toutes les minutes
        if [ $((WAIT_TIME % 60)) -eq 0 ]; then
            echo "$(date) : Attente Docker... (${WAIT_TIME}s écoulées)" 2>&1 | tee -a "$LOG"
        fi

        if [ $WAIT_TIME -ge $MAX_WAIT ]; then
            echo "$(date) : ❌ Docker ne répond pas après ${MAX_WAIT}s, abandon." 2>&1 | tee -a "$LOG"
            echo "$(date) : Vérifiez que Docker Desktop peut démarrer manuellement." 2>&1 | tee -a "$LOG"
            exit 1
        fi
    done

    echo "$(date) : ✅ Docker est maintenant disponible (après ${WAIT_TIME}s)" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ✅ Docker est déjà actif" 2>&1 | tee -a "$LOG"
fi

# --- Lancer les conteneurs s'ils sont arrêtés ---
# --- Lancer les conteneurs s'ils sont arrêtés ---
docker start $DB_CONTAINER >/dev/null 2>&1 || {
  echo "$(date) : Impossible de démarrer $DB_CONTAINER" 2>&1 | tee -a "$LOG"
  exit 1
}
docker start $NGINX_CONTAINER >/dev/null 2>&1 || {
  echo "$(date) : Impossible de démarrer $NGINX_CONTAINER" 2>&1 | tee -a "$LOG"
  exit 1
}
# --- Lancer le conteneur du projet s'il n'est pas déjà actif ---
if docker ps --format '{{.Names}}' | grep -q "^${SITE_CONTAINER}$"; then
    echo "$(date) : ✅ Conteneur $SITE_CONTAINER déjà actif, pas de rebuild" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : 🔄 Conteneur $SITE_CONTAINER non actif, lancement avec build..." 2>&1 | tee -a "$LOG"
    docker compose up -d --build 2>&1 | tee -a "$LOG"
    # --- Pause pour laisser les services se stabiliser ---
    echo "Pause de 10 secondes pour laisser les services se stabiliser..." 2>&1 | tee -a "$LOG"
    sleep 10
fi


# --- Détection de l'environnement d'exécution (Docker, OrbStack, PHP direct) ---
if docker compose exec -T "$APP_SERVICE" php -v >/dev/null 2>&1; then
    PHP_CMD="docker compose exec -T $APP_SERVICE php $PHP_CLI_OPTS"
    echo "$(date) : ✅ Environnement Docker Compose détecté" 2>&1 | tee -a "$LOG"
elif command -v orb >/dev/null 2>&1 && orb ps | grep -q "$APP_SERVICE"; then
    PHP_CMD="orb exec $APP_SERVICE -- php $PHP_CLI_OPTS"
    echo "$(date) : ✅ Environnement OrbStack détecté" 2>&1 | tee -a "$LOG"
elif command -v php >/dev/null 2>&1; then
    PHP_CMD="php $PHP_CLI_OPTS"
    echo "$(date) : ✅ Exécution directe via PHP CLI" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Impossible de détecter un environnement d'exécution compatible (Docker, OrbStack ou PHP CLI)" 2>&1 | tee -a "$LOG"
    exit 1
fi

# --- Vérifier artisan ---
if [[ ! -f "artisan" ]]; then
    echo "$(date) : ❌ Fichier artisan non trouvé dans $PROJECT_DIR" 2>&1 | tee -a "$LOG"
    exit 1
fi


# --- Phase 1 & 2 pour TOUS les sports ---
SPORTS=(tennis football basketball handball ice-hockey volleyball baseball rugby)
for SPORT in "${SPORTS[@]}"; do
    UPPER=$(echo "$SPORT" | tr 'a-z' 'A-Z')
    CACHE_MARKER="$PROJECT_DIR/storage/app/sofascore_cache/${SPORT}_CACHE_DONE_$(date +%Y-%m-%d)"
    LEAGUE_PATTERN="$PROJECT_DIR/storage/app/sofascore_cache/${SPORT}_LEAGUE_DONE_$(date +%Y-%m-%d)_*"
    FORCE_VAR="${UPPER}_FORCE"
    FORCE_VAL="${!FORCE_VAR}"
    echo "$(date) : ▶️ Import $SPORT..." 2>&1 | tee -a "$LOG"
    if [[ "$FORCE_VAL" != "1" ]]; then
        if [[ -f "$CACHE_MARKER" ]] || compgen -G "$LEAGUE_PATTERN" >/dev/null; then
            echo "$(date) : ⏭️  $SPORT Phase 1 déjà faite (marker présent). Skip API." 2>&1 | tee -a "$LOG"
        else
            $PHP_CMD artisan $SPORT:import-from-schedule --import-teams --download-logos 2>&1 | tee -a "$LOG"
            if [[ $? -eq 0 ]]; then
                echo "$(date) : ✅ $SPORT Phase 1 (cache) terminée" 2>&1 | tee -a "$LOG"
                printf "%s" "done" > "$CACHE_MARKER" 2>/dev/null || true
            else
                echo "$(date) : ❌ Erreur lors du cache $SPORT Phase 1" 2>&1 | tee -a "$LOG"
            fi
        fi
    else
        echo "$(date) : ⚠️ $SPORT force demandé (${UPPER}_FORCE=1) — exécution Phase 1" 2>&1 | tee -a "$LOG"
        $PHP_CMD artisan $SPORT:import-from-schedule --import-teams --download-logos 2>&1 | tee -a "$LOG"
        if [[ $? -eq 0 ]]; then
            printf "%s" "done" > "$CACHE_MARKER" 2>/dev/null || true
        fi
    fi

    # Phase 2 : cache → BDD (toujours exécutée, la commande gère les doublons)
    echo "$(date) : ▶️ $SPORT Phase 2 (cache → BDD)..." 2>&1 | tee -a "$LOG"
    $PHP_CMD artisan $SPORT:import-from-cache --force --import-teams --download-logos 2>&1 | tee -a "$LOG"
    if [[ $? -eq 0 ]]; then
        echo "$(date) : ✅ $SPORT Phase 2 (import) terminée" 2>&1 | tee -a "$LOG"
    else
        echo "$(date) : ❌ Erreur lors de l'import $SPORT Phase 2" 2>&1 | tee -a "$LOG"
    fi
done

# --- Import Football ---
echo "$(date) : ⚽ Import Football..." 2>&1 | tee -a "$LOG"
FOOTBALL_CACHE_MARKER="$PROJECT_DIR/storage/app/sofascore_cache/football_CACHE_DONE_$(date +%Y-%m-%d)"

# Phase 1 : API → cache (skip si déjà fait aujourd'hui)
# Accept either a global cache marker (football_CACHE_DONE_YYYY-MM-DD) OR
# one or more per-league markers (football_LEAGUE_DONE_YYYY-MM-DD_<id>).
FOOTBALL_LEAGUE_PATTERN="$PROJECT_DIR/storage/app/sofascore_cache/football_LEAGUE_DONE_$(date +%Y-%m-%d)_*"
if [[ "${FOOTBALL_FORCE:-}" != "1" ]]; then
    if [[ -f "$FOOTBALL_CACHE_MARKER" ]] || compgen -G "$FOOTBALL_LEAGUE_PATTERN" >/dev/null; then
        echo "$(date) : ⏭️  Football Phase 1 déjà faite (marker présent). Skip API." 2>&1 | tee -a "$LOG"
    else
        $PHP_CMD artisan football:import-from-schedule --import-teams --download-logos 2>&1 | tee -a "$LOG"
        if [[ $? -eq 0 ]]; then
            echo "$(date) : ✅ Football Phase 1 (cache) terminée" 2>&1 | tee -a "$LOG"
            printf "%s" "done" > "$FOOTBALL_CACHE_MARKER" 2>/dev/null || true
        else
            echo "$(date) : ❌ Erreur lors du cache Football Phase 1" 2>&1 | tee -a "$LOG"
        fi
    fi
else
    echo "$(date) : ⚠️ Football force demandé (FOOTBALL_FORCE=1) — exécution Phase 1" 2>&1 | tee -a "$LOG"
    $PHP_CMD artisan football:import-from-schedule  --import-teams --download-logos 2>&1 | tee -a "$LOG"
    if [[ $? -eq 0 ]]; then
        printf "%s" "done" > "$FOOTBALL_CACHE_MARKER" 2>/dev/null || true
    fi
fi

# Phase 2 : cache → BDD (toujours exécutée, la commande gère les doublons)
echo "$(date) : ⚽ Football Phase 2 (cache → BDD)..." 2>&1 | tee -a "$LOG"
$PHP_CMD artisan football:import-from-cache --force --import-teams --download-logos 2>&1 | tee -a "$LOG"
if [[ $? -eq 0 ]]; then
    echo "$(date) : ✅ Football Phase 2 (import) terminée" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Erreur lors de l'import Football Phase 2" 2>&1 | tee -a "$LOG"
fi

# --- Import Basketball ---
echo "$(date) : 🏀 Import Basketball..." 2>&1 | tee -a "$LOG"
BASKETBALL_CACHE_MARKER="$PROJECT_DIR/storage/app/sofascore_cache/basketball_CACHE_DONE_$(date +%Y-%m-%d)"

# Phase 1 : API → cache (skip si déjà fait aujourd'hui)
# Accept either a global cache marker (basketball_CACHE_DONE_YYYY-MM-DD) OR
# one or more per-league markers (basketball_LEAGUE_DONE_YYYY-MM-DD_<id>).
BASKETBALL_LEAGUE_PATTERN="$PROJECT_DIR/storage/app/sofascore_cache/basketball_LEAGUE_DONE_$(date +%Y-%m-%d)_*"
if [[ "${BASKETBALL_FORCE:-}" != "1" ]]; then
    if [[ -f "$BASKETBALL_CACHE_MARKER" ]] || compgen -G "$BASKETBALL_LEAGUE_PATTERN" >/dev/null; then
        echo "$(date) : ⏭️  Basketball Phase 1 déjà faite (marker présent). Skip API." 2>&1 | tee -a "$LOG"
    else
        $PHP_CMD artisan basketball:import-from-schedule --import-teams --download-logos 2>&1 | tee -a "$LOG"
        if [[ $? -eq 0 ]]; then
            echo "$(date) : ✅ Basketball Phase 1 (cache) terminée" 2>&1 | tee -a "$LOG"
            printf "%s" "done" > "$BASKETBALL_CACHE_MARKER" 2>/dev/null || true
        else
            echo "$(date) : ❌ Erreur lors du cache Basketball Phase 1" 2>&1 | tee -a "$LOG"
        fi
    fi
else
    echo "$(date) : ⚠️ Basketball force demandé (BASKETBALL_FORCE=1) — exécution Phase 1" 2>&1 | tee -a "$LOG"
    $PHP_CMD artisan basketball:import-from-schedule --no-cache --import-teams --download-logos 2>&1 | tee -a "$LOG"
    if [[ $? -eq 0 ]]; then
        printf "%s" "done" > "$BASKETBALL_CACHE_MARKER" 2>/dev/null || true
    fi
fi

# Phase 2 : cache → BDD (toujours exécutée, la commande gère les doublons)
echo "$(date) : 🏀 Basketball Phase 2 (cache → BDD)..." 2>&1 | tee -a "$LOG"
$PHP_CMD artisan basketball:import-from-cache --force --import-teams --download-logos 2>&1 | tee -a "$LOG"
if [[ $? -eq 0 ]]; then
    echo "$(date) : ✅ Basketball Phase 2 (import) terminée" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Erreur lors de l'import Basketball Phase 2" 2>&1 | tee -a "$LOG"
fi



# --- Exclure temporairement les fichiers "marker" locaux avant d'envoyer le cache au serveur
# Certains fichiers _CACHE_DONE_, _LEAGUE_DONE_ ou IMPORT_DONE_* peuvent être déplacés
# temporairement pour ne pas fausser l'état côté serveur. Ce comportement est contrôlé
# par la variable d'environnement SEND_MARKERS (1 = envoyer les markers, 0 = les exclure).
if [[ "${SEND_MARKERS:-1}" == "1" ]]; then
    echo "$(date) : ℹ️  SEND_MARKERS=1 — les markers (_CACHE_DONE_, _LEAGUE_DONE_, IMPORT_DONE_*) seront envoyés." 2>&1 | tee -a "$LOG"
else
    MARKER_TMP_DIR="$LOCK_DIR/sofascore_markers_tmp_$(date +%s)"
    mkdir -p "$MARKER_TMP_DIR"
    echo "$(date) : 🔒 Déplacement temporaire des fichiers marker vers $MARKER_TMP_DIR" 2>&1 | tee -a "$LOG"

    # Déplacer les fichiers marker en conservant l'arborescence relative
    find "$PROJECT_DIR/storage/app/sofascore_cache" -type f \(
        -name "*_CACHE_DONE_*" -o -name "*_LEAGUE_DONE_*" -o -name "IMPORT_DONE_*"
    \) -print0 | while IFS= read -r -d '' file; do
        rel="${file#"$PROJECT_DIR/storage/app/sofascore_cache/"}"
        destdir="$MARKER_TMP_DIR/$(dirname "$rel")"
        mkdir -p "$destdir"
        mv -f "$file" "$destdir/" 2>&1 | tee -a "$LOG" || true
    done
fi

echo "$(date) : 📤 Synchronisation avec ljdsync..." 2>&1 | tee -a "$LOG"
ljdsync 2>&1 | tee -a "$LOG"
LJDSYNC_EXIT_CODE=$?

if [[ "${SEND_MARKERS:-1}" == "1" ]]; then
    echo "$(date) : ℹ️  SEND_MARKERS=1 — pas de restauration des markers (ils ont été envoyés)." 2>&1 | tee -a "$LOG"
else
    # Restaurer les fichiers marker depuis le répertoire temporaire
    echo "$(date) : 🔓 Restauration des markers locaux depuis $MARKER_TMP_DIR" 2>&1 | tee -a "$LOG"
    if [[ -d "$MARKER_TMP_DIR" ]]; then
        find "$MARKER_TMP_DIR" -type f -print0 | while IFS= read -r -d '' f; do
            rel="${f#"$MARKER_TMP_DIR/"}"
            destdir="$PROJECT_DIR/storage/app/sofascore_cache/$(dirname "$rel")"
            mkdir -p "$destdir"
            mv -f "$f" "$destdir/" 2>&1 | tee -a "$LOG" || true
        done
        # tenter de supprimer les répertoires temporaires (silencieux en cas d'échec)
        find "$MARKER_TMP_DIR" -depth -type d -exec rmdir {} \; 2>/dev/null || true
    fi
fi

if [[ $LJDSYNC_EXIT_CODE -eq 0 ]]; then
    echo "$(date) : ✅ Synchronisation terminée avec succès" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Erreur lors de la synchronisation" 2>&1 | tee -a "$LOG"
fi

# Si la synchronisation a réussi, créer un marqueur global indiquant la date/heure
# de la dernière sync pour permettre l'archivage local côté import (non-production).
if [[ $LJDSYNC_EXIT_CODE -eq 0 ]]; then
    SYNC_MARKER_DIR="$PROJECT_DIR/storage/app/sofascore_cache"
    SYNC_MARKER_FILE="$SYNC_MARKER_DIR/.synced_at"
    if [[ ! -d "$SYNC_MARKER_DIR" ]]; then
        mkdir -p "$SYNC_MARKER_DIR" 2>&1 | tee -a "$LOG" || true
    fi
    date +%s > "$SYNC_MARKER_FILE" 2>/dev/null || true
    echo "$(date) : ✅ Marqueur de sync créé: $SYNC_MARKER_FILE" 2>&1 | tee -a "$LOG"
fi


# --- Nettoyage cache temporaire ---
#rm -rf storage/app/sofascore_cache/tennis_players/* 2>&1 | tee -a "$LOG" 2>/dev/null

# --- Arrêter le conteneur Laravel ---
#docker stop "$SITE_CONTAINER" >/dev/null 2>&1
echo "$(date) : Conteneur $SITE_CONTAINER arrêté" 2>&1 | tee -a "$LOG"

echo "$(date) : 🎉 Processus terminé!" 2>&1 | tee -a "$LOG"

exit 0
