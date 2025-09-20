# Résumé du déploiement du cron sur le serveur

## 📋 Modifications apportées

### 1. Configuration crontab adaptée au serveur
- **Fichier modifié** : `tennis_import_crontab.txt`
- **Changement** : Tous les chemins ont été adaptés pour utiliser `cd ~/api.auxotracker && ./tennis_import_cron.sh`
- **Raison** : Exécution dans le bon répertoire sur le serveur

### 2. Script cron renforcé
- **Fichier modifié** : `tennis_import_cron.sh`
- **Ajout** : Vérification de la présence du fichier `artisan` pour s'assurer d'être dans le bon répertoire
- **Raison** : Sécurité et validation de l'environnement

### 3. Guide d'installation mis à jour
- **Fichier modifié** : `CRON_TENNIS_IMPORT_GUIDE.md`
- **Changement** : Instructions spécifiques pour le déploiement serveur
- **Ajout** : Étapes de déploiement et configuration

### 4. Script de déploiement automatique
- **Fichier créé** : `deploy_cron_to_server.sh`
- **Fonction** : Déploie automatiquement tous les fichiers nécessaires sur le serveur
- **Utilise** : Configuration `.dploycnf` existante

## 🚀 Déploiement effectué

✅ **Statut** : Déploiement réussi sur `bouteille.o2switch.net`
✅ **Fichiers transférés** :
- `tennis_import_cron.sh`
- `tennis_import_crontab.txt`
- `CRON_TENNIS_IMPORT_GUIDE.md`

## 📝 Étapes suivantes sur le serveur

1. **Se connecter au serveur** :
   ```bash
   ssh sc2vagr6376@bouteille.o2switch.net
   ```

2. **Aller dans le répertoire du projet** :
   ```bash
   cd api.auxotracker
   ```

3. **Rendre le script exécutable** :
   ```bash
   chmod +x tennis_import_cron.sh
   ```

4. **Tester le script** :
   ```bash
   ./tennis_import_cron.sh
   ```

5. **Configurer le cron** :
   ```bash
   # Choisir l'option souhaitée dans tennis_import_crontab.txt
   # Par défaut : OPTION 0 (toutes les 5 minutes) et OPTION 3 (2x/jour) sont actives
   crontab tennis_import_crontab.txt
   ```

6. **Vérifier l'installation** :
   ```bash
   crontab -l
   ```

## ⚙️ Options de fréquence configurées

- **OPTION 0** : Toutes les 5 minutes (développement/test) - **ACTIVE**
- **OPTION 1** : Toutes les heures (développement)
- **OPTION 2** : Toutes les 6 heures (production fréquente)
- **OPTION 3** : Deux fois par jour à 8h15 et 20h15 (production) - **ACTIVE**
- **OPTION 4** : Une fois par jour à 2h15 (nuit)
- **OPTION 5** : Jours ouvrables à 7h15

## 🔍 Surveillance

### Logs
- **Emplacement** : `~/api.auxotracker/logs/tennis_import_cron_YYYYMMDD.log`
- **Rotation** : Automatique (suppression > 30 jours)

### Commandes utiles
```bash
# Voir le log du jour
tail -f logs/tennis_import_cron_$(date +%Y%m%d).log

# Voir les tâches cron actives
crontab -l

# Éditer les tâches cron
crontab -e
```

## 🛠️ Maintenance

### Redéployer les modifications
```bash
# Depuis le répertoire backend local
./deploy_cron_to_server.sh
```

### Désactiver temporairement
```bash
# Commenter les lignes dans le cron
crontab -e
```

### Supprimer complètement
```bash
crontab -r
```

## 📞 Support

En cas de problème :
1. Vérifier les logs : `tail -f logs/tennis_import_cron_*.log`
2. Tester manuellement : `./tennis_import_cron.sh`
3. Vérifier le cron : `crontab -l`
4. Consulter le guide : `CRON_TENNIS_IMPORT_GUIDE.md`