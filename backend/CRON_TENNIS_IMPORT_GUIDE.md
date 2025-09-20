# Guide d'installation du Cron d'importation des joueurs de tennis

## 📋 Vue d'ensemble

Ce guide vous explique comment configurer un cron pour automatiser l'importation des joueurs de tennis depuis les fichiers de cache.

## 📁 Fichiers créés

- `tennis_import_cron.sh` : Script principal d'exécution
- `tennis_import_crontab.txt` : Configuration crontab avec différentes options
- `CRON_TENNIS_IMPORT_GUIDE.md` : Ce guide d'installation

## 🚀 Installation rapide

### 1. Déployer les fichiers sur le serveur

Assurez-vous que les fichiers suivants sont déployés dans le répertoire `~/api.auxotracker` de votre serveur :
```bash
# Sur le serveur, dans le répertoire ~/api.auxotracker
ls -la tennis_import_cron.sh tennis_import_crontab.txt
```

### 2. Rendre le script exécutable

```bash
chmod +x tennis_import_cron.sh
```

### 3. Tester le script

Avant d'installer le cron, testez le script manuellement :
```bash
cd ~/api.auxotracker
./tennis_import_cron.sh
```

### 4. Configurer le cron

Le fichier `tennis_import_crontab.txt` est déjà configuré pour le serveur avec le chemin `~/api.auxotracker` :
```bash
# Installer la configuration cron
crontab tennis_import_crontab.txt

# Vérifier l'installation
crontab -l
```

## ⚙️ Options de fréquence

Le fichier `tennis_import_crontab.txt` propose plusieurs options :

### Option 1 : Toutes les heures (développement)
```bash
15 * * * * /chemin/vers/projet/tennis_import_cron.sh
```

### Option 2 : Toutes les 6 heures (production fréquente)
```bash
15 */6 * * * /chemin/vers/projet/tennis_import_cron.sh
```

### Option 3 : Deux fois par jour (recommandé)
```bash
15 8,20 * * * /chemin/vers/projet/tennis_import_cron.sh
```

### Option 4 : Une fois par jour (nuit)
```bash
15 2 * * * /chemin/vers/projet/tennis_import_cron.sh
```

### Option 5 : Jours ouvrables uniquement
```bash
15 7 * * 1-5 /chemin/vers/projet/tennis_import_cron.sh
```

## 📊 Fonctionnalités du script

### Commande exécutée
```bash
php artisan tennis:import-players-from-cache --download-images --force
```

### Options utilisées
- `--download-images` : Télécharge les images des joueurs
- `--force` : Force la mise à jour des joueurs existants
- Aucune limite : Traite tous les fichiers de cache disponibles

### Gestion des logs
- Logs quotidiens dans `logs/tennis_import_cron_YYYYMMDD.log`
- Nettoyage automatique des logs > 30 jours
- Horodatage de toutes les opérations

### Gestion des erreurs
- Vérification de l'environnement PHP
- Vérification de la présence d'artisan
- Codes de retour appropriés pour le monitoring

## 📝 Surveillance et maintenance

### Consulter les logs
```bash
# Log du jour
tail -f logs/tennis_import_cron_$(date +%Y%m%d).log

# Logs récents
ls -la logs/tennis_import_cron_*.log

# Contenu d'un log spécifique
cat logs/tennis_import_cron_20250919.log
```

### Vérifier le statut du cron
```bash
# Voir les tâches cron actives
crontab -l

# Voir les logs système du cron (sur certains systèmes)
tail -f /var/log/cron.log
```

### Désactiver temporairement
```bash
# Commenter la ligne dans le cron
crontab -e

# Ou supprimer complètement
crontab -r
```

## 🔧 Dépannage

### Le script ne s'exécute pas
1. Vérifiez les permissions : `chmod +x tennis_import_cron.sh`
2. Vérifiez le chemin dans la crontab
3. Vérifiez que PHP est accessible : `which php`

### Erreurs dans les logs
1. Consultez le fichier de log du jour
2. Vérifiez les permissions du répertoire `logs/`
3. Testez la commande manuellement

### Le cron ne se lance pas
1. Vérifiez la syntaxe crontab : `crontab -l`
2. Vérifiez que le service cron est actif
3. Consultez les logs système

## 📈 Monitoring recommandé

### Alertes à mettre en place
- Échec d'exécution du script (code de retour ≠ 0)
- Absence de logs pendant plus de 24h
- Taille des logs anormalement importante

### Métriques à surveiller
- Nombre de joueurs traités par exécution
- Temps d'exécution du script
- Taille des fichiers de logs

## 🔒 Sécurité

### Bonnes pratiques
- Le script s'exécute avec les permissions de l'utilisateur
- Pas de mots de passe en dur dans les scripts
- Logs accessibles uniquement au propriétaire
- Nettoyage automatique des anciens logs

## 📞 Support

En cas de problème :
1. Consultez les logs détaillés
2. Testez le script manuellement
3. Vérifiez la configuration cron
4. Contactez l'équipe de développement avec les logs d'erreur