# Liste des Sports et leurs Slugs Sofascore

Cette liste contient les différents sports disponibles sur Sofascore avec leurs slugs correspondants pour l'importation des ligues.

## Sports Disponibles

### Sports Principaux
- **Football** : `football`
- **Basketball** : `basketball`
- **Hockey sur glace** : `ice-hockey`
- **Tennis** : `tennis`
- **Baseball** : `baseball`
- **Football américain** : `american-football`

### Sports de Raquette
- **Tennis de table** : `table-tennis`
- **Badminton** : `badminton`
- **Squash** : `squash`

### Sports Aquatiques
- **Water-polo** : `waterpolo`
- **Natation** : `swimming`

### Sports de Combat
- **Boxe** : `boxing`
- **MMA** : `mma`

### Autres Sports
- **Snooker** : `snooker`
- **Futsal** : `futsal`
- **Fléchettes** : `darts`
- **Cricket** : `cricket`
- **Rugby** : `rugby`
- **Handball** : `handball`
- **Volleyball** : `volleyball`
- **Esports** : `esports`

## Utilisation

Pour importer les ligues d'un sport spécifique, utilisez la commande :

```bash
php artisan sport:import-leagues {sport_slug} --force
```

### Exemples

```bash
# Importer les ligues de football
php artisan sport:import-leagues football --force

# Importer les ligues de basketball
php artisan sport:import-leagues basketball --force

# Importer les ligues de hockey sur glace
php artisan sport:import-leagues ice-hockey --force

# Importer les ligues de tennis de table
php artisan sport:import-leagues table-tennis --force
```

## Notes

- Les slugs sont basés sur l'API Sofascore : `https://www.sofascore.com/api/v1/sport/{sport_slug}/categories`
- Certains sports peuvent ne pas avoir de ligues disponibles dans tous les pays
- La commande `--force` permet de forcer l'importation même si des ligues existent déjà

## Vérification des Sports Disponibles

Pour vérifier qu'un sport existe dans la base de données avant l'importation, vous pouvez consulter la table `sports` ou utiliser la commande d'importation qui vérifiera automatiquement l'existence du sport.