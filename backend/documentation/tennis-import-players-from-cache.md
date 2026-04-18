# `tennis:import-from-cache` — Importer les joueurs de tennis depuis le cache

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan tennis:import-from-cache` |
| **Classe** | `App\Console\Commands\ImportTennisPlayersFromCache` |
| **Fichier** | `app/Console/Commands/ImportTennisPlayersFromCache.php` |
| **Catégorie** | Import / Tennis |

## Description

Deuxième étape du workflow de collecte tennis : lit les fichiers de cache créés par `tennis:cache-players` et persiste les joueurs en base de données (table `teams`). Supporte la mise à jour des joueurs existants, le téléchargement des images, et l'archivage des fichiers traités.

---

## Signature

```bash
php artisan tennis:import-from-cache [--force] [--limit=] [--download-images]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer la mise à jour des joueurs existants |
| `--limit=` | `null` | Limiter le nombre de joueurs à traiter |
| `--download-images` | `false` | Télécharger les images des joueurs |

---

## Fonctionnement détaillé

1. **Lecture du cache** : parcourt les fichiers `player_basic_*.json` dans `storage/app/sofascore_cache/tennis_players/players/`
2. **Pour chaque fichier** :
   - Lit les données de base (sofascore_id, name, etc.)
   - Vérifie si le joueur existe déjà par `sofascore_id`
   - Si existant et pas `--force` : skip
   - Détection de doublons par nom (sans `league_id`)
   - **Création** : `Team::create()` avec toutes les données
   - **Mise à jour** : `Team::update()` en **excluant le nickname** pour préserver les modifications manuelles
   - Enrichit avec les détails complets depuis le cache (statistiques, etc.)
   - Télécharge l'image si `--download-images`
3. **Archivage** (production uniquement) : déplace les fichiers traités vers `players/processed/`

---

## Spécificités

- **Préservation du nickname** : lors de la mise à jour d'un joueur existant, le champ `nickname` n'est pas écrasé pour conserver les modifications manuelles
- **Archivage conditionnel** : les fichiers ne sont archivés qu'en environnement de production (`app()->environment('production')`)
- **Enrichissement** : après création/update de base, les détails complets sont lus depuis le cache si disponibles
---

### Images et cache

- La commande copie les images depuis le cache (`storage/app/sofascore_cache/tennis_players/players/logos/{sofascoreId}.png`) vers `storage/app/public/team_logos/{team_id}.png` lors de l'import si l'option `--download-images` est fournie.
- Si une image n'est pas présente dans le cache, un avertissement est affiché et l'import continue.
- Attention : la phase 1 (`tennis:cache-players`) crée des tombstones pour les images en cas d'échec de téléchargement : `metadata/player_image_{sofascoreId}.meta` (valide 24h). Tant que ce tombstone est présent, la phase 1 ne retentera pas le téléchargement. Pour forcer un nouveau téléchargement d'image, exécuter `tennis:cache-players --force`, supprimer manuellement le tombstone, ou exécuter `backend/script/clear_import_markers.sh --sport tennis --all`.

---

## Statistiques suivies

| Métrique | Description |
|---|---|
| `players_processed` / `created` / `updated` / `skipped` | Joueurs traités |
| `duplicates_detected` | Doublons potentiels |
| `cache_files_found` / `processed` / `cleaned` | Fichiers de cache |
| `errors` | Erreurs |

---

## Dépendances

- `App\Models\Team`
- `App\Services\TeamLogoService`

---

## Exemples d'utilisation

```bash
# Import standard
php artisan tennis:import-from-cache

# Forcer la mise à jour
php artisan tennis:import-from-cache --force

# Limiter à 100 joueurs avec images
php artisan tennis:import-from-cache --limit=100 --download-images
```

---

## Workflow complet tennis

```bash
# Étape 1 : Collecter les données (pas de modification en base)
php artisan tennis:cache-players --force --download-images

# Étape 2 : Persister en base
php artisan tennis:import-from-cache --force --download-images
```
