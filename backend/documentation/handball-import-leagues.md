# `handball:import-leagues` — Importer les ligues de handball

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan handball:import-leagues` |
| **Classe** | `App\Console\Commands\ImportHandballLeagues` |
| **Fichier** | `app/Console/Commands/ImportHandballLeagues.php` |
| **Catégorie** | Import / Handball |
| **API externe** | Sofascore (`/sport/handball/categories` + `/category/{id}/unique-tournaments`) |

## Description

Importe les pays et leurs ligues de handball depuis l'API Sofascore. Version simplifiée sans système de cache ni progression sauvegardée.

---

## Signature

```bash
php artisan handball:import-leagues [--force]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer l'importation même si des ligues existent déjà |

---

## Fonctionnement détaillé

1. **Récupération des catégories** : appel direct (sans headers custom) à `/sport/handball/categories`
2. **Création du sport** : utilise `firstOrCreate` pour le sport Handball
3. **Pour chaque catégorie** :
   - Recherche le pays par nom (insensible à la casse via `LOWER()`)
   - Si le pays n'est pas trouvé → **arrête le script** avec un message d'erreur
   - Récupère les ligues via `/category/{id}/unique-tournaments`
   - Parse `groups[].uniqueTournaments[]`
   - Crée ou met à jour chaque ligue par `sofascore_id` + `sport_id`
4. **Résumé** : tableau avec ligues traitées, créées, mises à jour, ignorées

---

## Spécificités

- **Pas de système de cache** : appels API directs à chaque exécution
- **Arrêt sur pays manquant** : si un pays n'est pas trouvé en base, le script s'arrête et demande de l'ajouter manuellement
- **Pas de rotation de User-Agent** : utilise les headers HTTP par défaut de Laravel

---

## Dépendances

- `App\Models\Country`, `League`, `Sport`

---

## Exemples d'utilisation

```bash
# Import standard
php artisan handball:import-leagues

# Forcer la mise à jour
php artisan handball:import-leagues --force
```
