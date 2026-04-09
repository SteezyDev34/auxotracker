#!/bin/bash

# Script de déploiement automatisé - AuxoTracker Frontend O2Switch
# Application: Vue.js SPA
# Frontend: https://auxotracker.p-com.studio//
# API: http://datas.sc2vagr6376.universe.wf/
# Hébergeur: O2Switch
# Méthode: Build local + rsync

set -e  # Arrêter le script en cas d'erreur

# Chargement de la configuration rsync
if [ -f ".dploycnf" ]; then
    source .dploycnf
else
    echo "❌ Fichier .dploycnf introuvable"
    exit 1
fi

# Configuration locale
LOCAL_DIST_DIR="./dist"
REMOTE_WEB_DIR="./auxotracker"
LOG_FILE="/tmp/deploy-auxotracker-$(date +%Y%m%d_%H%M%S).log"
BACKUP_NAME="auxotracker-backup-$(date +%Y%m%d_%H%M%S).tar.gz"

# Fonction de logging
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Fonction de sauvegarde distante
create_backup() {
    log "💾 Création de la sauvegarde distante..."
    
    # Créer le dossier de backup sur le serveur distant
    ssh "$LOGIN@$SERVER" "mkdir -p ./backups/auxotracker"
    
    # Créer une archive des fichiers actuels du site
    ssh "$LOGIN@$SERVER" "
        if [ -d '$REMOTE_WEB_DIR' ] && [ \"\$(ls -A $REMOTE_WEB_DIR 2>/dev/null)\" ]; then
            cd $REMOTE_WEB_DIR && tar -czf ../backups/auxotracker/$BACKUP_NAME .
            echo 'Backup créé: $BACKUP_NAME'
        else
            echo 'Aucun fichier à sauvegarder dans $REMOTE_WEB_DIR'
        fi
    "
    
    log "✅ Sauvegarde distante créée: $BACKUP_NAME"
}

# Vérifier les prérequis
check_prerequisites() {
    log "🔍 Vérification des prérequis..."
    
    # Vérifier Node.js
    if ! command -v node &> /dev/null; then
        log "❌ Node.js n'est pas installé"
        exit 1
    fi
    
    # Vérifier npm
    if ! command -v npm &> /dev/null; then
        log "❌ npm n'est pas installé"
        exit 1
    fi
    
    log "✅ Node.js version: $(node --version)"
    log "✅ npm version: $(npm --version)"
}

# Build de production
build_production() {
    log "📦 Build de production en cours..."
    
    # Vérifier que Node 20 est utilisé
    log "🔍 Vérification de la version Node.js..."
    if command -v nvm &> /dev/null; then
        nvm use 20
    fi
    
    node_version=$(node --version)
    log "📋 Version Node.js: $node_version"
    
    # Nettoyer le dossier dist précédent et les caches
    if [ -d "$LOCAL_DIST_DIR" ]; then
        rm -rf "$LOCAL_DIST_DIR"
        log "🧹 Ancien dossier dist supprimé"
    fi
    
    # Nettoyer node_modules et package-lock pour éviter les problèmes
    log "🧹 Nettoyage des dépendances et cache..."
    rm -rf node_modules package-lock.json
    npm cache clean --force
    
    # Installation des dépendances
    log "📥 Installation des dépendances..."
    npm install

    # Configuration de l'environnement de production
    log "⚙️ Configuration de l'environnement de production..."
    if [ -f ".env.production" ]; then
        cp .env.production .env
        log "✅ Fichier .env.production copié vers .env"
    else
        log "⚠️ Fichier .env.production non trouvé, utilisation de .env existant"
    fi

    # Build de production
    log "🔨 Build de production..."
    npx vite build
    
    if [ ! -d "$LOCAL_DIST_DIR" ]; then
        log "❌ Erreur: Le dossier dist n'a pas été créé"
        exit 1
    fi
    
    # Copier le fichier .htaccess dans dist
    if [ -f ".htaccess" ]; then
        cp .htaccess "$LOCAL_DIST_DIR/"
        log "📄 Fichier .htaccess copié dans dist"
    fi
    
    log "✅ Build de production terminé"
}

# Fonction de déploiement des fichiers avec rsync
deploy_files() {
    log "🚀 Déploiement des fichiers vers O2Switch avec rsync..."
    
    # Vérifier que le dossier dist existe
    if [ ! -d "$LOCAL_DIST_DIR" ]; then
        log "❌ Erreur: Le dossier $LOCAL_DIST_DIR n'existe pas"
        exit 1
    fi
    
    # Nettoyer le répertoire web distant
    log "🧹 Nettoyage du répertoire web distant..."
    ssh "$LOGIN@$SERVER" "rm -rf $REMOTE_WEB_DIR/* $REMOTE_WEB_DIR/.*[^.] 2>/dev/null || true"
    
    # Synchroniser les fichiers avec rsync
    log "📤 Synchronisation des fichiers..."
    rsync $OPTIONS \
        --delete \
        --progress \
        "$LOCAL_DIST_DIR/" \
        "$LOGIN@$SERVER:$REMOTE_WEB_DIR/"
    
    # Définir les permissions sur le serveur distant
    log "🔐 Configuration des permissions..."
    ssh "$LOGIN@$SERVER" "chmod -R 755 $REMOTE_WEB_DIR/"
    
    log "✅ Fichiers déployés vers O2Switch avec succès"
}

# Fonction de vérification des services
restart_services() {
    log "🔄 Vérification des services O2Switch..."
    
    # Note: O2Switch gère automatiquement Apache
    # Pas besoin de redémarrer manuellement les services
    
    # Vérifier que les fichiers sont bien présents
    ssh "$LOGIN@$SERVER" "
        if [ -f '$REMOTE_WEB_DIR/index.html' ]; then
            echo 'Fichier index.html trouvé'
        else
            echo 'ERREUR: Fichier index.html manquant'
            exit 1
        fi
        
        if [ -f '$REMOTE_WEB_DIR/.htaccess' ]; then
            echo 'Fichier .htaccess trouvé'
        else
            echo 'ATTENTION: Fichier .htaccess manquant'
        fi
    "
    
    log "✅ Services O2Switch vérifiés"
}

# Fonction de vérification de santé
health_check() {
    log "🏥 Vérification de santé de l'application..."
    
    # Attendre quelques secondes pour que les changements soient pris en compte
    log "⏳ Attente de la propagation des changements..."
    sleep 5
    
    # Test de l'application frontend O2Switch
    log "🌐 Test du frontend..."
    if curl -f -s -I "https://auxotracker.p-com.studio/" > /dev/null; then
        log "✅ Application frontend accessible"
    else
        log "❌ Erreur: Application frontend non accessible"
        log "🔍 Vérifiez les logs Apache et la configuration DNS"
        exit 1
    fi
    
    # Test de l'API O2Switch
    log "🔌 Test de l'API..."
    if curl -f -s -I "http://datas.sc2vagr6376.universe.wf/api" > /dev/null; then
        log "✅ API accessible"
    else
        log "⚠️  API non accessible (vérifier le backend)"
        log "💡 L'API doit être déployée séparément"
    fi
    
    # Test de la page d'accueil avec contenu
    log "📄 Test du contenu de la page..."
    if curl -s "https://auxotracker.p-com.studio/" | grep -q "AuxoTracker" 2>/dev/null; then
        log "✅ Contenu de la page vérifié"
    else
        log "⚠️  Contenu de la page non trouvé (peut être normal)"
    fi
    
    log "✅ Vérifications de santé terminées"
}

# Fonction de nettoyage
cleanup() {
    log "🧹 Nettoyage des fichiers temporaires..."
    
    # Nettoyer les anciens backups sur le serveur distant (garder les 5 derniers)
    ssh "$LOGIN@$SERVER" "
        cd ./backups/auxotracker 2>/dev/null || exit 0
        ls -t *.tar.gz 2>/dev/null | tail -n +6 | xargs rm -f 2>/dev/null || true
        echo 'Anciens backups nettoyés'
    " 2>/dev/null || log "⚠️  Impossible de nettoyer les anciens backups"
    
    # Nettoyer le cache de build local
    rm -rf node_modules/.cache 2>/dev/null || true
    rm -rf .vite 2>/dev/null || true
    
    log "✅ Nettoyage terminé"
}

# Fonction principale
main() {
    log "🚀 Début du déploiement AuxoTracker Frontend sur O2Switch..."
    log "📋 Configuration: $LOGIN@$SERVER"
    log "📁 Répertoire distant: $REMOTE_WEB_DIR"
    
    # Vérifier la connectivité SSH
    log "🔐 Test de la connexion SSH..."
    if ! ssh -o ConnectTimeout=10 "$LOGIN@$SERVER" "echo 'Connexion SSH réussie'"; then
        log "❌ Erreur: Impossible de se connecter au serveur"
        exit 1
    fi
    
    # Exécuter les étapes de déploiement
    create_backup
    build_production
    deploy_files
    restart_services
    health_check
    cleanup
    
    log "🎉 Déploiement terminé avec succès!"
    log "📱 Application accessible sur: https://auxotracker.p-com.studio/"
    log "🔗 API disponible sur: http://datas.sc2vagr6376.universe.wf"
    log "📊 Log de déploiement: $LOG_FILE"
}

# Exécuter le script principal
main "$@"