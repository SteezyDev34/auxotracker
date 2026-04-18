# `league:download-logos` — Télécharger les logos des ligues

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan league:download-logos` |
| **Classe** | `App\Console\Commands\DownloadLeagueLogos` |
| **Fichier** | `app/Console/Commands/DownloadLeagueLogos.php` |
| **Catégorie** | Assets / Logos de ligues |
| **API externe** | Sofascore (via `LeagueLogoService`) |

## Description

Télécharge les logos manquants des ligues depuis l'API Sofascore. Supporte les variantes **light** et **dark** des logos. Peut traiter une ligue spécifique ou toutes les ligues avec un `sofascore_id`.

---

## Signature

```bash
php artisan league:download-logos [league_id] [--force] [--empty-img] [--delay=0]
```

## Arguments

| Argument | Obligatoire | Description |
|---|---|---|
| `league_id` | Non | ID de la ligue spécifique à télécharger |

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Force le téléchargement même si les logos existent |
| `--empty-img` | `false` | Ne traiter que les ligues avec le champ `img` vide ou invalide |
| `--delay=` | `0` | Délai en secondes entre chaque requête API (défaut : 0.5s via `usleep`) |

---

## Fonctionnement

### Mode ligue unique (`league_id`)
1. Vérifie que la ligue existe et possède un `sofascore_id`
2. Appelle `LeagueLogoService::ensureLeagueLogos()`
3. Affiche le détail : logo light, dark, update du champ `img`

### Mode toutes les ligues (sans argument)
1. Sélectionne toutes les ligues ayant un `sofascore_id`
2. Si `--empty-img` : filtre les ligues sans image valide
3. Barre de progression
4. Pour chaque ligue, télécharge les logos light et/ou dark
5. Pause de 0.5s par défaut (ou le délai configuré)
6. Tableau de statistiques finales

---

## Statistiques affichées

| Statut | Description |
|---|---|
| Succès | Logo(s) téléchargé(s) avec succès |
| Échecs | Erreur de téléchargement |
| Ignorés | Logo déjà existant et non forcé |
| Champ img mis à jour | Le champ `img` de la ligue a été mis à jour |
| Light seulement | Seul le logo light a été téléchargé |
| Dark seulement | Seul le logo dark a été téléchargé |
| Light + Dark | Les deux variantes ont été téléchargées |

---

## Dépendances

- `App\Services\LeagueLogoService`
- `App\Models\League`

---

## Exemples d'utilisation

```bash
# Télécharger les logos d'une ligue spécifique
php artisan league:download-logos 42

# Télécharger tous les logos avec un délai de 2s
php artisan league:download-logos --delay=2

# Ne traiter que les ligues sans image
php artisan league:download-logos --empty-img

# Forcer le re-téléchargement complet
php artisan league:download-logos --force --delay=1
```
