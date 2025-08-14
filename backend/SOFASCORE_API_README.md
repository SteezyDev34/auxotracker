# Guide d'utilisation de l'importation des ligues depuis Sofascore

## Améliorations apportées pour contourner les restrictions 403

La commande d'importation des ligues depuis l'API Sofascore a été améliorée pour contourner les restrictions 403. Voici les principales modifications :

### 1. Rotation des User-Agents

La commande utilise maintenant une rotation aléatoire de User-Agents pour chaque requête, ce qui permet de simuler différents navigateurs et appareils.

### 2. Délais aléatoires

Des délais aléatoires ont été ajoutés entre les requêtes pour éviter d'être détecté comme un bot.

### 3. En-têtes HTTP améliorés

Les en-têtes HTTP ont été enrichis pour ressembler davantage à ceux d'un navigateur réel :
- Accept-Language
- Origin
- Sec-Fetch-* headers
- Cache-Control
- Pragma

### 4. Système de retry avec backoff exponentiel

En cas d'échec d'une requête, la commande réessaie automatiquement avec un délai exponentiel entre chaque tentative.

### 5. Mise en cache des réponses

Les réponses de l'API sont mises en cache pendant 24 heures pour réduire le nombre de requêtes et éviter d'être bloqué.

## Utilisation de la commande

```bash
php artisan sport:import-leagues {sport_slug} [options]
```

### Arguments

- `sport_slug` : Le slug du sport à importer (ex: football, basketball, tennis)

### Options

- `--force` : Force l'importation même si les ligues existent déjà
- `--no-cache` : Désactive l'utilisation du cache (toutes les requêtes seront faites directement à l'API)

### Exemples

```bash
# Importer les ligues de football
php artisan sport:import-leagues football

# Importer les ligues de basketball en forçant la mise à jour
php artisan sport:import-leagues basketball --force

# Importer les ligues de tennis sans utiliser le cache
php artisan sport:import-leagues tennis --no-cache

# Importer les ligues de volleyball en forçant la mise à jour et sans utiliser le cache
php artisan sport:import-leagues volleyball --force --no-cache
```

## Dépannage

Si vous rencontrez toujours des erreurs 403 malgré ces améliorations, voici quelques suggestions supplémentaires :

1. **Utiliser un proxy** : Configurez un proxy rotatif pour changer d'adresse IP à chaque requête
2. **Augmenter les délais** : Modifiez la méthode `addRandomDelay()` pour augmenter les délais entre les requêtes
3. **Ajouter plus de User-Agents** : Enrichissez la liste des User-Agents dans la propriété `$userAgents`
4. **Vérifier les cookies** : Certaines API nécessitent des cookies spécifiques pour fonctionner
5. **Utiliser un service d'API scraping** : Envisagez d'utiliser un service tiers comme ScrapingBee ou ScraperAPI

## Maintenance du cache

Les fichiers de cache sont stockés dans le répertoire `storage/app/sofascore_cache`. Vous pouvez les supprimer manuellement si nécessaire :

```bash
rm -rf storage/app/sofascore_cache/*
```