# `team:download-logos` — Télécharger les logos d'équipes

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan team:download-logos` |
| **Classe** | `App\Console\Commands\DownloadTeamLogos` |
| **Fichier** | `app/Console/Commands/DownloadTeamLogos.php` |
| **Catégorie** | Assets / Logos d'équipes |
| **API externe** | Sofascore (via `TeamLogoService`) |

## Description

Télécharge les logos manquants des équipes depuis l'API Sofascore. Offre de nombreuses options de filtrage : par équipe, par ligue, par champ image vide, avec gestion fine du délai API.

---

## Signature

```bash
php artisan team:download-logos [team_id] [--force] [--empty-img] [--league=] [--all-league] [--delay=1]
```

## Arguments

| Argument | Obligatoire | Description |
|---|---|---|
| `team_id` | Non | ID de l'équipe spécifique |

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Force le téléchargement même si le logo existe |
| `--empty-img` | `false` | Ne traiter que les équipes avec le champ `img` vide ou sans `team_logos` |
| `--league=` | `null` | ID de la ligue pour filtrer les équipes |
| `--all-league` | `false` | Télécharger les logos de toutes les équipes de la ligue, même si elles ont déjà un logo |
| `--delay=` | `1` | Délai en secondes entre chaque requête API |

---

## Fonctionnement

### Mode équipe unique (`team_id`)
1. Vérifie l'existence et le `sofascore_id`
2. Vérifie si le logo existe (sauf `--force`)
3. Télécharge via `TeamLogoService::ensureTeamLogo()`

### Mode batch (sans `team_id`)
1. Sélectionne les équipes avec `sofascore_id` non null
2. Applique les filtres : `--empty-img`, `--league`
3. Tri par ID décroissant
4. Pour chaque équipe :
   - Si `--all-league` : télécharge systématiquement
   - Sinon : vérifie l'existence du logo avant téléchargement
5. Barre de progression + tableau statistiques

---

## Dépendances

- `App\Services\TeamLogoService`
- `App\Models\Team`
- `Illuminate\Support\Facades\Storage` (disque `public`)

---

## Exemples d'utilisation

```bash
# Télécharger le logo d'une équipe spécifique
php artisan team:download-logos 123

# Télécharger les logos manquants des équipes d'une ligue
php artisan team:download-logos --league=42 --empty-img

# Tout re-télécharger pour une ligue
php artisan team:download-logos --league=42 --all-league --force

# Téléchargement global avec délai de 2s
php artisan team:download-logos --delay=2
```
