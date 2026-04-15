#!/bin/bash

# Script d'importation automatique des joueurs de tennis depuis le cache
# Auteur: Système de gestion des paris sportifs
# Date: $(date +%Y-%m-%d)

# Configuration des chemins
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$SCRIPT_DIR"
LOG_DIR="$PROJECT_DIR/logs"
LOG_FILE="$LOG_DIR/tennis_import_cron_$(date +%Y%m%d_%H%M%S).log"

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
log_message "Exécution de: $PHP_PATH artisan tennis:import-from-cache --download-images"
# Exécuter la commande, afficher à l'écran et écrire dans le log en même temps.
# On utilise tee -a pour append, puis on récupère le code de retour réel de PHP via PIPESTATUS[0].
"$PHP_PATH" artisan tennis:import-from-cache --force --download-images  2>&1 | tee -a "$LOG_FILE"
IMPORT_EXIT_CODE=${PIPESTATUS[0]} 
if [ $IMPORT_EXIT_CODE -eq 0 ]; then
    log_message "✅ Importation terminée avec succès"
else
    log_message "❌ Importation échouée avec le code de retour: $IMPORT_EXIT_CODE"
    # Ne pas exit, continuer avec les autres sports
fi

# --- Import Football Phase 2 (ligues + équipes depuis le cache) ---
log_message "⚽ Import Football depuis le cache (Phase 2)..."
log_message "Exécution de: $PHP_PATH artisan football:import-from-cache --import-teams --download-logos"
"$PHP_PATH" artisan football:import-from-cache --import-teams --download-logos 2>&1 | tee -a "$LOG_FILE"
FOOTBALL_EXIT_CODE=${PIPESTATUS[0]}
if [ $FOOTBALL_EXIT_CODE -eq 0 ]; then
    log_message "✅ Import Football terminé avec succès"
else
    log_message "❌ Import Football échoué avec le code de retour: $FOOTBALL_EXIT_CODE"
    # Ne pas exit, continuer avec basketball et le reste
fi

# --- Import Basketball Phase 2 (ligues + équipes depuis le cache) ---
log_message "🏀 Import Basketball depuis le cache (Phase 2)..."
log_message "Exécution de: $PHP_PATH artisan basketball:import-from-cache --import-teams --download-logos"
"$PHP_PATH" artisan basketball:import-from-cache --import-teams --download-logos 2>&1 | tee -a "$LOG_FILE"
BASKETBALL_EXIT_CODE=${PIPESTATUS[0]}
if [ $BASKETBALL_EXIT_CODE -eq 0 ]; then
    log_message "✅ Import Basketball terminé avec succès"
else
    log_message "❌ Import Basketball échoué avec le code de retour: $BASKETBALL_EXIT_CODE"
    # Ne pas exit, continuer avec le reste
fi

# --- Import Baseball Phase 2 (ligues + équipes depuis le cache) ---
log_message "⚾ Import Baseball depuis le cache (Phase 2)..."
log_message "Exécution de: $PHP_PATH artisan baseball:import-from-cache --import-teams --download-logos"
"$PHP_PATH" artisan baseball:import-from-cache --import-teams --download-logos 2>&1 | tee -a "$LOG_FILE"
BASEBALL_EXIT_CODE=${PIPESTATUS[0]}
if [ $BASEBALL_EXIT_CODE -eq 0 ]; then
    log_message "✅ Import Baseball terminé avec succès"
else
    log_message "❌ Import Baseball échoué avec le code de retour: $BASEBALL_EXIT_CODE"
    # Ne pas exit, continuer avec le reste
fi

# --- Import Futsal Phase 2 (ligues + équipes depuis le cache) ---
log_message "🥅 Import Futsal depuis le cache (Phase 2)..."
log_message "Exécution de: $PHP_PATH artisan futsal:import-from-cache --import-teams --download-logos"
"$PHP_PATH" artisan futsal:import-from-cache --import-teams --download-logos 2>&1 | tee -a "$LOG_FILE"
FUTSAL_EXIT_CODE=${PIPESTATUS[0]}
if [ $FUTSAL_EXIT_CODE -eq 0 ]; then
    log_message "✅ Import Futsal terminé avec succès"
else
    log_message "❌ Import Futsal échoué avec le code de retour: $FUTSAL_EXIT_CODE"
    # Ne pas exit, continuer avec le reste
fi

# --- Import Handball Phase 2 (ligues + équipes depuis le cache) ---
log_message "🤾 Import Handball depuis le cache (Phase 2)..."
log_message "Exécution de: $PHP_PATH artisan handball:import-from-cache --import-teams --download-logos"
"$PHP_PATH" artisan handball:import-from-cache --import-teams --download-logos 2>&1 | tee -a "$LOG_FILE"
HANDBALL_EXIT_CODE=${PIPESTATUS[0]}
if [ $HANDBALL_EXIT_CODE -eq 0 ]; then
    log_message "✅ Import Handball terminé avec succès"
else
    log_message "❌ Import Handball échoué avec le code de retour: $HANDBALL_EXIT_CODE"
    # Ne pas exit, continuer avec le reste
fi

# --- Import Ice Hockey Phase 2 (ligues + équipes depuis le cache) ---
log_message "🏒 Import Ice Hockey depuis le cache (Phase 2)..."
log_message "Exécution de: $PHP_PATH artisan ice-hockey:import-from-cache --import-teams --download-logos"
"$PHP_PATH" artisan ice-hockey:import-from-cache --import-teams --download-logos 2>&1 | tee -a "$LOG_FILE"
ICE_HOCKEY_EXIT_CODE=${PIPESTATUS[0]}
if [ $ICE_HOCKEY_EXIT_CODE -eq 0 ]; then
    log_message "✅ Import Ice Hockey terminé avec succès"
else
    log_message "❌ Import Ice Hockey échoué avec le code de retour: $ICE_HOCKEY_EXIT_CODE"
    # Ne pas exit, continuer avec le reste
fi

# --- Import Rugby Phase 2 (ligues + équipes depuis le cache) ---
log_message "🏉 Import Rugby depuis le cache (Phase 2)..."
log_message "Exécution de: $PHP_PATH artisan rugby:import-from-cache --import-teams --download-logos"
"$PHP_PATH" artisan rugby:import-from-cache --import-teams --download-logos 2>&1 | tee -a "$LOG_FILE"
RUGBY_EXIT_CODE=${PIPESTATUS[0]}
if [ $RUGBY_EXIT_CODE -eq 0 ]; then
    log_message "✅ Import Rugby terminé avec succès"
else
    log_message "❌ Import Rugby échoué avec le code de retour: $RUGBY_EXIT_CODE"
    # Ne pas exit, continuer avec le reste
fi

# --- Import Volleyball Phase 2 (ligues + équipes depuis le cache) ---
log_message "🏐 Import Volleyball depuis le cache (Phase 2)..."
log_message "Exécution de: $PHP_PATH artisan volleyball:import-from-cache --import-teams --download-logos"
"$PHP_PATH" artisan volleyball:import-from-cache --import-teams --download-logos 2>&1 | tee -a "$LOG_FILE"
VOLLEYBALL_EXIT_CODE=${PIPESTATUS[0]}
if [ $VOLLEYBALL_EXIT_CODE -eq 0 ]; then
    log_message "✅ Import Volleyball terminé avec succès"
else
    log_message "❌ Import Volleyball échoué avec le code de retour: $VOLLEYBALL_EXIT_CODE"
    # Ne pas exit, continuer avec le reste
fi

# Afficher les statistiques du fichier de log
LOG_SIZE=$(du -h "$LOG_FILE" | cut -f1)
log_message "Taille du fichier de log: $LOG_SIZE"

# Nettoyer les anciens logs (garder seulement les 30 derniers jours)
log_message "Nettoyage des anciens logs..."
find "$LOG_DIR" -name "tennis_import_cron_*.log" -type f -mtime +30 -delete 2>/dev/null

log_message "=== FIN DE L'IMPORTATION AUTOMATIQUE ==="
log_message ""

exit 0
