# `rugby:import-leagues` — Importer les ligues de rugby

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan rugby:import-leagues` |
| **Classe** | `App\Console\Commands\ImportRugbyLeagues` |
| **Fichier** | `app/Console/Commands/ImportRugbyLeagues.php` |
| **Catégorie** | Import / Rugby |
| **API externe** | Sofascore (`/sport/rugby/categories` + `/category/{id}/unique-tournaments`) |

## Description

Importe les pays et leurs ligues de rugby depuis l'API Sofascore. Structure identique aux importeurs de handball et basketball.

---

## Signature

```bash
php artisan rugby:import-leagues [--force]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer l'importation même si des ligues existent déjà |

---

## Fonctionnement détaillé

1. **Récupération des catégories** : appel à `/sport/rugby/categories`
2. **Détection du sport** : extrait le `sofascore_id` du rugby depuis la première catégorie
3. **Pour chaque pays** :
   - Cherche/crée le pays en base
   - Récupère les ligues via `/category/{id}/unique-tournaments`
   - Parse `groups[].uniqueTournaments[]`
   - Crée/met à jour par `sofascore_id` + `sport_id`
4. **Tableau de statistiques** : ligues traitées, créées, mises à jour, ignorées

---

## Dépendances

- `App\Models\Country`, `League`, `Sport`

---

## Exemples d'utilisation

```bash
# Import standard
php artisan rugby:import-leagues

# Forcer la mise à jour
php artisan rugby:import-leagues --force
```
