# `bet:extract-market` — Extraire le marché d'un texte de pari

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan bet:extract-market` |
| **Classe** | `App\Console\Commands\ExtractMarketFromBet` |
| **Fichier** | `app/Console/Commands/ExtractMarketFromBet.php` |
| **Catégorie** | Paris / Utilitaire |

## Description

Commande utilitaire qui extrait le nom des équipes d'un texte de pari au format "Équipe1 vs Équipe2", puis tente d'associer chaque équipe à un enregistrement en base de données. Utile pour le debug et la vérification du parsing.

---

## Signature

```bash
php artisan bet:extract-market {text} [league_id]
```

## Arguments

| Argument | Obligatoire | Description |
|---|---|---|
| `text` | ✅ Oui | Texte brut du pari contenant "Équipe1 vs Équipe2" |
| `league_id` | Non | ID de la ligue pour restreindre la recherche d'équipes |

---

## Fonctionnement

1. **Extraction regex** : parse le texte avec un pattern unicode supportant les emojis
2. **Recherche d'équipes** : si `league_id` fourni, cherche dans cette ligue ; sinon, dans toutes les ligues avec `priority > 0`
3. **Matching** : recherche par `LIKE %nom%` sur le champ `name`
4. **Sortie JSON** : affiche le résultat avec `market`, noms extraits, et IDs trouvés

---

## Sortie JSON

```json
{
    "market": "texte original",
    "team1_name": "Équipe1",
    "team2_name": "Équipe2",
    "team1_id": 123,
    "team2_id": 456
}
```

---

## Exemples d'utilisation

```bash
# Extraire les équipes d'un texte
php artisan bet:extract-market "⚽ PSG vs Marseille - Ligue 1"

# Restreindre la recherche à une ligue spécifique
php artisan bet:extract-market "Real Madrid vs Barcelona" 42
```
