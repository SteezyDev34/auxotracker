# `bankroll:adjust-stakes` — Ajustement des mises

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan bankroll:adjust-stakes` |
| **Classe** | `App\Console\Commands\AdjustStakes` |
| **Fichier** | `app/Console/Commands/AdjustStakes.php` |
| **Catégorie** | Bankroll / Gestion des mises |

## Description

Ajuste automatiquement les stakes (mises) de tous les paris d'une bankroll selon une stratégie de money management prédéfinie. Deux stratégies sont disponibles : **recovery** (récupération des pertes) et **simple** (mise fixe proportionnelle).

---

## Signature

```bash
php artisan bankroll:adjust-stakes {bankroll_id} [--sport-id=] [--strategy=recovery] [--dry-run]
```

## Arguments

| Argument | Obligatoire | Description |
|---|---|---|
| `bankroll_id` | ✅ Oui | ID de la bankroll dont les mises doivent être ajustées |

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--sport-id=` | `null` (tous les sports) | ID du sport à traiter. Si non spécifié, traite tous les sports |
| `--strategy=` | `recovery` | Stratégie de mise à utiliser : `recovery` ou `simple` |
| `--dry-run` | `false` | Mode simulation : affiche les changements sans les sauvegarder en base |

---

## Stratégies disponibles

### 1. Stratégie `recovery` (Récupération)

- **Objectif par pari** : 0.1% du capital actuel
- **Mécanisme** : En cas de perte, le prochain pari intègre la récupération de la mise perdue (mise × cote) + l'objectif de base de 0.1%
- **Formule de la mise** : `mise = objectif_gain / (cote - 1)`
- **En cas de gain après récupération** : le compteur de perte est remis à zéro
- **En cas de nouvelle perte** : la perte à récupérer est mise à jour (`mise × cote`)

### 2. Stratégie `simple` (Mise fixe)

- **Objectif par pari** : 0.5% du capital actuel (note : le code utilise `0.01` soit 1%, vérifier si c'est intentionnel)
- **Mécanisme** : Aucune récupération des pertes. La mise est recalculée à chaque pari en fonction du capital courant
- **Formule de la mise** : `mise = (capital × pourcentage) / (cote - 1)`

---

## Fonctionnement détaillé

1. **Validation** : vérifie que la bankroll existe et que la stratégie est valide
2. **Chargement des paris** : récupère tous les paris de la bankroll, ordonnés par date puis par ID
3. **Filtrage optionnel** : applique le filtre par sport si `--sport-id` est fourni
4. **Calcul itératif** : pour chaque pari, calcule la nouvelle mise en fonction du capital courant
5. **Mise à jour du capital** : après chaque pari, ajuste le capital selon le résultat (`win`, `lost`, `void`)
6. **Sauvegarde** : si la différence entre l'ancienne et la nouvelle mise dépasse 0.01€, met à jour en base (sauf en `--dry-run`)

---

## Modèles utilisés

- `App\Models\UserBankroll` — pour récupérer la bankroll et le capital initial
- `App\Models\Bet` — pour les paris à ajuster
- `App\Models\Sport` — pour le filtrage par sport

---

## Sortie console

Pour chaque pari traité, affiche :
- ID du pari, date, code
- Capital courant
- Mode (BASE ou RÉCUPÉRATION)
- Montant à récupérer (si applicable)
- Cote
- Ancien stake → Nouveau stake
- Gain potentiel brut et bénéfice net
- Résultat du pari

---

## Exemples d'utilisation

```bash
# Ajuster avec la stratégie recovery (défaut)
php artisan bankroll:adjust-stakes 1

# Ajuster avec la stratégie simple
php artisan bankroll:adjust-stakes 1 --strategy=simple

# Simulation sans modification en base
php artisan bankroll:adjust-stakes 1 --dry-run

# Ajuster uniquement les paris de football (sport_id=3)
php artisan bankroll:adjust-stakes 1 --sport-id=3

# Combinaison complète
php artisan bankroll:adjust-stakes 1 --sport-id=3 --strategy=recovery --dry-run
```

---

## Notes et précautions

- La commande modifie directement le champ `stake` des paris en base de données. Toujours tester avec `--dry-run` avant d'appliquer.
- Le capital initial est celui enregistré dans `bankroll_start_amount`.
- Les paris avec résultat `void` ne modifient pas le capital.
- L'ordre de traitement (par date puis ID) est crucial pour la cohérence des calculs.
