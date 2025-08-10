# Système de Gestion des Logos d'Équipes

Ce système permet de télécharger automatiquement les logos des équipes depuis l'API Sofascore et de les stocker localement.

## Prérequis

- Les équipes doivent avoir un `sofascore_id` renseigné dans la base de données
- Le lien symbolique de stockage doit être créé : `php artisan storage:link`

## Structure de la Table Teams

```sql
CREATE TABLE `teams` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `nickname` varchar(255) DEFAULT NULL,
    `slug` varchar(255) DEFAULT NULL,
    `img` varchar(255) DEFAULT NULL,
    `sofascore_id` varchar(255) DEFAULT NULL,
    `league_id` bigint(20) unsigned NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `teams_league_id_foreign` (`league_id`),
    CONSTRAINT `teams_league_id_foreign` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Utilisation

### Via la ligne de commande

```bash
# Télécharger tous les logos manquants
php artisan team:download-logos

# Forcer le téléchargement même si les logos existent déjà
php artisan team:download-logos --force
```

### Via l'API REST

#### Vérifier le statut des logos
```http
GET /api/teams/logos/status
```

Réponse :
```json
{
    "success": true,
    "stats": {
        "total_teams": 50,
        "with_logo": 30,
        "without_logo": 20,
        "teams_without_logo": [
            {
                "id": 1,
                "name": "Paris Saint-Germain",
                "sofascore_id": "1234"
            }
        ]
    }
}
```

#### Télécharger tous les logos manquants
```http
POST /api/teams/logos/download-all
```

#### Télécharger le logo d'une équipe spécifique
```http
POST /api/teams/{teamId}/logo/download
```

## Stockage

- Les logos sont stockés dans : `storage/app/public/team_logos/`
- Format de fichier : `{team_id}.png`
- Accessible via : `public/storage/team_logos/{team_id}.png`

## Fonctionnalités

1. **Vérification automatique** : Le système vérifie si un logo existe déjà avant de le télécharger
2. **Gestion d'erreurs** : Logging complet des succès et échecs
3. **Limitation de débit** : Pause de 0.5 seconde entre chaque téléchargement pour éviter de surcharger l'API
4. **Mise à jour automatique** : Le champ `img` de l'équipe est automatiquement mis à jour avec le chemin du logo

## API Sofascore

L'URL utilisée pour télécharger les logos :
```
https://api.sofascore.com/api/v1/team/{sofascore_id}/image
```

## Logs

Tous les téléchargements et erreurs sont enregistrés dans les logs Laravel :
- Succès : `storage/logs/laravel.log`
- Erreurs : `storage/logs/laravel.log`

## Classes Principales

- **Service** : `App\Services\TeamLogoService`
- **Contrôleur** : `App\Http\Controllers\TeamLogoController`
- **Commande** : `App\Console\Commands\DownloadTeamLogos`
- **Modèle** : `App\Models\Team`