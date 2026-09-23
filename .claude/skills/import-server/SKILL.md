---
name: import-server
description: Lance les migrations puis les commandes import-from-cache sur le serveur de production o2switch via SSH (après sync avec /sync-cache).
---

# Import depuis le cache côté serveur (production o2switch)

Lance `php artisan migrate` puis, pour chaque sport, la commande `import-from-cache` correspondante sur le serveur o2switch via SSH (tennis garde sa propre commande dédiée `tennis:import-from-cache` ; tous les autres sports passent par la commande générique `sport:import-from-cache {sport}`).

**Prérequis** : le cache doit d'abord avoir été synced via `/sync-cache`.

## Infos serveur
- **Host** : `bouteille.o2switch.net`
- **User** : `sc2vagr6376`
- **Répertoire Laravel** : `~/api.auxotracker`

## Instructions

Run migrations then each import-from-cache command over SSH. These read only from cache — no Sofascore API calls.

```bash
SSH="ssh sc2vagr6376@bouteille.o2switch.net"

echo "🔄 Migrations..."
$SSH "cd ~/api.auxotracker && php artisan migrate --force" 2>&1
echo "✅ Migrations terminées"

echo "🚀 Import tennis..."
$SSH "cd ~/api.auxotracker && php artisan tennis:import-from-cache --force --download-images --download-logos" 2>&1 | tail -5

echo "🚀 Import football..."
$SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache football --force --import-teams --download-logos" 2>&1 | tail -5

echo "🚀 Import basketball..."
$SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache basketball --force --import-teams --download-logos" 2>&1 | tail -5

echo "🚀 Import baseball..."
$SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache baseball --force --import-teams --download-logos" 2>&1 | tail -5

echo "🚀 Import futsal..."
$SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache futsal --force --import-teams --download-logos" 2>&1 | tail -5

echo "🚀 Import handball..."
$SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache handball --force --import-teams --download-logos" 2>&1 | tail -5

echo "🚀 Import ice hockey..."
$SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache ice-hockey --force --import-teams --download-logos" 2>&1 | tail -5

echo "🚀 Import rugby..."
$SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache rugby --force --import-teams --download-logos" 2>&1 | tail -5

echo "🚀 Import volleyball..."
$SSH "cd ~/api.auxotracker && php artisan sport:import-from-cache volleyball --force --import-teams --download-logos" 2>&1 | tail -5

echo "🎉 Tous les imports serveur terminés"
```

Report a summary table with status for each sport, and highlight any migration or SSH errors.
