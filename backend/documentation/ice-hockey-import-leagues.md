# `ice-hockey:import-leagues` — Importer les ligues de hockey sur glace

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan ice-hockey:import-leagues` |
| **Classe** | `App\Console\Commands\ImportIceHockeyLeagues` |
| **Fichier** | `app/Console/Commands/ImportIceHockeyLeagues.php` |
| **Catégorie** | Import / Hockey sur glace |
| **API externe** | Sofascore (`/sport/ice-hockey/categories` + `/category/{id}/tournaments`) |

## Description

Importe les pays et leurs ligues de hockey sur glace depuis l'API Sofascore. Structure classique : récupération des catégories, puis des ligues par catégorie.

---

## Signature

```bash
php artisan ice-hockey:import-leagues [--force]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer l'importation même si des ligues existent déjà |

---

## Fonctionnement détaillé

1. **Récupération des pays** : appel à `/sport/ice-hockey/categories`
2. **Détection du sport** : extrait le `sofascore_id` du Ice Hockey
3. **Pour chaque pays** :
   - Cherche/crée en base
   - Récupère les ligues via `/category/{id}/tournaments` (noter : endpoint `tournaments` et non `unique-tournaments`)
   - Crée/met à jour chaque ligue
4. **Statistiques** : pays traités, ligues créées/mises à jour/ignorées, erreurs

---

## Spécificités

- Utilise l'endpoint `/tournaments` au lieu de `/unique-tournaments` (différent des autres sports)
- Pas de système de cache ni de progression sauvegardée
- Appels API directs sans headers personnalisés

---

## Dépendances

- `App\Models\Country`, `League`, `Sport`

---

## Exemples d'utilisation

```bash
# Import standard
php artisan ice-hockey:import-leagues

# Forcer la mise à jour
php artisan ice-hockey:import-leagues --force
```
