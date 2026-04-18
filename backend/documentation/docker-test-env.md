# `docker:test-env` — Tester l'environnement Docker

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan docker:test-env` |
| **Classe** | `App\Console\Commands\TestDockerEnvironment` |
| **Fichier** | `app/Console/Commands/TestDockerEnvironment.php` |
| **Catégorie** | Test / Infrastructure |

## Description

Commande de diagnostic qui teste la détection d'environnement Docker et affiche la configuration de base de données. Vérifie si l'application s'exécute dans un conteneur Docker et teste la connexion à la base de données.

---

## Signature

```bash
php artisan docker:test-env
```

## Options

Aucune option disponible.

---

## Informations affichées

### Détection d'environnement
- **Environnement détecté** : Docker ou Local (via `DockerHelper::isRunningInDocker()`)
- **Hôte DB recommandé** : selon l'environnement détecté
- **Hôte DB configuré** : depuis `config('database.connections.mysql.host')`
- **Variable DB_HOST** : valeur de l'environnement

### Informations système
- Existence de `/.dockerenv`
- Variable `DOCKER_CONTAINER`
- Hostname
- Analyse de `/proc/1/cgroup` (docker/containerd)

### Test de connexion DB
- Tentative de connexion à la base de données
- Driver et version du serveur MySQL/MariaDB

---

## Dépendances

- `App\Helpers\DockerHelper`
- `Illuminate\Support\Facades\DB`

---

## Exemples d'utilisation

```bash
php artisan docker:test-env
```

---

## Notes

- Commande en lecture seule : ne modifie aucune donnée.
- Utile pour débugger les problèmes de connexion DB entre environnements Docker et local.
- Les vérifications de `/proc/1/cgroup` ne fonctionnent que sur Linux.
