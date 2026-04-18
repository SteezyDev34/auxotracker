# `wta:import-players` — Importer les joueuses WTA

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan wta:import-players` |
| **Classe** | `App\Console\Commands\ImportWtaPlayers` |
| **Fichier** | `app/Console/Commands/ImportWtaPlayers.php` |
| **Catégorie** | Import / Tennis |
| **API externe** | Sofascore (`/api/v1/rankings/type/6`) |

## Description

Importe les joueuses du classement WTA (Women's Tennis Association) depuis l'API Sofascore et les crée en tant qu'entrées dans la table `teams`. Chaque joueuse est associée à la ligue WTA (league_id fixe : `19777`, partagé avec l'ATP).

---

## Signature

```bash
php artisan wta:import-players [--force]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer l'import même si la joueuse existe déjà |

---

## Fonctionnement détaillé

1. **Appel API** : requête GET vers `https://www.sofascore.com/api/v1/rankings/type/6` (classement WTA)
2. **Parsing** : extraction du tableau `rankings[]`
3. **Pour chaque joueuse** :
   - Vérifie la présence de `team.id`
   - Recherche existante par `sofascore_id` OU (`name` + `league_id`)
   - Si existante et pas `--force` : skip
   - Sinon : `updateOrCreate` avec les données
4. **Statistiques** : créées, mises à jour, ignorées, erreurs, taux de succès

---

## Données importées par joueuse

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
# Import standard
php artisan wta:import-players

# Forcer la mise à jour
php artisan wta:import-players --force
```

---

## Notes

- Fonctionne de manière identique à `atp:import-players` mais cible le classement WTA (type 6 au lieu de type 5).
- Le `league_id` 19777 est partagé entre ATP et WTA.
