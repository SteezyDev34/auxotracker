# `teams:update:nicknames` — Mettre à jour les nicknames des équipes

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan teams:update:nicknames` |
| **Classe** | `App\Console\Commands\UpdateTeamNicknames` |
| **Fichier** | `app/Console/Commands/UpdateTeamNicknames.php` |
| **Catégorie** | Maintenance / Équipes |

## Description

Parcourt toutes les équipes et ajoute des nicknames (surnoms) en se basant sur les correspondances trouvées dans le fichier `backend/api.json`. Le fichier JSON contient un mapping clé-valeur qui est croisé avec les champs `slug`, `name`, et `nickname` de chaque équipe.

---

## Signature

```bash
php artisan teams:update:nicknames [--dry-run] [--slug=]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--dry-run` | `false` | Ne pas écrire en base, afficher les changements proposés |
| `--slug=` | `null` | Filtrer par slug d'équipe spécifique |

---

## Fonctionnement détaillé

1. **Lecture du fichier** : charge `backend/api.json` (un objet clé-valeur)
2. **Construction des tables** :
   - `keyToValue` : clé normalisée → valeur (ex: `"psg"` → `"Paris Saint-Germain"`)
   - `valueToKeys` : valeur normalisée → liste de clés
3. **Pour chaque équipe** :
   - Cherche des correspondances par `slug` dans les clés du JSON
   - Cherche par `name` dans les clés
   - Cherche chaque partie du `nickname` existant (séparé par virgules) dans les clés
   - Collecte les candidats (valeurs correspondantes)
   - Fusionne avec les nicknames existants (dédupliqué)
   - Si le nouveau nickname diffère de l'actuel → mise à jour
4. **Statistiques** : nombre d'équipes mises à jour

---

## Spécificités

- **Memory limit** : défini à `-1` (illimité) pour supporter les gros fichiers JSON
- **Nicknames existants préservés** : les nouveaux nicknames sont fusionnés avec les existants, jamais remplacés
- **Séparateur** : les nicknames multiples sont séparés par `, ` (virgule + espace)

---

## Dépendances

- `App\Models\Team`
- Fichier `backend/api.json`

---

## Exemples d'utilisation

```bash
# Simulation (afficher sans modifier)
php artisan teams:update:nicknames --dry-run

# Mise à jour effective
php artisan teams:update:nicknames

# Tester pour une équipe spécifique
php artisan teams:update:nicknames --slug=paris-saint-germain --dry-run
```
