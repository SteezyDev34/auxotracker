# `bankroll:list` — Lister les bankrolls

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan bankroll:list` |
| **Classe** | `App\Console\Commands\ListBankrolls` |
| **Fichier** | `app/Console/Commands/ListBankrolls.php` |
| **Catégorie** | Bankroll / Consultation |

## Description

Affiche une liste tabulée de toutes les bankrolls disponibles dans le système, avec leurs informations clés : capital initial, bénéfices, nombre de paris, utilisateur associé.

---

## Signature

```bash
php artisan bankroll:list [--user=]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--user=` | `null` (tous les utilisateurs) | ID de l'utilisateur pour filtrer les bankrolls |

---

## Fonctionnement

1. Charge toutes les bankrolls avec les relations `user` et `bets` (eager loading)
2. Applique le filtre par utilisateur si `--user` est fourni
3. Affiche un tableau avec les colonnes : ID, Nom, Utilisateur, Capital Initial, Bénéfices, Nb Paris, Date de création
4. Affiche un rappel de la commande `bankroll:adjust-stakes`

---

## Modèles utilisés

- `App\Models\UserBankroll`
- `App\Models\User`

---

## Sortie console

Tableau formaté avec les colonnes :

| ID | Nom | Utilisateur | Capital Initial | Bénéfices | Nb Paris | Créée le |
|---|---|---|---|---|---|---|

---

## Exemples d'utilisation

```bash
# Lister toutes les bankrolls
php artisan bankroll:list

# Lister les bankrolls d'un utilisateur spécifique
php artisan bankroll:list --user=1
```
