#!/bin/bash

# Script de déploiement en production pour O2Switch
# Ce script configure l'environnement de production et déploie l'API

echo "🚀 Démarrage du déploiement en production..."

# Configuration de l'environnement de production
echo "📝 Configuration de l'environnement de production..."
if [ -f ".env.production" ]; then
    cp .env.production .env
    echo "✅ Fichier .env.production copié vers .env"
else
    echo "❌ Erreur: Fichier .env.production introuvable"
    exit 1
fi

# Installation des dépendances
echo "📦 Installation des dépendances Composer..."
composer install --no-dev --optimize-autoloader

# Génération de la clé d'application si nécessaire
if grep -q "APP_KEY=$" .env; then
    echo "🔑 Génération de la clé d'application..."
    php artisan key:generate --force
fi

# Cache des configurations
echo "⚡ Optimisation des configurations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migration de la base de données
echo "🗄️ Migration de la base de données..."
php artisan migrate --force

# Nettoyage du cache
echo "🧹 Nettoyage du cache..."
php artisan cache:clear

# Configuration des permissions
echo "🔒 Configuration des permissions..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache

echo "✅ Déploiement terminé avec succès!"
echo "🌐 API disponible à: https://auxotracker.p-com.studio/"