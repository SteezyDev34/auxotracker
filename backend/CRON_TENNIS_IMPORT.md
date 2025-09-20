# Configuration du Cron Job - Importation des Joueurs de Tennis

## 📋 Description

Ce document décrit la configuration du cron job pour l'importation automatique des joueurs de tennis depuis l'API Sofascore.

## ⚙️ Configuration

### 1. Scheduler Laravel (app/Console/Kernel.php)

La tâche est configurée pour s'exécuter tous les jours à **1h40** avec les options suivantes :

```php
$schedule->command('tennis:import-players --download-images')
         ->dailyAt('01:40')
         ->timezone('Europe/Paris')
         ->withoutOverlapping()
         ->runInBackground()
         ->appendOutputTo(storage_path('logs/tennis-import.log'));
```

### 2. Cron Job Système

Le cron job système exécute le scheduler Laravel toutes les minutes :

```bash
* * * * * cd /home2/sc2vagr6376/api.auxotracker && php artisan schedule:run >> /dev/null 2>&1
```

## 🔧 Options de la Commande

- `--download-images` : Télécharge automatiquement les images des joueurs
- `--force` : Force l'importation même si le joueur existe déjà (non utilisé par défaut)
- `--delay=1` : Délai entre les requêtes API (1 seconde par défaut)
- `--no-cache` : Désactive le cache (non utilisé par défaut)

## 📊 Fonctionnalités

### Sécurité et Performance
- **withoutOverlapping()** : Empêche l'exécution simultanée de plusieurs instances
- **runInBackground()** : Exécution en arrière-plan pour éviter les timeouts
- **timezone('Europe/Paris')** : Fuseau horaire français

### Logging
- **Fichier de log** : `storage/logs/tennis-import.log`
- **Logs Laravel** : `storage/logs/laravel.log`

## 🕐 Horaire d'Exécution

- **Heure** : 01:40 (Europe/Paris)
- **Fréquence** : Quotidienne
- **Raison** : Heure creuse pour minimiser l'impact sur les performances

## 📝 Commandes Utiles

### Vérifier les tâches programmées
```bash
php artisan schedule:list
```

### Exécuter manuellement
```bash
php artisan tennis:import-players --download-images
```

### Voir les logs
```bash
tail -f storage/logs/tennis-import.log
```

### Vérifier le cron système
```bash
crontab -l
```

## 🔍 Surveillance

### Vérification du bon fonctionnement
1. Vérifier les logs quotidiennement
2. Contrôler l'ajout de nouveaux joueurs dans la base de données
3. S'assurer que les images sont téléchargées

### Indicateurs de problème
- Absence de logs dans `tennis-import.log`
- Erreurs 403 (blocage API)
- Erreurs de base de données
- Absence de nouveaux joueurs sur plusieurs jours

## 🚨 Dépannage

### Si la tâche ne s'exécute pas
1. Vérifier que le cron système fonctionne : `crontab -l`
2. Vérifier les permissions sur les fichiers de log
3. Tester manuellement : `php artisan schedule:run`

### Si l'API retourne des erreurs 403
1. Attendre quelques heures avant de relancer
2. Vérifier les logs pour le type de challenge
3. Considérer l'utilisation d'un VPN si nécessaire

## 📅 Historique

- **Date de création** : 16 septembre 2025
- **Heure configurée** : 01:40 Europe/Paris
- **Options activées** : download-images, withoutOverlapping, runInBackground