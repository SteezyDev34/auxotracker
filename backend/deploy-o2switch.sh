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
    echo "✅ Déploiement terminé avec succès!"
    echo "🌐 API disponible sur: https://datas.sc2vagr6376.universe.wf/"
    echo ""
    echo "📝 Changements déployés:"
    echo "   - Configuration CORS mise à jour"
    echo "   - Headers CORS ajoutés dans .htaccess"
    echo "   - Support pour https://auxotracker.sc2vagr6376.universe.wf"
else
    echo "❌ Erreur lors du déploiement"
    exit 1
fi