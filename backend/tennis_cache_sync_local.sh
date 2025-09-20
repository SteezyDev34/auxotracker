#!/bin/bash

# Script simple : génération cache tennis + ljdsync
# Génère les données de cache puis synchronise avec ljdsync

echo "🎾 Génération du cache tennis..."

# Vérifier qu'on est dans le bon répertoire
if [[ ! -f "artisan" ]]; then
    echo "❌ Erreur: fichier artisan non trouvé"
    exit 1
fi

# Générer le cache
php artisan tennis:cache-players --download-images

if [[ $? -eq 0 ]]; then
    echo "✅ Cache généré avec succès"
else
    echo "❌ Erreur lors de la génération du cache"
    exit 1
fi
# importer le cache
php artisan tennis:import-players-from-cache

if [[ $? -eq 0 ]]; then
    echo "✅ Cache importé avec succès"
else
    echo "❌ Erreur lors de l'importation du cache"
    exit 1
fi
echo "📤 Synchronisation avec ljdsync..."

# Exécuter ljdsync
ljdsync



if [[ $? -eq 0 ]]; then
    echo "✅ Synchronisation terminée avec succès"
else
    echo "❌ Erreur lors de la synchronisation"
    exit 1
fi

echo "🎉 Processus terminé!"