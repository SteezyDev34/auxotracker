# `basketball:import-leagues` — Importer les ligues de basketball

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan basketball:import-leagues` |
| **Classe** | `App\Console\Commands\ImportBasketballLeagues` |
| **Fichier** | `app/Console/Commands/ImportBasketballLeagues.php` |
| **Catégorie** | Import / Basketball |
| **API externe** | Sofascore (`/api/v1/sport/basketball/categories` + `/api/v1/category/{id}/unique-tournaments`) |

## Description

Importe les pays et leurs ligues de basketball depuis l'API Sofascore. Parcourt toutes les catégories (pays) puis récupère les ligues de chacun. Supporte la reprise d'importation grâce à un système de progression sauvegardée.

---

## Signature

```bash
php artisan basketball:import-leagues [--force] [--no-cache]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer l'importation même si des ligues existent déjà |
| `--no-cache` | `false` | Ne pas utiliser le cache |

---

## Fonctionnement détaillé

1. **Récupération des catégories** : appel à `/sport/basketball/categories`
2. **Détection du sport** : extrait le `sofascore_id` du sport basketball depuis la réponse API
3. **Progression** : charge un fichier de progression pour reprendre après interruption
4. **Pour chaque pays** :
   - Vérifie si déjà traité (via le fichier de progression)
   - Cherche/crée le pays en base
   - Récupère les ligues via `/category/{id}/unique-tournaments`
   - Parse la structure `groups[].uniqueTournaments[]`
   - Crée ou met à jour chaque ligue
5. **Sauvegarde de progression** : après chaque pays, sauvegarde l'état

---

## Spécificités

- **Rotation de User-Agent** : 8 User-Agents différents pour éviter la détection
- **Système de cache** : répertoire `storage/app/sofascore_cache/basketball/`
- **Reprise sur erreur** : fichier de progression permettant de reprendre une importation interrompue

---

## Dépendances

- `App\Models\Country`, `League`, `Sport`

---

## Exemples d'utilisation

```bash
# Import standard
php artisan basketball:import-leagues

# Forcer la mise à jour
php artisan basketball:import-leagues --force

# Sans cache
php artisan basketball:import-leagues --no-cache
```
