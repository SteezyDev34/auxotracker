# `bets:import-json` — Importer des paris depuis un fichier JSON

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan bets:import-json` |
| **Classe** | `App\Console\Commands\ImportBetsFromJson` |
| **Fichier** | `app/Console/Commands/ImportBetsFromJson.php` |
| **Catégorie** | Import / Paris |

## Description

Importe des paris depuis un fichier JSON dans la base de données. Crée automatiquement les paris (`Bet`) et leurs événements (`Event`) associés, avec détection automatique du sport, conversion des statuts, et gestion des encodages.

---

## Signature

```bash
php artisan bets:import-json {file} [--user-id=] [--bankroll-id=] [--dry-run]
```

## Arguments

| Argument | Obligatoire | Description |
|---|---|---|
| `file` | ✅ Oui | Chemin vers le fichier JSON contenant les paris |

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--user-id=` | `null` | ID de l'utilisateur propriétaire |
| `--bankroll-id=` | `null` | ID de la bankroll cible |
| `--dry-run` | `false` | Simulation sans sauvegarde en base |

---

## Format du fichier JSON attendu

```json
[
  {
    "date": "15/03/2025",
    "hour": "20:00",
    "statut": "GAGNÉ",
    "mise": "10 €",
    "cote": "1,85",
    "sport": "FOOTBALL",
    "tournoi": "Ligue 1",
    "description": "PSG vs Marseille"
  }
]
```

---

## Fonctionnement détaillé

1. **Validation** : vérifie l'existence du fichier et la validité du JSON
2. **Sélection de la bankroll** :
   - Par `--bankroll-id` si fourni
   - Par `--user-id` (première bankroll de l'utilisateur)
   - Sinon, affiche la liste et demande un choix interactif
3. **Pour chaque pari** (dans une transaction DB) :
   - Parse la date (`d/m/Y`) et l'heure
   - Convertit le statut (GAGNÉ → win, PERDU → lost, REMBOURSÉ → void, EN COURS → pending)
   - Extrait la mise et la cote (gestion virgule/point)
   - Détecte automatiquement le sport via le mapping intégré
   - Génère un code de pari unique
   - Crée le `Bet` et l'`Event`, puis les lie via la table pivot
4. **Statistiques** : total, succès, erreurs, ignorés

---

## Mapping des sports

Le système détecte automatiquement le sport à partir des champs `sport`, `tournoi`, ou `description` :

| Mot-clé | Sport ID |
|---|---|
| FOOT, FOOTBALL, SOCCER | 3 |
| TENNIS | 2 |
| BASKET, BASKETBALL | 4 |
| RUGBY | 5 |
| HANDBALL | 8 |
| HOCKEY, ICE HOCKEY | 9 |
| NBA, NFL, MLB... | Correspondant |

---

## Dépendances

- `App\Models\Bet`, `Sport`, `UserBankroll`, `Event`, `League`, `Country`, `Team`
- `Carbon\Carbon` pour le parsing de dates

---

## Exemples d'utilisation

```bash
# Import dans une bankroll spécifique
php artisan bets:import-json pronos.json --bankroll-id=1

# Import avec choix interactif de la bankroll
php artisan bets:import-json pronos.json

# Simulation
php artisan bets:import-json pronos.json --bankroll-id=1 --dry-run

# Import pour un utilisateur spécifique
php artisan bets:import-json pronos.json --user-id=1
```

---

## Notes

- Les dates sont attendues au format `d/m/Y` (ex: `15/03/2025`)
- Les cotes utilisent la virgule comme séparateur décimal (ex: `1,85`)
- Les mises peuvent contenir le symbole `€` et des espaces
- La commande gère les problèmes d'encodage (ISO-8859-1, Windows-1252 → UTF-8)
