# `atp:import-players` — Importer les joueurs ATP

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan atp:import-players` |
| **Classe** | `App\Console\Commands\ImportAtpPlayers` |
| **Fichier** | `app/Console/Commands/ImportAtpPlayers.php` |
| **Catégorie** | Import / Tennis |
| **API externe** | Sofascore (`/api/v1/rankings/type/5`) |

## Description

Importe les joueurs du classement ATP (Association of Tennis Professionals) depuis l'API Sofascore et les crée en tant qu'entrées dans la table `teams`. Chaque joueur est associé à la ligue ATP (league_id fixe : `19777`).

---

## Signature

```bash
php artisan atp:import-players [--force]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer l'import même si le joueur existe déjà en base |

---

## Fonctionnement détaillé

1. **Appel API** : requête GET vers `https://www.sofascore.com/api/v1/rankings/type/5` (classement ATP)
2. **Parsing** : extraction du tableau `rankings[]` contenant les données des joueurs
3. **Pour chaque joueur** :
   - Vérifie la présence de `team.id` dans les données
   - Recherche un joueur existant par `sofascore_id` OU par (`name` + `league_id`)
   - Si existant et pas `--force` : skip
   - Sinon : `updateOrCreate` avec `sofascore_id`, `name`, `nickname` (shortName), `slug`, `league_id=19777`
4. **Rate limiting** : pause de 0.2s entre chaque joueur
5. **Statistiques** : créés, mis à jour, ignorés, erreurs, taux de succès

---

## Données importées par joueur

| Champ | Source |
|---|---|
| `sofascore_id` | `team.id` |
| `name` | `team.name` |
| `nickname` | `team.shortName` |
| `slug` | Généré via `Str::slug(name)` |
| `league_id` | `19777` (fixe) |

---

## Dépendances

- `App\Models\Team`
- `Illuminate\Support\Facades\Http`

---

## Exemples d'utilisation

```bash
# Import standard (ignore les joueurs existants)
php artisan atp:import-players

# Forcer la mise à jour de tous les joueurs
php artisan atp:import-players --force
```
