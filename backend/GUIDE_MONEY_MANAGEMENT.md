# Guide des Commandes de Money Management

## Vue d'ensemble

Ces commandes permettent d'ajuster automatiquement les stakes (mises) d'une bankroll selon différentes stratégies de money management.

## Commandes disponibles

### 1. Lister les bankrolls

```bash
php artisan bankroll:list [--user=ID]
```

**Options :**

-   `--user=ID` : Filtrer par utilisateur (optionnel)

**Exemple :**

```bash
php artisan bankroll:list
php artisan bankroll:list --user=1
```

### 2. Ajuster les stakes

```bash
php artisan bankroll:adjust-stakes {bankroll_id} {strategy} [--dry-run]
```

**Paramètres :**

-   `bankroll_id` : ID de la bankroll à traiter
-   `strategy` : Stratégie à appliquer (`recovery` ou `martingale`)
-   `--dry-run` : Mode simulation (aucune modification sauvegardée)

**Exemples :**

```bash
# Test en mode simulation
php artisan bankroll:adjust-stakes 1 recovery --dry-run
php artisan bankroll:adjust-stakes 1 martingale --dry-run

# Application réelle
php artisan bankroll:adjust-stakes 1 recovery
php artisan bankroll:adjust-stakes 1 martingale
```

## Stratégies disponibles

### 1. Stratégie de Récupération (`recovery`)

**Principe :**

-   Objectif de gain : 1% du capital actuel
-   En cas de perte : ajoute le gain manqué aux objectifs futurs
-   En cas de gain : réduit les gains manqués précédents

**Formule :**

```
Gain cible = (Capital × 0.5%) + Gains manqués
Stake = Gain cible ÷ (Cote - 1)
```

**Avantages :**

-   Récupération progressive des pertes
-   Adaptation au capital disponible
-   Gestion des séries de pertes

### 2. Stratégie Martingale Modifiée (`martingale`)

**Principe :**

-   Base : 0.5% du capital actuel
-   En cas de perte : double le multiplicateur
-   En cas de gain : divise le multiplicateur par 2 (minimum 1x)

**Formule :**

```
Stake base = Capital × 0.5%
Stake final = Stake base × Multiplicateur
```

**Avantages :**

-   Récupération rapide après gain
-   Limitation des risques par la base 0.5%
-   Adaptation dynamique du risque

## Fonctionnalités techniques

### Protection des données

-   Mode `--dry-run` pour simulation
-   Validation des paramètres d'entrée
-   Vérification de l'existence de la bankroll
-   Gestion des erreurs de division par zéro

### Traitement des résultats

-   **win** : Gain ajouté au capital
-   **lost** : Stake déduite du capital
-   **void** : Aucun impact sur le capital
-   **pending** : Aucun impact sur le capital

### Logs et suivi

-   Affichage détaillé de chaque pari traité
-   Compteur des modifications effectuées
-   Informations sur l'évolution du capital
-   Suivi des gains manqués (stratégie recovery)

## Exemples pratiques

### Scénario 1 : Test initial

```bash
# 1. Lister les bankrolls disponibles
php artisan bankroll:list

# 2. Tester la stratégie recovery
php artisan bankroll:adjust-stakes 1 recovery --dry-run

# 3. Comparer avec martingale
php artisan bankroll:adjust-stakes 1 martingale --dry-run
```

### Scénario 2 : Application en production

```bash
# 1. Sauvegarder la base de données
mysqldump -u user -p database > backup.sql

# 2. Appliquer la stratégie choisie
php artisan bankroll:adjust-stakes 1 recovery

# 3. Vérifier les résultats
php artisan bankroll:list
```

## Limitations et précautions

### Limitations

-   ⚠️ **Commandes temporaires** : Destinées à des ajustements ponctuels
-   ⚠️ **Pas de rollback automatique** : Sauvegarder avant utilisation
-   ⚠️ **Traitement séquentiel** : Un pari à la fois par ordre de date

### Précautions

-   🔄 **Toujours tester avec `--dry-run`** avant application
-   💾 **Sauvegarder la base de données** avant modifications
-   📊 **Vérifier la cohérence** des cotes et résultats
-   🎯 **Adapter la stratégie** selon le profil de risque

## Notes techniques

### Performance

-   Traitement optimisé par lots
-   Utilisation d'Eloquent pour la sécurité
-   Gestion mémoire pour grandes bankrolls

### Sécurité

-   Validation des entrées utilisateur
-   Protection contre l'injection SQL
-   Logs des opérations critiques

### Extensibilité

-   Architecture modulaire pour nouvelles stratégies
-   Interface standardisée pour les calculs
-   Configuration centralisée des paramètres
