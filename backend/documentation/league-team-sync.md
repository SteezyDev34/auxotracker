# `league-team:sync` — Synchroniser la table pivot league_team

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan league-team:sync` |
| **Classe** | `App\Console\Commands\SyncLeagueTeamPivot` |
| **Fichier** | `app/Console/Commands/SyncLeagueTeamPivot.php` |
| **Catégorie** | Maintenance / Base de données |

## Description

Synchronise la table pivot `league_team` à partir du champ `league_id` de chaque équipe dans la table `teams`. Permet de reconstruire la relation many-to-many entre les ligues et les équipes à partir de la relation one-to-many existante.

---

## Signature

```bash
php artisan league-team:sync [--rebuild]
```

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--rebuild` | `false` | Vider complètement la table pivot avant reconstruction (TRUNCATE) |

---

## Fonctionnement

1. Si `--rebuild` : exécute un `TRUNCATE` de la table `league_team`
2. Charge les équipes par chunks de 200 (optimisation mémoire)
3. Pour chaque chunk, prépare un tableau d'insertions `{league_id, team_id, created_at, updated_at}`
4. Utilise `insertOrIgnore` pour éviter les erreurs de clé dupliquée

---

## Dépendances

- `App\Models\Team`
- `Illuminate\Support\Facades\DB`

---

## Exemples d'utilisation

```bash
# Synchroniser (ajouter les entrées manquantes)
php artisan league-team:sync

# Reconstruction complète de la table pivot
php artisan league-team:sync --rebuild
```

---

## Notes

- `insertOrIgnore` : les doublons existants sont silencieusement ignorés.
- Le compteur d'insertions est approximatif car `insertOrIgnore` peut retourner des valeurs variables selon le driver DB.
- **Attention** : `--rebuild` effectue un `TRUNCATE`, opération irréversible.
