#!/bin/bash

# Script d'orchestration pour l'importation et synchronisation des données tennis
# Usage: ./tennis_sync_orchestrator.sh [--dry-run] [--force] [--download-images]

set -e  # Arrêter en cas d'erreur

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="${SCRIPT_DIR}/logs"
EXPORT_DIR="${SCRIPT_DIR}/storage/app/tennis_exports"
REMOTE_HOST="sc2vagr6376@bouteille"
REMOTE_PATH="api.auxotracker"

# Couleurs pour les logs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction de logging
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✅${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️${NC} $1"
}

log_error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ❌${NC} $1"
}

# Fonction d'aide
show_help() {
    echo "Script d'orchestration pour l'importation et synchronisation des données tennis"
    echo ""
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --dry-run         Mode test (pas de synchronisation réelle)"
    echo "  --force           Forcer l'importation même si les joueurs existent"
    echo "  --download-images Télécharger les images des joueurs"
    echo "  --local-only      Exécuter seulement l'importation locale (pas de sync)"
    echo "  --sync-only       Synchroniser seulement les exports existants"
    echo "  --help            Afficher cette aide"
    echo ""
    echo "Exemples:"
    echo "  $0                                    # Importation et sync normales"
    echo "  $0 --dry-run                         # Test sans synchronisation"
    echo "  $0 --force --download-images         # Import forcé avec images"
    echo "  $0 --local-only                      # Import local seulement"
    echo "  $0 --sync-only                       # Sync des exports existants"
}

# Traitement des arguments
DRY_RUN=false
FORCE=false
DOWNLOAD_IMAGES=false
LOCAL_ONLY=false
SYNC_ONLY=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        --download-images)
            DOWNLOAD_IMAGES=true
            shift
            ;;
        --local-only)
            LOCAL_ONLY=true
            shift
            ;;
        --sync-only)
            SYNC_ONLY=true
            shift
            ;;
        --help)
            show_help
            exit 0
            ;;
        *)
            log_error "Option inconnue: $1"
            show_help
            exit 1
            ;;
    esac
done

# Créer les répertoires nécessaires
mkdir -p "$LOG_DIR"
mkdir -p "$EXPORT_DIR"

# Fichier de log pour cette exécution
LOG_FILE="${LOG_DIR}/tennis_sync_$(date '+%Y%m%d_%H%M%S').log"

# Fonction pour logger dans le fichier et la console
log_both() {
    echo "$1" | tee -a "$LOG_FILE"
}

# Début du script
log_both "🚀 Début de l'orchestration tennis - $(date)"
log_both "📁 Répertoire de travail: $SCRIPT_DIR"
log_both "📝 Fichier de log: $LOG_FILE"
log_both "🔧 Options: DRY_RUN=$DRY_RUN, FORCE=$FORCE, DOWNLOAD_IMAGES=$DOWNLOAD_IMAGES, LOCAL_ONLY=$LOCAL_ONLY, SYNC_ONLY=$SYNC_ONLY"

# Fonction d'importation locale
run_local_import() {
    log_both "\n🎾 === ÉTAPE 1: Importation locale des données tennis ==="
    
    # Construction de la commande
    IMPORT_CMD="php artisan tennis:import-players --export-data"
    
    if [ "$FORCE" = true ]; then
        IMPORT_CMD="$IMPORT_CMD --force"
    fi
    
    if [ "$DOWNLOAD_IMAGES" = true ]; then
        IMPORT_CMD="$IMPORT_CMD --download-images"
    fi
    
    log_both "🔄 Commande d'importation: $IMPORT_CMD"
    
    # Exécution de l'importation
    if eval "$IMPORT_CMD" 2>&1 | tee -a "$LOG_FILE"; then
        log_success "Importation locale terminée avec succès"
        return 0
    else
        log_error "Échec de l'importation locale"
        return 1
    fi
}

# Fonction de synchronisation vers le serveur
run_sync_to_server() {
    log_both "\n📤 === ÉTAPE 2: Synchronisation vers le serveur ==="
    
    # Vérifier s'il y a des exports récents
    LATEST_EXPORT=$(find "$EXPORT_DIR" -name "export_metadata_*.json" -type f -printf '%T@ %p\n' 2>/dev/null | sort -n | tail -1 | cut -d' ' -f2-)
    
    if [ -z "$LATEST_EXPORT" ]; then
        log_warning "Aucun export récent trouvé dans $EXPORT_DIR"
        return 1
    fi
    
    log_both "📋 Export le plus récent: $LATEST_EXPORT"
    
    # Lire les métadonnées de l'export
    EXPORT_TIMESTAMP=$(basename "$LATEST_EXPORT" .json | sed 's/export_metadata_//')
    TEAMS_FILE="${EXPORT_DIR}/teams_export_${EXPORT_TIMESTAMP}.json"
    PLAYERS_FILE="${EXPORT_DIR}/players_export_${EXPORT_TIMESTAMP}.json"
    
    # Vérifier que les fichiers existent
    if [ ! -f "$TEAMS_FILE" ] || [ ! -f "$PLAYERS_FILE" ]; then
        log_error "Fichiers d'export manquants pour $EXPORT_TIMESTAMP"
        return 1
    fi
    
    log_both "📁 Fichiers à synchroniser:"
    log_both "   - Teams: $TEAMS_FILE"
    log_both "   - Players: $PLAYERS_FILE"
    log_both "   - Metadata: $LATEST_EXPORT"
    
    if [ "$DRY_RUN" = true ]; then
        log_both "🧪 [DRY RUN] Simulation de la synchronisation"
        log_both "   rsync -avz --progress $TEAMS_FILE $PLAYERS_FILE $LATEST_EXPORT $REMOTE_HOST:$REMOTE_PATH/storage/app/tennis_imports/"
        return 0
    fi
    
    # Créer le répertoire distant si nécessaire
    log_both "📁 Création du répertoire distant..."
    if ssh "$REMOTE_HOST" "mkdir -p $REMOTE_PATH/storage/app/tennis_imports" 2>&1 | tee -a "$LOG_FILE"; then
        log_success "Répertoire distant créé/vérifié"
    else
        log_error "Impossible de créer le répertoire distant"
        return 1
    fi
    
    # Synchronisation des fichiers
    log_both "🔄 Synchronisation des fichiers..."
    if rsync -avz --progress "$TEAMS_FILE" "$PLAYERS_FILE" "$LATEST_EXPORT" "$REMOTE_HOST:$REMOTE_PATH/storage/app/tennis_imports/" 2>&1 | tee -a "$LOG_FILE"; then
        log_success "Fichiers synchronisés avec succès"
    else
        log_error "Échec de la synchronisation des fichiers"
        return 1
    fi
    
    # Exécution du script de synchronisation sur le serveur
    log_both "🔄 Exécution de la synchronisation sur le serveur..."
    REMOTE_SYNC_CMD="cd $REMOTE_PATH && php sync_tennis_data.php --import-from=storage/app/tennis_imports/export_metadata_${EXPORT_TIMESTAMP}.json"
    
    if [ "$DRY_RUN" = true ]; then
        REMOTE_SYNC_CMD="$REMOTE_SYNC_CMD --dry-run"
    fi
    
    if ssh "$REMOTE_HOST" "$REMOTE_SYNC_CMD" 2>&1 | tee -a "$LOG_FILE"; then
        log_success "Synchronisation serveur terminée avec succès"
        return 0
    else
        log_error "Échec de la synchronisation serveur"
        return 1
    fi
}

# Fonction de nettoyage
cleanup() {
    log_both "\n🧹 === NETTOYAGE ==="
    
    # Supprimer les exports de plus de 7 jours
    find "$EXPORT_DIR" -name "*.json" -type f -mtime +7 -delete 2>/dev/null || true
    
    # Supprimer les logs de plus de 30 jours
    find "$LOG_DIR" -name "*.log" -type f -mtime +30 -delete 2>/dev/null || true
    
    log_success "Nettoyage terminé"
}

# Fonction principale
main() {
    local exit_code=0
    
    # Vérifier les prérequis
    if ! command -v php &> /dev/null; then
        log_error "PHP n'est pas installé ou pas dans le PATH"
        exit 1
    fi
    
    if ! command -v rsync &> /dev/null; then
        log_error "rsync n'est pas installé"
        exit 1
    fi
    
    # Exécution selon les options
    if [ "$SYNC_ONLY" = false ]; then
        if ! run_local_import; then
            exit_code=1
        fi
    fi
    
    if [ "$LOCAL_ONLY" = false ] && [ $exit_code -eq 0 ]; then
        if ! run_sync_to_server; then
            exit_code=1
        fi
    fi
    
    # Nettoyage
    cleanup
    
    # Résumé final
    log_both "\n🏁 === RÉSUMÉ FINAL ==="
    if [ $exit_code -eq 0 ]; then
        log_success "Orchestration terminée avec succès!"
    else
        log_error "Orchestration terminée avec des erreurs"
    fi
    
    log_both "📝 Log complet: $LOG_FILE"
    log_both "⏰ Durée totale: $SECONDS secondes"
    
    exit $exit_code
}

# Gestion des signaux pour un arrêt propre
trap 'log_error "Script interrompu par l'\''utilisateur"; exit 130' INT TERM

# Exécution du script principal
main "$@"