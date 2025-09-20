# Guide de Déploiement O2Switch - AuxoTracker Frontend

## 🌐 Informations du serveur
- **Frontend**: https://auxotracker.sc2vagr6376.universe.wf/
- **API Backend**: http://datas.sc2vagr6376.universe.wf/
- **Hébergeur**: O2Switch

## 📋 Prérequis O2Switch

### 1. Accès SSH
```bash
ssh sc2vagr6376@sc2vagr6376.universe.wf
```

### 2. Structure des dossiers O2Switch
```
/home/sc2vagr6376/
├── www/                    # Dossier web public (DocumentRoot)
├── auxotracker/           # Dossier du projet
│   └── frontend/          # Code source frontend
└── backups/               # Sauvegardes
    └── auxotracker/
```

## 🚀 Déploiement rapide

### Méthode 1: Script automatisé
```bash
# Se connecter au serveur
ssh sc2vagr6376@sc2vagr6376.universe.wf

# Naviguer vers le projet
cd /home/sc2vagr6376/auxotracker/frontend

# Exécuter le script de déploiement
chmod +x deploy-production.sh
./deploy-production.sh
```

### Méthode 2: Commandes manuelles
```bash
# 1. Se connecter et naviguer
ssh sc2vagr6376@sc2vagr6376.universe.wf
cd /home/sc2vagr6376/auxotracker/frontend

# 2. Installer les dépendances
npm ci --only=production

# 3. Build de production
npm run build

# 4. Déployer vers le dossier web
rm -rf /home/sc2vagr6376/www/*
cp -r dist/* /home/sc2vagr6376/www/
chmod -R 755 /home/sc2vagr6376/www/

# 5. Vérifier le déploiement
curl -I https://auxotracker.sc2vagr6376.universe.wf
```

## ⚙️ Configuration spécifique O2Switch

### Variables d'environnement (.env)
```env
VITE_API_URL=http://datas.sc2vagr6376.universe.wf/api
VITE_API_BASE_URL=http://datas.sc2vagr6376.universe.wf
```

### Configuration Apache (.htaccess)
Créer un fichier `.htaccess` dans `/home/sc2vagr6376/www/` :

```apache
# Configuration pour SPA Vue.js
RewriteEngine On
RewriteBase /

# Gestion des routes Vue Router
RewriteRule ^index\.html$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.html [L]

# Configuration CORS
Header always set Access-Control-Allow-Origin "https://auxotracker.sc2vagr6376.universe.wf"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"

# Cache des assets statiques
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$">
    ExpiresActive On
    ExpiresDefault "access plus 1 month"
    Header append Cache-Control "public, immutable"
</FilesMatch>

# Compression gzip
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

## 🔧 Optimisations O2Switch

### 1. Performance
```bash
# Optimiser les images
npm install -g imagemin-cli
find dist/ -name "*.png" -exec imagemin {} --out-dir=dist/ \;

# Analyser la taille du bundle
npm run build -- --report
```

### 2. Monitoring
```bash
# Surveiller l'espace disque
df -h /home/sc2vagr6376/

# Vérifier les logs d'accès
tail -f /home/sc2vagr6376/logs/access.log

# Tester la connectivité API
curl -I http://datas.sc2vagr6376.universe.wf/api
```

## 🔒 Sécurité

### 1. Permissions des fichiers
```bash
# Définir les bonnes permissions
chmod -R 755 /home/sc2vagr6376/www/
chmod 644 /home/sc2vagr6376/www/.htaccess
```

### 2. Protection des fichiers sensibles
```bash
# Créer un .htaccess pour protéger les fichiers de config
echo "deny from all" > /home/sc2vagr6376/auxotracker/.htaccess
```

## 📊 Tests de validation

### 1. Test de l'application
```bash
# Test de base
curl -I https://auxotracker.sc2vagr6376.universe.wf

# Test avec User-Agent
curl -H "User-Agent: Mozilla/5.0" https://auxotracker.sc2vagr6376.universe.wf
```

### 2. Test de l'API
```bash
# Test de connectivité API
curl -I http://datas.sc2vagr6376.universe.wf/api

# Test CORS
curl -H "Origin: https://auxotracker.sc2vagr6376.universe.wf" \
     -H "Access-Control-Request-Method: GET" \
     -H "Access-Control-Request-Headers: X-Requested-With" \
     -X OPTIONS \
     http://datas.sc2vagr6376.universe.wf/api
```

## 🆘 Dépannage

### Problèmes courants

1. **Erreur 404 sur les routes Vue**
   - Vérifier le fichier `.htaccess`
   - S'assurer que mod_rewrite est activé

2. **Erreurs CORS**
   - Vérifier la configuration CORS dans `.htaccess`
   - Contrôler les headers de réponse

3. **Assets non chargés**
   - Vérifier les permissions des fichiers
   - Contrôler la configuration du cache

### Commandes de diagnostic
```bash
# Vérifier la structure des fichiers
ls -la /home/sc2vagr6376/www/

# Tester la configuration Apache
curl -v https://auxotracker.sc2vagr6376.universe.wf

# Vérifier les logs d'erreur
tail -f /home/sc2vagr6376/logs/error.log
```

## 📝 Checklist de déploiement

- [ ] Variables d'environnement configurées
- [ ] Build de production réussi
- [ ] Fichiers copiés vers `/home/sc2vagr6376/www/`
- [ ] Permissions définies (755)
- [ ] Fichier `.htaccess` créé
- [ ] Test de l'application frontend
- [ ] Test de connectivité API
- [ ] Vérification des logs
- [ ] Backup créé

## 🔄 Mise à jour

Pour mettre à jour l'application :

```bash
# 1. Se connecter
ssh sc2vagr6376@sc2vagr6376.universe.wf

# 2. Naviguer vers le projet
cd /home/sc2vagr6376/auxotracker/frontend

# 3. Récupérer les dernières modifications
git pull origin main

# 4. Relancer le déploiement
./deploy-production.sh
```

---

**Note**: Ce guide est spécifique à l'environnement O2Switch avec les URLs fournies. Adaptez les chemins et URLs selon votre configuration spécifique.