#!/bin/bash

# Script d'importation automatique des joueurs de tennis depuis le cache
# Auteur: Système de gestion des paris sportifs
# Date: $(date +%Y-%m-%d)

# Configuration des chemins
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$SCRIPT_DIR"
LOG_DIR="$PROJECT_DIR/logs"
LOG_FILE="$LOG_DIR/tennis_import_cron_$(date +%Y%m%d_%H%M%S).log"
EMPTY_LOG_FILE="$LOG_DIR/tennis_import_cron_empty_$(date +%Y%m%d).log"
# Vérifier que nous sommes dans le bon répertoire
if [[ ! -f "artisan" ]]; then
    echo "ERREUR: Le fichier artisan n'a pas été trouvé. Assurez-vous d'être dans le répertoire du projet Laravel."
    exit 1
fi

# Créer le répertoire de logs s'il n'existe pas
mkdir -p "$LOG_DIR"

# Fonction de logging
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Fonction de logging pour les messages d'information vides
log_empty_message() {
     echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$EMPTY_LOG_FILE"
}

# Fonction de gestion des erreurs
handle_error() {
    log_message "ERREUR: $1"
    exit 1
}

# Début du script
log_message "=== DÉBUT DE L'IMPORTATION AUTOMATIQUE DES JOUEURS DE TENNIS ==="
log_message "Répertoire du projet: $PROJECT_DIR"
log_message "Fichier de log: $LOG_FILE"

# S'assurer qu'on est dans le bon répertoire
cd "$PROJECT_DIR" || handle_error "Impossible de se déplacer vers $PROJECT_DIR"
log_message "Répertoire de travail actuel: $(pwd)"

# Vérifier que le fichier artisan existe
if [[ ! -f "artisan" ]]; then
    handle_error "Le fichier artisan n'existe pas dans le répertoire courant"
fi

# --- Sentinel check: only proceed if sentinel file exists ---
SENTINEL="$PROJECT_DIR/storage/app/sofascore_cache/tennis_players/IMPORT_READY"
PROCESSING_SENTINEL="${SENTINEL}.processing"
LOCK_FILE="$PROJECT_DIR/tennis_import_cron.lock"

if [[ ! -f "$SENTINEL" ]]; then
    log_empty_message "Aucun nouveau fichier détecté (sentinel absent). Sortie légère."
    rm -f "$LOG_FILE" # Supprimer le log vide
    # Nettoyer les anciens empty logs (garder seulement les 30 derniers jours)
    find "$LOG_DIR" -name "tennis_import_cron_empty_*.log" -type f -mtime +30 -delete 2>/dev/null || true
    exit 0
fi

# Prevent concurrent runs using flock
exec 200>"$LOCK_FILE"
if ! flock -n 200; then
    log_message "Un autre processus est déjà en cours. Sortie."
    exit 0
fi

# Acquire the sentinel atomically by renaming it to .processing
if ! mv "$SENTINEL" "$PROCESSING_SENTINEL" 2>/dev/null; then
    log_message "Impossible d'acquérir le sentinel; sortie."
    exit 0
fi


# Trouver le chemin complet vers PHP
# Prioriser /usr/local/bin/php pour le serveur
for php_path in /usr/local/bin/php /opt/homebrew/bin/php; do
    if [[ -x "$php_path" ]]; then
        PHP_PATH="$php_path"
        break
    fi
done

# Si aucun chemin spécifique trouvé, essayer which php
if [[ -z "$PHP_PATH" ]]; then
    PHP_PATH=$(which php)
fi

if [[ -z "$PHP_PATH" ]]; then
    handle_error "PHP non trouvé dans le PATH"
fi

log_message "Chemin PHP utilisé: $PHP_PATH"

# Exécuter l'importation des joueurs depuis le cache
log_message "Démarrage de l'importation des joueurs depuis le cache..."

# Commande d'importation avec options optimisées pour le cron
# --force: Forcer la mise à jour des joueurs existants
# Pas de limite pour traiter tous les fichiers de cache disponibles
log_message "Exécution de: $PHP_PATH artisan tennis:import-players-from-cache --force"
# Exécuter la commande, afficher à l'écran et écrire dans le log en même temps.
# On utilise tee -a pour append, puis on récupère le code de retour réel de PHP via PIPESTATUS[0].
"$PHP_PATH" artisan tennis:import-players-from-cache --download-images --force 2>&1 | tee -a "$LOG_FILE"
IMPORT_EXIT_CODE=${PIPESTATUS[0]}
if [ $IMPORT_EXIT_CODE -eq 0 ]; then
    log_message "✅ Importation terminée avec succès"
    # Suppression du sentinel processing après succès
    if [[ -f "$PROCESSING_SENTINEL" ]]; then
        rm -f "$PROCESSING_SENTINEL" || log_message "⚠️ Échec suppression du sentinel processing"
    fi
else
    log_message "❌ Importation échouée avec le code de retour: $IMPORT_EXIT_CODE"
    # Restaurer le sentinel pour réessayer plus tard
    if [[ -f "$PROCESSING_SENTINEL" ]]; then
        mv -f "$PROCESSING_SENTINEL" "$SENTINEL" || log_message "⚠️ Échec restauration du sentinel"
    fi
    exit $IMPORT_EXIT_CODE
fi

# Afficher les statistiques du fichier de log
LOG_SIZE=$(du -h "$LOG_FILE" | cut -f1)
log_message "Taille du fichier de log: $LOG_SIZE"

# Nettoyer les anciens logs (garder seulement les 30 derniers jours)
log_message "Nettoyage des anciens logs..."
find "$LOG_DIR" -name "tennis_import_cron_*.log" -type f -mtime +30 -delete 2>/dev/null
# Nettoyer les anciens empty logs (garder seulement les 30 derniers jours)
find "$LOG_DIR" -name "tennis_import_cron_empty_*.log" -type f -mtime +30 -delete 2>/dev/null

log_message "=== FIN DE L'IMPORTATION AUTOMATIQUE ==="
log_message ""

exit 0
