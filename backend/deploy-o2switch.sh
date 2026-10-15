#!/bin/bash

# Script de déploiement backend pour O2Switch
# Auteur: Assistant IA
# Date: $(date +%Y-%m-%d)

echo "🚀 Déploiement du backend sur O2Switch..."

# Charger la configuration de déploiement
source .dploycnf

# Vérifier que les variables sont définies
if [ -z "$LOGIN" ] || [ -z "$SERVER" ] || [ -z "$REMOTEDIR" ]; then
    echo "❌ Erreur: Configuration de déploiement incomplète dans .dploycnf"
    exit 1
fi

echo "📋 Configuration:"
echo "   Serveur: $SERVER"
echo "   Login: $LOGIN"
echo "   Répertoire distant: $REMOTEDIR"

# Synchronisation des fichiers avec rsync
echo "📤 Synchronisation des fichiers..."
rsync $OPTIONS \
    --exclude-from="$EXCLUDE" \
    --delete \
    --progress \
    "$LOCALDIR" \
    "$LOGIN@$SERVER:$REMOTEDIR"

if [ $? -eq 0 ]; then
    # Filet de sécurité : s'assurer que le symlink public/storage existe
    # toujours côté prod (idempotent — artisan ne recrée pas s'il est déjà
    # bon). Voir incident 2026-10-14 : supprimé une fois par ce même rsync
    # --delete, images team_logos 404 en conséquence.
    echo "🔗 Vérification du symlink public/storage..."
    ssh "$LOGIN@$SERVER" "cd $REMOTEDIR && php artisan storage:link" 2>&1

    echo "✅ Déploiement terminé avec succès!"
    echo "🌐 API disponible sur: https://auxotracker.fr//"
    echo ""
    echo "📝 Changements déployés:"
    echo "   - Configuration CORS mise à jour"
    echo "   - Headers CORS ajoutés dans .htaccess"
    echo "   - Support pour https://auxotracker.fr/"
else
    echo "❌ Erreur lors du déploiement"
    exit 1
fi