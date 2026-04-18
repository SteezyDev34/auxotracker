# `football:import-leagues` — Importer les ligues de football

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan football:import-leagues` |
| **Classe** | `App\Console\Commands\ImportFootballLeagues` |
| **Fichier** | `app/Console/Commands/ImportFootballLeagues.php` |
| **Catégorie** | Import / Football |
| **API externe** | Sofascore (`/sport/football/categories` + `/category/{id}/unique-tournaments`) |

## Description

Importe les pays et leurs ligues de football depuis l'API Sofascore. Parcourt toutes les catégories (pays) et récupère les ligues associées. Supporte le cache, l'import depuis le cache local, et le téléchargement des logos.

---

## Signature

```bash
php artisan football:import-leagues [--force] [--no-cache] [--from-cache] [--download-logos] [--delay=0]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer l'import même si la ligue existe |
| `--no-cache` | `false` | Ne pas utiliser le cache |
| `--from-cache` | `false` | Importer depuis le cache local (pas d'appel API) |
| `--download-logos` | `false` | Télécharger les logos des ligues |
| `--delay=` | `0` | Délai en secondes entre chaque requête API |

---

## Fonctionnement détaillé

1. **Récupération des catégories** :
   - En mode API : appel à `/sport/football/categories`
   - En mode cache : recherche dans `storage/app/sofascore_cache/categories_football.json`
2. **Détection du sport** : extrait le `sofascore_id` du sport football
3. **Pour chaque pays** :
   - Cherche/crée le pays en base
   - Prépare le répertoire de cache par pays : `leagues_country/{slug}-{id}/`
   - Récupère les ligues via API ou cache local
   - Parse `groups[].uniqueTournaments[]`
   - Crée/met à jour chaque ligue
   - Sauvegarde automatique du cache
4. **Statistiques** : pays traités, ligues créées/mises à jour/ignorées, erreurs, taux de succès

---

## Système de cache

- **Catégories** : `storage/app/sofascore_cache/categories_football.json`
- **Ligues par pays** : `storage/app/sofascore_cache/leagues_country/{slug}-{id}/leagues.json`
- Supporte plusieurs formats de cache en lecture (clés `categories`, `data`, ou tableau direct)

---

## Dépendances

- `App\Models\League`, `Country`, `Sport`

---

## Exemples d'utilisation

```bash
# Import standard depuis l'API
php artisan football:import-leagues

# Import depuis le cache local
php artisan football:import-leagues --from-cache

# Import forcé avec téléchargement des logos
php artisan football:import-leagues --force --download-logos --delay=1

# Sans cache
php artisan football:import-leagues --no-cache
```
