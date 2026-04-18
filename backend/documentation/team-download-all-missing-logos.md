# `team:download-all-missing-logos` — Télécharger tous les logos manquants

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan team:download-all-missing-logos` |
| **Classe** | `App\Console\Commands\DownloadAllMissingTeamLogos` |
| **Fichier** | `app/Console/Commands/DownloadAllMissingTeamLogos.php` |
| **Catégorie** | Assets / Logos d'équipes |
| **API externe** | Sofascore (via `TeamLogoService`) |

## Description

Télécharge automatiquement les logos manquants pour toutes les équipes qui n'ont pas d'image enregistrée. La commande identifie les équipes sans logo (ou avec un chemin invalide) et les télécharge depuis l'API Sofascore.

---

## Signature

```bash
php artisan team:download-all-missing-logos [--limit=100] [--delay=2] [--force]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--limit=` | `100` | Nombre maximum d'équipes à traiter |
| `--delay=` | `2` | Délai en secondes entre chaque requête API (pour éviter le rate-limiting) |
| `--force` | `false` | Force le téléchargement même si le logo existe déjà |

---

## Fonctionnement détaillé

1. **Sélection des équipes** : récupère les équipes ayant un `sofascore_id` et dont le champ `img` est vide, null, ou ne contient pas `team_logos` (sauf si `--force`)
2. **Tri** : les équipes sont triées par ID décroissant (les plus récentes d'abord)
3. **Confirmation** : si plus de 50 équipes sont à traiter, demande confirmation à l'utilisateur
4. **Téléchargement** : pour chaque équipe, appelle `TeamLogoService::ensureTeamLogo()`
5. **Pause** : applique le délai configuré entre chaque requête API
6. **Statistiques finales** : tableau avec succès, échecs, ignorés, déjà existants et pourcentages

---

## Dépendances

- `App\Services\TeamLogoService` — service de téléchargement des logos
- `App\Models\Team`
- `Illuminate\Support\Facades\Storage` (disque `public`)

---

## Sortie console

- Barre de progression avec format `debug`
- Tableau de statistiques final avec pourcentages

---

## Exemples d'utilisation

```bash
# Télécharger les logos manquants (100 max, 2s entre chaque)
php artisan team:download-all-missing-logos

# Limiter à 20 équipes avec délai réduit
php artisan team:download-all-missing-logos --limit=20 --delay=1

# Forcer le re-téléchargement de tous les logos
php artisan team:download-all-missing-logos --force --limit=500
```

---

## Notes

- Le délai de 2 secondes par défaut est configuré pour éviter les erreurs 403 de l'API Sofascore.
- Les logos sont stockés sur le disque `public` de Laravel Storage.
- Code de retour : `0` si tout est OK, `1` si des échecs ont été rencontrés.
