# `sport:import-leagues` — Importeur générique de ligues par sport

## Informations générales

| Propriété | Valeur |
|---|---|
| **Commande** | `php artisan sport:import-leagues` |
| **Classe** | `App\Console\Commands\ImportSportLeagues` |
| **Fichier** | `app/Console/Commands/ImportSportLeagues.php` |
| **Catégorie** | Import / Générique |
| **API externe** | Sofascore (`/sport/{slug}/categories` + `/category/{id}/unique-tournaments`) |

## Description

Commande générique qui importe les ligues pour **n'importe quel sport** en se basant sur le slug du sport dans l'API Sofascore. C'est la version la plus flexible et réutilisable des importeurs de ligues.

---

## Signature

```bash
php artisan sport:import-leagues {sport_slug} [--force] [--no-cache]
```

## Arguments

| Argument | Obligatoire | Description |
|---|---|---|
| `sport_slug` | ✅ Oui | Slug du sport dans l'API Sofascore (ex: `football`, `basketball`, `tennis`, `handball`, `rugby`, `ice-hockey`, `baseball`, `volleyball`, etc.) |

## Options

| Option | Valeur par défaut | Description |
|---|---|---|
| `--force` | `false` | Forcer l'import même si la ligue existe |
| `--no-cache` | `false` | Ne pas utiliser le cache |

---

## Fonctionnement détaillé

1. **Initialisation du cache** : crée le répertoire `storage/app/sofascore_cache/leagues_{sport_slug}/`
2. **Récupération des catégories** : appel à `/sport/{slug}/categories` avec User-Agent aléatoire
3. **Détection automatique du sport** : extrait le `sofascore_id` depuis la réponse API
4. **Pour chaque pays** :
   - Cherche/crée le pays en base
   - Gère les catégories spéciales (ex: "In Progress" → ignorée)
   - Récupère les ligues via `/category/{id}/unique-tournaments`
   - Crée/met à jour chaque ligue
5. **Statistiques** avec taux de succès

---

## Spécificités

- **Rotation de User-Agent** : 7 User-Agents différents pour éviter le blocage
- **Délai aléatoire** : entre 1 et 3 secondes entre chaque requête API
- **Système de cache** : cache des réponses API avec clé MD5, réponses simulées depuis le cache
- **Gestion des pays ignorés** : catégories comme "In Progress" sont silencieusement ignorées

---

## Système de cache

- **Répertoire** : `storage/app/sofascore_cache/leagues_{sport_slug}/`
- **Fichiers** : `{md5_url}.json`
- Chaque fichier contient : `url`, `status`, `headers`, `body`, `cached_at`
- Pas d'expiration de cache : le cache est permanent sauf si `--no-cache`

---

## Dépendances

- `App\Models\League`, `Country`, `Sport`

---

## Exemples d'utilisation

```bash
# Import des ligues de football
php artisan sport:import-leagues football

# Import des ligues de baseball (sport non couvert par les commandes spécifiques)
php artisan sport:import-leagues baseball

# Import forcé des ligues de volleyball
php artisan sport:import-leagues volleyball --force

# Sans utiliser le cache
php artisan sport:import-leagues cricket --no-cache
```

---

## Sports supportés (non exhaustif)

Tout sport disponible sur Sofascore avec le slug correspondant :
- `football`, `basketball`, `tennis`, `handball`, `rugby`, `ice-hockey`
- `baseball`, `volleyball`, `cricket`, `table-tennis`, `badminton`
- `american-football`, `futsal`, `waterpolo`, `darts`, `snooker`
- `mma`, `motorsport`, `cycling`, `aussie-rules`, etc.
