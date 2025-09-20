# Documentation Complète du Projet NEW BET TRACKER

## Vue d'ensemble

NEW BET TRACKER est une application web complète de gestion et de suivi des paris sportifs, développée avec une architecture moderne séparant le frontend et le backend. L'application permet aux utilisateurs de gérer leurs paris, analyser leurs performances, et utiliser des outils de calcul avancés pour optimiser leurs stratégies de paris.

## Architecture Générale

### Structure du Projet
```
NEW BET TRACKER/
├── frontend/          # Application Vue.js
├── backend/           # API Laravel
├── GUIDE_AUTHENTIFICATION.md
└── DOCUMENTATION_PROJET.md
```

### Technologies Utilisées

#### Frontend
- **Framework**: Vue.js 3.4.34 avec Composition API
- **Build Tool**: Vite 5.3.1
- **UI Framework**: PrimeVue 4.2.4 avec thème Aura
- **CSS Framework**: Tailwind CSS 3.4.6
- **Routing**: Vue Router 4.4.0
- **HTTP Client**: Axios 1.7.9
- **Validation**: Vuelidate 2.0.3
- **Charts**: Chart.js 3.3.2
- **Icons**: PrimeIcons 7.0.0 + FontAwesome 6.7.2
- **Date Handling**: date-fns 4.1.0

#### Backend
- **Framework**: Laravel 10.10
- **PHP Version**: 8.2+
- **Authentication**: Laravel Sanctum 3.3
- **HTTP Client**: Guzzle 7.2
- **Containerization**: Docker avec Apache

## Frontend - Application Vue.js

### Configuration et Structure

#### Point d'entrée (`main.js`)
- Configuration de PrimeVue avec thème Aura
- Support du mode sombre (`.app-dark`)
- Services Toast et Confirmation
- Palette de couleurs personnalisée (emerald/slate)

#### Architecture des Composants
```
src/
├── components/        # Composants réutilisables
│   ├── dashboard/     # Widgets du tableau de bord
│   ├── landing/       # Composants de la page d'accueil
│   └── *.vue         # Composants génériques
├── layout/           # Structure de l'application
│   ├── AppLayout.vue # Layout principal
│   ├── AppMenu.vue   # Menu de navigation
│   └── composables/  # Logique réutilisable
├── views/            # Pages de l'application
│   ├── profile/      # Gestion du profil utilisateur
│   ├── pages/        # Pages génériques
│   └── uikit/        # Documentation des composants
├── router/           # Configuration des routes
├── service/          # Services API
└── assets/           # Ressources statiques
```

### Fonctionnalités Principales

#### 1. Tableau de Bord (`Dashboard.vue`)
**Description**: Interface principale de l'application offrant une vue d'ensemble complète de l'activité de paris de l'utilisateur.

**Fonctionnement détaillé**:
- **Évolution du Capital**: Graphique linéaire interactif affichant l'évolution du capital dans le temps avec Chart.js
  - Calcul automatique des gains/pertes cumulés
  - Filtrage par période (7 jours, 1 mois, 3 mois, 1 an)
  - Affichage des points de données au survol
  - Indicateurs de tendance (hausse/baisse)

- **Widgets de Statistiques**: Cartes d'information en temps réel
  - Capital actuel avec pourcentage de variation
  - Nombre total de paris placés
  - Taux de réussite global
  - Profit/Perte de la période sélectionnée
  - ROI (Return on Investment) calculé automatiquement

- **Historique des Paris**: Table interactive des derniers paris
  - Affichage des 10 derniers paris par défaut
  - Statut en temps réel (En cours, Gagné, Perdu, Annulé)
  - Calcul automatique des gains potentiels
  - Liens directs vers les détails de chaque pari

- **Graphiques Avancés**: Visualisations supplémentaires
  - Répartition des paris par sport (graphique en secteurs)
  - Performance par bookmaker
  - Analyse des tendances mensuelles

#### 2. Gestion du Profil (`profile/`)
**Description**: Module complet de gestion des informations personnelles et des préférences utilisateur.

**Fonctionnement détaillé**:

##### **Informations Personnelles** (`infos.vue`)
- **Gestion de l'Avatar**:
  - Upload d'image avec prévisualisation
  - Redimensionnement automatique (150x150px)
  - Formats supportés: JPG, PNG, GIF
  - Stockage sécurisé sur le serveur

- **Paramètres de Localisation**:
  - **Langue**: Sélection parmi les langues disponibles (français par défaut)
  - **Devise**: Configuration de la devise principale pour l'affichage des montants
  - **Fuseau Horaire**: Ajustement automatique des heures d'affichage
  - **Format de Date**: Personnalisation du format d'affichage des dates

- **Préférences d'Affichage**:
  - Page d'accueil par défaut après connexion
  - Nombre d'éléments par page dans les listes
  - Thème de couleur préféré
  - Notifications activées/désactivées

##### **Bankrolls** (`bankrolls.vue`)
- **Gestion des Comptes de Paris**:
  - Création de multiples bankrolls avec noms personnalisés
  - Définition du capital initial pour chaque bankroll
  - Suivi automatique du capital actuel
  - Historique des transactions par bankroll

- **Fonctionnalités CRUD**:
  - **Création**: Formulaire avec validation (nom, capital initial, devise)
  - **Lecture**: Liste paginée avec filtres et recherche
  - **Modification**: Édition en ligne ou via modal
  - **Suppression**: Confirmation obligatoire avec vérification des paris associés

- **Validation et Sécurité**:
  - Validation côté client avec Vuelidate
  - Validation côté serveur avec Laravel Request
  - Vérification des droits d'accès
  - Audit trail des modifications

##### **Bookmakers** (`bookmakers.vue`)
- **Association avec Sites de Paris**:
  - Liste des bookmakers disponibles avec logos
  - Association utilisateur-bookmaker avec identifiants personnels
  - Gestion des bonus et promotions
  - Suivi des limites de mise par bookmaker

##### **Sports** (`sports.vue`)
- **Préférences Sportives**:
  - Sélection des sports favoris
  - Configuration des ligues suivies
  - Paramètres de notification par sport
  - Ordre d'affichage personnalisé

##### **Tipsters** (`tipsters.vue`)
- **Gestion des Pronostiqueurs**:
  - Ajout de tipsters avec informations de contact
  - Suivi des performances par tipster
  - Calcul automatique du ROI par pronostiqueur
  - Historique des pronostics reçus

#### 3. Outils de Calcul (`MesOutils.vue`)
**Description**: Suite d'outils mathématiques pour optimiser les stratégies de paris.

**Fonctionnement détaillé**:

##### **Remboursé si Nul**
- **Principe**: Calcul des cotes ajustées lorsque le bookmaker rembourse en cas de match nul
- **Fonctionnement**:
  - Saisie de la cote originale
  - Calcul automatique de la cote ajustée
  - Affichage de la valeur ajoutée par l'offre
  - Comparaison avec d'autres bookmakers
- **Formule**: Cote ajustée = (Cote originale - 1) × (1 + probabilité de nul) + 1

##### **Double Chance**
- **Principe**: Conversion des cotes simples en cotes double chance
- **Fonctionnement**:
  - Saisie des cotes 1X2
  - Calcul automatique des cotes 1X, X2, 12
  - Analyse de la valeur de chaque option
  - Recommandations basées sur les probabilités
- **Calculs**: Combinaison des probabilités implicites

##### **Taux de Retour Joueur (RTP)**
- **Principe**: Analyse de la rentabilité théorique d'un pari
- **Fonctionnement**:
  - Calcul du taux de retour du bookmaker
  - Identification de la marge du bookmaker
  - Comparaison entre plusieurs bookmakers
  - Détection des paris à valeur positive
- **Formule**: RTP = 1 / (1/cote1 + 1/cote2 + ... + 1/coteN)

##### **Dutching**
- **Principe**: Répartition optimale des mises sur plusieurs sélections
- **Fonctionnement**:
  - Saisie des cotes de chaque sélection
  - Définition de la mise totale
  - Calcul automatique de la répartition
  - Garantie de profit identique quelle que soit l'issue
- **Algorithme**: Répartition proportionnelle inverse aux cotes

#### 4. Simulateur (`Martingale.vue`)
**Description**: Outil de simulation et de test de stratégies de martingale pour l'analyse des risques.

**Fonctionnement détaillé**:

##### **Simulation de Stratégies**
- **Configuration de Base**:
  - Mise initiale personnalisable
  - Coefficient de progression (classique: x2, personnalisé possible)
  - Capital de départ
  - Nombre maximum de niveaux
  - Objectif de gain

- **Types de Martingale**:
  - **Classique**: Doublement après chaque perte
  - **Fibonacci**: Progression selon la suite de Fibonacci
  - **D'Alembert**: Augmentation/diminution d'une unité
  - **Personnalisée**: Définition libre de la progression

##### **Génération de Paris Aléatoires**
- **Paramètres de Simulation**:
  - Cotes minimales et maximales
  - Pourcentage de réussite simulé
  - Nombre de sessions à simuler
  - Durée de chaque session

- **Résultats de Simulation**:
  - Graphique d'évolution du capital
  - Statistiques de performance
  - Analyse des risques de ruine
  - Temps moyen de récupération

##### **Création de Martingales Personnalisées**
- **Interface de Configuration**:
  - Définition des niveaux de mise
  - Conditions d'arrêt personnalisées
  - Règles de progression spécifiques
  - Gestion des limites de bankroll

##### **Sauvegarde et Gestion**
- **Fonctionnalités de Persistance**:
  - Sauvegarde des configurations testées
  - Historique des simulations
  - Comparaison entre différentes stratégies
  - Export des résultats en CSV/PDF

#### 5. Gestion des Paris
**Description**: Module central pour la création, le suivi et l'analyse des paris sportifs.

**Fonctionnement détaillé**:

##### **Ajout de Nouveaux Paris**
- **Formulaire de Création**:
  - Sélection du sport et de la compétition
  - Choix de l'événement (avec recherche)
  - Type de pari (1X2, Over/Under, Handicap, etc.)
  - Saisie de la cote et de la mise
  - Sélection du bookmaker et de la bankroll
  - Notes personnelles optionnelles

- **Validation et Contrôles**:
  - Vérification de la disponibilité des fonds
  - Validation des cotes (limites min/max)
  - Contrôle des doublons
  - Sauvegarde automatique en brouillon

##### **Consultation de l'Historique**
- **Interface de Consultation**:
  - Table paginée avec tri multi-colonnes
  - Affichage conditionnel selon le statut
  - Calcul automatique des gains/pertes
  - Liens vers les détails de chaque pari

- **Informations Affichées**:
  - Date et heure du pari
  - Événement et type de pari
  - Cote et mise
  - Statut actuel (En cours, Gagné, Perdu, Annulé)
  - Gain/Perte réalisé
  - Bookmaker utilisé

##### **Filtrage et Recherche Avancée**
- **Filtres Disponibles**:
  - Période (date de début/fin)
  - Sport et compétition
  - Bookmaker
  - Bankroll
  - Statut du pari
  - Montant de mise (min/max)
  - Type de pari

- **Recherche Textuelle**:
  - Recherche dans les noms d'équipes
  - Recherche dans les notes personnelles
  - Recherche par ID de pari
  - Suggestions automatiques

##### **Statistiques Détaillées**
- **Métriques Globales**:
  - Nombre total de paris
  - Taux de réussite par période
  - ROI global et par catégorie
  - Profit/Perte net
  - Mise moyenne

- **Analyses Segmentées**:
  - Performance par sport
  - Performance par bookmaker
  - Performance par type de pari
  - Évolution temporelle
  - Comparaison avec objectifs fixés

### Fonctionnalités Secondaires

#### 1. Système de Notifications
**Description**: Système d'alertes et de rappels pour optimiser l'expérience utilisateur.

**Fonctionnement**:
- **Notifications de Paris**: Alertes pour les paris en cours
- **Rappels de Résultats**: Notification des résultats disponibles
- **Alertes de Bankroll**: Seuils de capital atteints
- **Promotions Bookmakers**: Nouvelles offres disponibles

#### 2. Système d'Import/Export
**Description**: Outils pour la sauvegarde et la migration des données.

**Fonctionnalités**:
- **Export CSV**: Extraction des données de paris
- **Export PDF**: Rapports formatés
- **Import de Paris**: Importation depuis d'autres plateformes
- **Sauvegarde Automatique**: Backup quotidien des données

#### 3. Analyse Comparative
**Description**: Outils de comparaison et d'analyse de performance.

**Fonctionnalités**:
- **Comparaison de Cotes**: Entre différents bookmakers
- **Analyse de Tendances**: Identification des patterns
- **Benchmarking**: Comparaison avec moyennes du marché
- **Alertes de Valeur**: Détection des paris à forte valeur

#### 4. Gestion des Objectifs
**Description**: Système de définition et de suivi d'objectifs personnels.

**Fonctionnalités**:
- **Objectifs de Profit**: Définition de cibles mensuelles/annuelles
- **Objectifs de Volume**: Nombre de paris ciblés
- **Suivi de Progression**: Indicateurs visuels d'avancement
- **Récompenses**: Système de badges et accomplissements

### Système de Navigation

#### Menu Principal (`AppMenu.vue`)
```javascript
- Tableau de bord
- Profil
  - Mes informations
  - Sports
  - Bookmakers
  - Bankrolls
  - Tipsters
- Mes Outils
  - Remboursé si Nul
  - Double Chance
  - Taux de Retour Joueur
  - Dutching
- Simulateur
  - Martingale
- UI Components (développement)
- Pages (authentification, documentation)
```

### Système de Thèmes
- **Thème principal**: PrimeVue Aura
- **Mode sombre**: Support complet
- **Couleurs**: Palette emerald/slate personnalisée
- **Responsive**: Design adaptatif avec Tailwind CSS

### Authentification Frontend
- Routes protégées avec `meta: { requiresAuth: true }`
- Gestion des tokens avec Sanctum
- Redirection automatique vers login

## Backend - API Laravel

### Structure de l'API

#### Modèles de Données
```php
app/Models/
├── User.php                    # Utilisateurs
├── Bet.php                     # Paris
├── Event.php                   # Événements sportifs
├── Sport.php                   # Sports
├── League.php                  # Ligues/Championnats
├── Team.php                    # Équipes
├── Player.php                  # Joueurs
├── Country.php                 # Pays
├── Bookmaker.php               # Sites de paris
├── UserBankroll.php            # Bankrolls utilisateur
├── UserBookmaker.php           # Associations utilisateur-bookmaker
├── UserSportPreference.php     # Préférences sportives
├── Tipster.php                 # Pronostiqueurs
└── Transaction.php             # Transactions financières
```

#### Contrôleurs API
```php
app/Http/Controllers/
├── AuthController.php          # Authentification
├── BetController.php           # Gestion des paris
├── EventController.php         # Événements sportifs
├── SportController.php         # Sports et ligues
├── UserController.php          # Profil utilisateur
├── BookmakerController.php     # Bookmakers
├── UserBankrollController.php  # Bankrolls
├── TipsterController.php       # Tipsters
└── TransactionController.php   # Transactions
```

### Endpoints API Principaux

#### Authentification
```
POST /api/register              # Inscription
POST /api/login                 # Connexion
POST /api/logout                # Déconnexion
GET  /api/user                  # Profil utilisateur
```

#### Paris
```
GET    /api/bets                # Liste des paris
POST   /api/bets                # Créer un pari
GET    /api/bets/{id}           # Détails d'un pari
PUT    /api/bets/{id}           # Modifier un pari
DELETE /api/bets/{id}           # Supprimer un pari
GET    /api/bets/stats          # Statistiques
GET    /api/bets/capital-evolution # Évolution du capital
GET    /api/bets/filter-options # Options de filtrage
```

#### Sports et Données
```
GET /api/sports                 # Liste des sports
GET /api/sports/{id}/leagues    # Ligues par sport
GET /api/sports/{id}/teams      # Équipes par sport
GET /api/countries              # Liste des pays
GET /api/bookmakers             # Liste des bookmakers
```

#### Profil Utilisateur
```
GET    /api/bankrolls           # Bankrolls utilisateur
POST   /api/bankrolls           # Créer une bankroll
GET    /api/user-bookmakers     # Bookmakers associés
GET    /api/tipsters            # Tipsters de l'utilisateur
GET    /api/user/sports-preferences # Préférences sportives
```

### Services Backend

#### Services de Données Externes
```php
app/Services/
├── CountryFlagService.php      # Téléchargement des drapeaux
├── LeagueLogoService.php       # Logos des ligues
└── TeamLogoService.php         # Logos des équipes
```

#### Commandes Artisan
```php
app/Console/Commands/
├── ImportSportLeagues.php      # Import des ligues
├── ImportTeams.php             # Import des équipes
├── ImportPlayers.php           # Import des joueurs
├── DownloadTeamLogos.php       # Téléchargement des logos
└── DownloadCountryFlags.php    # Téléchargement des drapeaux
```

### Configuration Docker

#### Backend (`docker-compose.yml`)
```yaml
services:
  web:
    container_name: api.auxotracker
    ports: ["8080:80"]
    environment:
      VIRTUAL_HOST: api.auxotracker.lan
    volumes:
      - ./:/var/www/html
      - SSL certificates
```

## Configuration et Déploiement

### Variables d'Environnement

#### Frontend (`.env`)
```
VITE_API_BASE_URL=https://api.auxotracker.lan
```

#### Backend (`.env`)
```
APP_URL=https://api.auxotracker.lan
DB_CONNECTION=mysql
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1
```

### Installation et Lancement

#### Frontend
```bash
cd frontend
npm install
npm run dev          # Développement
npm run build        # Production
```

#### Backend
```bash
cd backend
composer install
php artisan key:generate
php artisan migrate
php artisan serve    # Développement
```

### Docker
```bash
# Backend
cd backend
docker-compose up -d

# Frontend
cd frontend
docker build -t bet-tracker-frontend .
docker run -p 8080:8080 bet-tracker-frontend
```

## Fonctionnalités Avancées

### Système de Thèmes Dynamiques
- Changement de thème en temps réel
- Palettes de couleurs personnalisables
- Support du mode sombre/clair

### Internationalisation
- Support multilingue (français par défaut)
- Gestion des devises
- Fuseaux horaires

### Sécurité
- Authentification avec Laravel Sanctum
- Protection CSRF
- Validation des données côté client et serveur
- Chiffrement des données sensibles

### Performance
- Lazy loading des composants Vue
- Optimisation des requêtes API
- Cache des données statiques
- Compression des assets

## Développement et Maintenance

### Standards de Code
- ESLint pour JavaScript/Vue
- Laravel Pint pour PHP
- Prettier pour le formatage
- Conventions de nommage françaises

### Tests
- Tests unitaires avec PHPUnit (backend)
- Tests d'intégration API
- Tests de composants Vue (à implémenter)

### Documentation
- Documentation API avec Swagger (à implémenter)
- Documentation des composants Vue
- Guide d'installation et de déploiement

## Roadmap et Évolutions

### Fonctionnalités Prévues
- Module de statistiques avancées
- Intégration avec APIs de paris en direct
- Notifications push
- Application mobile (React Native/Flutter)
- Système de recommandations IA

### Améliorations Techniques
- Migration vers TypeScript
- Implémentation de tests automatisés
- CI/CD avec GitHub Actions
- Monitoring et logging avancés
- Optimisation des performances

## Support et Contact

### Environnement de Développement
- **URL Frontend**: http://localhost:5173
- **URL Backend**: https://api.auxotracker.lan:8080
- **Base de données**: MySQL
- **Cache**: Redis (optionnel)

### Ressources
- [Documentation PrimeVue](https://primevue.org/)
- [Documentation Laravel](https://laravel.com/docs)
- [Documentation Vue.js](https://vuejs.org/)
- [Documentation Tailwind CSS](https://tailwindcss.com/)

---

*Cette documentation est maintenue à jour avec l'évolution du projet. Dernière mise à jour : Janvier 2025*