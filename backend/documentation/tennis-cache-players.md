# `tennis:cache-players` — Collecter les données des joueurs de tennis

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan tennis:cache-players` |
| **Classe** | `App\Console\Commands\ImportTennisPlayers` |
| **Fichier** | `app/Console/Commands/ImportTennisPlayers.php` |
| **Catégorie** | Import / Tennis |
| **API externe** | Sofascore (tournois en cours + joueurs) |

## Description

Collecter et mettre en cache les données des joueurs de tennis depuis l'API Sofascore **sans les persister en base de données**. Cette commande est la première étape d'un workflow en deux phases : collecte (cette commande) puis import en base (`tennis:import-from-cache`).

---

## Signature

```bash
php artisan tennis:cache-players [--delay=1] [--no-cache] [--force] [--limit=] [--download-images]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--delay=` | `1` | Délai en secondes entre chaque requête API |
| `--no-cache` | `false` | Désactiver le cache |
| `--force` | `false` | Forcer la récupération en ignorant le cache existant |
<!-- option --export-data retirée -->
| `--limit=` | `null` | Limiter le nombre de joueurs à collecter |
| `--download-images` | `false` | Télécharger les images des joueurs pendant la mise en cache |

---

## Fonctionnement détaillé

1. **Initialisation du cache** : crée la structure hiérarchique :
   ```
   storage/app/sofascore_cache/tennis_players/
   ├── tournaments/     # Cache des tournois
   ├── players/         # Cache des joueurs
   │   └── logos/       # Images des joueurs
   │   └── statistics/  # Stats des joueurs
   ├── metadata/        # Métadonnées
   └── compressed/      # Cache compressé
   ```
2. **Nettoyage du cache expiré** : automatique (une fois par jour)
3. **Récupération des tournois en cours** : liste les tournois de tennis actuels
4. **Pour chaque tournoi** :
   - Liste les matchs
   - Pour chaque match, extrait les données des joueurs
   - Sauvegarde en cache avec TTL adaptatif selon le type de données
5. **Export optionnel** : l'export JSON n'est plus géré par `tennis:cache-players`. Si un export est nécessaire, exécutez un job séparé ou utilisez l'outil dédié de synchronisation.

---

## Système de cache intelligent

- **TTL adaptatif** : durée de validité du cache différente selon le type de données
- **Clés de cache** : générées par hash MD5 de l'URL + type
- **Calcul des statistiques de cache** : taille en Mo, hits/misses
- **Nettoyage automatique** : suppression des données expirées

---

## Statistiques suivies

| Métrique | Description |
|---|---|
| `tournaments_processed` | Tournois traités |
| `matches_processed` | Matchs traités |
| `players_processed` / `created` / `updated` / `skipped` | Joueurs |
| `images_downloaded` | Images téléchargées |
| `cache_hits` / `cache_misses` | Performance du cache |
| `cache_size_mb` | Taille du cache en Mo |

---

## Dépendances

- `App\Models\Team`

---

## Exemples d'utilisation

```bash
# Collecte standard
php artisan tennis:cache-players

# Ignorer le cache existant
php artisan tennis:cache-players --force

# Limiter à 50 joueurs avec images
php artisan tennis:cache-players --limit=50 --download-images

# Export pour synchronisation (voir remarque ci-dessous)
<!-- commande deprecated: php artisan tennis:cache-players --export-data -->

# Collecte rapide sans cache
php artisan tennis:cache-players --no-cache --delay=0
```

> Remarque : Le cron de production (`tennis_import_cron.sh`) n'appelle pas `--export-data`. Dans la configuration actuelle (rsync + import depuis cache), l'export JSON produit par `--export-data` n'est pas utilisé par le script serveur et peut être considéré comme redondant. Supprimez ou ignorez son usage dans les crons/automatisations si vous souhaitez éviter des fichiers exports inutiles.

---

## Notes

- Cette commande ne modifie **pas** la base de données. Utiliser `tennis:import-from-cache` pour persister les données.
- Conçue pour être exécutée en cron job pour maintenir un cache frais.

## Exécution en cron (comportement du script)

Quand `tennis:cache-players` est lancé depuis le cron local `backend/script/tennis_cache_sync_local.sh`, le script applique des protections pour éviter des runs redondants :

- Le script crée un marqueur d'import réussi pour chaque jour : `storage/app/sofascore_cache/tennis_players/IMPORT_DONE_YYYY-MM-DD` après `tennis:import-from-cache` réussi. Si ce marqueur existe, le script **ne relancera pas** la séquence tennis (collecte + import) sauf si forcé.
- Pour forcer malgré le marqueur, exporter `TENNIS_FORCE=1` dans l'environnement (ex. crontab) — le script passera `--force` à `tennis:cache-players` si cette variable est définie.
- Variables d'environnement utiles dans le cron :
   - `TENNIS_DOWNLOAD_IMAGES` : mettez `--download-images` pour activer le téléchargement d'images (par défaut activé dans le script). Laisser vide pour ne pas télécharger.
   - `TENNIS_LIMIT` : limiter le nombre de joueurs traités par exécution.
   - `TENNIS_DELAY` : délai entre requêtes (défaut `1`).

Exemples :

```bash
# Forcer une exécution quotidienne même si le marqueur existe
TENNIS_FORCE=1 ./backend/script/tennis_cache_sync_local.sh

# Lancer sans télécharger d'images
TENNIS_DOWNLOAD_IMAGES= ./backend/script/tennis_cache_sync_local.sh

# Limiter à 100 joueurs
TENNIS_LIMIT=100 ./backend/script/tennis_cache_sync_local.sh

### Marqueurs par tournoi (nouveau)

- La commande `tennis:cache-players` écrit désormais un marqueur par tournoi traité : `storage/app/sofascore_cache/tennis_LEAGUE_DONE_YYYY-MM-DD_{sofascoreId}`.
- Lors d'exécutions ultérieures, la commande vérifie ces marqueurs et **ignore** les tournois déjà mis en cache pour la même date, à moins d'utiliser `--force`.
- Ce comportement réduit les re-traitements lorsque le cron est relancé plusieurs fois dans la journée.

### Cache négatif pour les images joueurs (nouveau)

- Lors du téléchargement d'images (`--download-images`), si une requête échoue, la commande écrit un tombstone descriptif dans : `storage/app/sofascore_cache/tennis_players/metadata/player_image_{sofascoreId}.meta`.
- Le tombstone contient `negative_cache: true` et un `timestamp` ; il est considéré valide **24 heures**. Tant que le tombstone est présent et valide, la commande **ne retentera pas** le téléchargement de cette image.
- Pour forcer un nouveau téléchargement d'image :
   - relancer `tennis:cache-players --force`, ou
   - supprimer manuellement le fichier tombstone `player_image_{sofascoreId}.meta`, ou
   - utiliser le script de nettoyage : `backend/script/clear_import_markers.sh --sport tennis --all` (ce script supprime désormais les tombstones images et les marqueurs liés).

```
