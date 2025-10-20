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
PROJECT_DIR="/Users/steeven/PROJETS/WORKSPACE/NEW BET TRACKER/backend"
SITE_CONTAINER="api.auxotracker"   # Conteneur Laravel
DB_CONTAINER="mariadb"             # Conteneur MariaDB
NGINX_CONTAINER="nginx-proxy"      # Conteneur Nginx
LOG="$PROJECT_DIR/script/logs/tennis_cache_sync_local_$(date +%Y-%m-%d_%H-%M-%S).log"

# --- Timestamp pour le log ---
echo "=== Cron exécuté à $(date) ===" 2>&1 | tee -a "$LOG"

# --- Se placer dans le bon dossier Laravel ---
cd "$PROJECT_DIR" || {
    echo "$(date) : ❌ Impossible de changer de dossier vers $PROJECT_DIR" 2>&1 | tee -a "$LOG"
    exit 1
}

# --- Vérifier/relancer Docker Desktop ---
echo "$(date) : Vérification de l'état de Docker..." 2>&1 | tee -a "$LOG"

# Vérifier si Docker est déjà en cours d'exécution
if ! docker info >/dev/null 2>&1; then
    echo "$(date) : Docker n'est pas actif, tentative de démarrage..." 2>&1 | tee -a "$LOG"
    
    # Vérifier si Docker Desktop est installé
    if [ ! -d "/Applications/Docker.app" ]; then
        echo "$(date) : ❌ Docker Desktop n'est pas installé dans /Applications/" 2>&1 | tee -a "$LOG"
        exit 1
    fi
    
    # Démarrer Docker Desktop
    open --background -a Docker
    echo "$(date) : Docker Desktop lancé, attente de la disponibilité..." 2>&1 | tee -a "$LOG"
    
    # Timeout augmenté à 5 minutes pour Docker Desktop
    TIMEOUT=300
    WAIT_TIME=0
    while ! docker info >/dev/null 2>&1; do
        sleep 15
        WAIT_TIME=$((WAIT_TIME+15))
        TIMEOUT=$((TIMEOUT-15))
        
        # Afficher le progrès toutes les minutes
        if [ $((WAIT_TIME % 60)) -eq 0 ]; then
            echo "$(date) : Attente Docker... (${WAIT_TIME}s écoulées)" 2>&1 | tee -a "$LOG"
        fi
        
        if [ $TIMEOUT -le 0 ]; then
            echo "$(date) : ❌ Docker ne répond pas après 5 minutes, abandon." 2>&1 | tee -a "$LOG"
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
# alias de lancement du container du projet
docker compose up -d --build 
# --- Pause pour laisser les services se stabiliser ---

echo "Pause de 10 secondes pour laisser les services se stabiliser..." 2>&1 | tee -a "$LOG"
sleep 10

# --- Vérifier artisan ---
if [[ ! -f "artisan" ]]; then
    echo "$(date) : ❌ Fichier artisan non trouvé dans $PROJECT_DIR" 2>&1 | tee -a "$LOG"
    exit 1
fi

# --- Génération cache tennis ---
echo "$(date) : 🎾 Génération du cache tennis..." 2>&1 | tee -a "$LOG"

#Usage:
#  tennis:cache-players [options]

#Options:
#     --delay[=DELAY]    Délai en secondes entre chaque requête API [default: "1"]
#    --force            Forcer la récupération des données en ignorant le cache existant
#      --export-data      Exporter les données collectées pour synchronisation
#      --limit[=LIMIT]    Limiter le nombre de joueurs à collecter
#      --download-images  Télécharger les images des joueurs pendant la mise en cache
#  -h, --help             Display help for the given command. When no command is given display help for the list command
#  -q, --quiet            Do not output any message
#  -V, --version          Display this application version
#      --ansi|--no-ansi   Force (or disable --no-ansi) ANSI output
#  -n, --no-interaction   Do not ask any interactive question
#      --env[=ENV]        The environment the command should run under
#  -v|vv|vvv, --verbose   Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug


php artisan tennis:cache-players --download-images 2>&1 | tee -a "$LOG"
if [[ $? -eq 0 ]]; then
    echo "$(date) : ✅ Cache généré avec succès" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Erreur lors de la génération du cache" 2>&1 | tee -a "$LOG"
    exit 1
fi

# --- Importation du cache ---
php artisan tennis:import-players-from-cache --force 2>&1 | tee -a "$LOG"
if [[ $? -eq 0 ]]; then
    echo "$(date) : ✅ Cache importé avec succès" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Erreur lors de l'importation du cache" 2>&1 | tee -a "$LOG"
    exit 1
fi

# --- Synchronisation avec ljdsync ---
echo "$(date) : 📤 Synchronisation avec ljdsync..." 2>&1 | tee -a "$LOG"
ljdsync 2>&1 | tee -a "$LOG"
if [[ $? -eq 0 ]]; then
    echo "$(date) : ✅ Synchronisation terminée avec succès" 2>&1 | tee -a "$LOG"
else
    echo "$(date) : ❌ Erreur lors de la synchronisation" 2>&1 | tee -a "$LOG"
    exit 1
fi

# --- Nettoyage cache temporaire ---
# rm -rf storage/app/sofascore_cache/tennis_players/* 2>&1 | tee -a "$LOG" 2>/dev/null

# --- Arrêter le conteneur Laravel ---
docker stop "$SITE_CONTAINER" >/dev/null 2>&1
echo "$(date) : Conteneur $SITE_CONTAINER arrêté" 2>&1 | tee -a "$LOG"

echo "$(date) : 🎉 Processus terminé!" 2>&1 | tee -a "$LOG"

exit 0
