# `bet:extract-market-all` — Extraire les marchés de tous les événements

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan bet:extract-market-all` |
| **Classe** | `App\Console\Commands\ExtractMarketForAllEvents` |
| **Fichier** | `app/Console/Commands/ExtractMarketForAllEvents.php` |
| **Catégorie** | Paris / Extraction de données |

## Description

Traite tous les événements (events) en base qui n'ont pas encore d'équipes associées (`team1_id` ou `team2_id` NULL). Extrait le nom des équipes depuis le champ `market` (format "Équipe1 vs Équipe2") et tente de les associer aux équipes existantes en base de données.

---

## Signature

```bash
php artisan bet:extract-market-all [--dry-run] [--show-missing]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--dry-run` | `false` | Simulation : affiche les résultats sans modifier la base |
| `--show-missing` | `false` | N'affiche que les événements pour lesquels au moins une équipe n'a pas été trouvée |

---

## Fonctionnement détaillé

1. **Sélection** : récupère les events où `team1_id` ou `team2_id` est NULL
2. **Extraction regex** : parse le champ `market` avec le pattern `team1 vs team2` (gère les emojis et caractères spéciaux)
3. **Recherche d'équipes** : pour chaque nom extrait, cherche dans les équipes de la ligue de l'event (ou les ligues avec `priority > 0`)
4. **Algorithme de matching** :
   - Normalisation ASCII (translittération)
   - Correspondance sur tous les mots du nom recherché dans `name` ou `nickname`
5. **Mise à jour** : si au moins une équipe est trouvée, met à jour `team1_id`, `team2_id`, et `league_id` (si les deux équipes sont dans la même ligue)
6. **Sortie JSON** : affiche les résultats au format JSON

---

## Dépendances

- `App\Models\Event`
- `App\Models\League`
- `App\Models\Team`

---

## Exemples d'utilisation

```bash
# Extraire et associer les équipes pour tous les événements
php artisan bet:extract-market-all

# Simulation sans modification
php artisan bet:extract-market-all --dry-run

# Afficher uniquement les événements avec des équipes non trouvées
php artisan bet:extract-market-all --show-missing

# Simulation + affichage des manquants
php artisan bet:extract-market-all --dry-run --show-missing
```
