# Guide du Système de Rôles - AuxoTracker

## Vue d'ensemble

Le système de rôles d'AuxoTracker permet de contrôler l'accès aux différentes fonctionnalités de l'application selon le rôle de l'utilisateur.

## Rôles Disponibles

### 1. `user` (Utilisateur Standard)

- **Description** : Utilisateur standard avec accès aux fonctionnalités complètes
- **Accès** :
  - Dashboard personnel
  - Gestion des paris
  - Profil utilisateur (partagé avec investor)
  - Outils de calcul (partagé avec investor)
  - Simulateurs (partagé avec investor)
  - Fonctionnalités avancées (partagé avec admin uniquement)

### 2. `investor` (Investisseur)

- **Description** : Utilisateur spécialisé dans l'investissement sportif avec accès limité
- **Accès** :
  - Dashboard personnel
  - Gestion des paris
  - Profil utilisateur (partagé avec user)
  - Outils de calcul (partagé avec user)
  - Simulateurs (partagé avec user)
  - **EXCLUS** des fonctionnalités avancées (UI Components, etc.)

### 3. `manager` (Gestionnaire)

- **Description** : Utilisateur avec des privilèges étendus pour la gestion
- **Accès** : Toutes les fonctionnalités des rôles précédents + accès à certaines fonctions de management

### 4. `admin` (Administrateur)

- **Description** : Administrateur avec accès aux outils d'administration
- **Accès** :
  - Toutes les fonctionnalités des rôles précédents
  - Interface d'administration (UI Components)
  - Gestion des utilisateurs
  - Statistiques système

### 5. `superadmin` (Super Administrateur)

- **Description** : Accès complet au système
- **Accès** :
  - Toutes les fonctionnalités de l'application
  - Modification des rôles utilisateur
  - Accès aux configurations système

## Implémentation

### Frontend

#### 1. Composable `useAuth`

```javascript
import { useAuth } from "@/composables/useAuth.js";

const { user, isAdmin, isSuperAdmin, isManager, hasRole, hasAnyRole } =
  useAuth();

// Vérifier un rôle spécifique
if (hasRole("admin")) {
  // Action pour admin uniquement
}

// Vérifier plusieurs rôles
if (hasAnyRole(["admin", "superadmin"])) {
  // Action pour admin ou superadmin
}
```

#### 2. Guard de Route

Les routes sensibles sont automatiquement protégées :

```javascript
{
  path: '/uikit/formlayout',
  component: FormLayout,
  meta: {
    requiresAuth: true,
    requiresRole: ['admin', 'superadmin']
  }
}
```

#### 3. Menu Dynamique

Le menu s'adapte automatiquement aux rôles de l'utilisateur :

```javascript
// Dans AppMenu.vue
if (isAdmin.value) {
  baseMenu.push({
    label: 'UI Components',
    items: [...]
  });
}
```

### Backend

#### 1. Middleware `CheckRole`

```php
// Dans les contrôleurs
public function __construct()
{
    $this->middleware(['auth:sanctum', 'role:admin,superadmin']);
}

// Ou sur des routes spécifiques
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin/users', [AdminController::class, 'getUsers']);
});
```

#### 2. Vérification dans les Contrôleurs

```php
// Vérification manuelle
if (!in_array(Auth::user()->role, ['admin', 'superadmin'])) {
    return response()->json(['error' => 'Accès refusé'], 403);
}
```

## Utilisation Pratique

### 1. Ajouter une Nouvelle Fonctionnalité Admin

**Frontend :**

1. Ajouter l'élément de menu conditionnel dans `AppMenu.vue`
2. Créer la route avec les métadonnées de rôle
3. Créer le composant Vue

**Backend :**

1. Créer le contrôleur avec le middleware approprié
2. Ajouter les routes dans `api.php`

### 2. Modifier un Rôle Utilisateur

Via l'API Admin :

```bash
POST /api/admin/users/{id}/role
{
  "role": "admin"
}
```

### 3. Vérifier les Permissions

**Frontend :**

```vue
<template>
  <div v-if="isAdmin">
    <Button label="Fonction Admin" />
  </div>
</template>

<script setup>
import { useAuth } from "@/composables/useAuth.js";
const { isAdmin } = useAuth();
</script>
```

**Backend :**

```php
public function sensitiveAction()
{
    if (!Auth::user()->role === 'superadmin') {
        return response()->json(['error' => 'Accès refusé'], 403);
    }

    // Action sensible
}
```

## Structure de la Base de Données

Le champ `role` est un ENUM dans la table `users` :

```sql
ALTER TABLE users ADD COLUMN role ENUM('user', 'manager', 'admin', 'superadmin') DEFAULT 'user';
```

## Sécurité

### Bonnes Pratiques

1. **Défense en Profondeur** : Vérifier les rôles côté frontend ET backend
2. **Principe du Moindre Privilège** : Donner uniquement les permissions nécessaires
3. **Audit** : Logger les actions sensibles avec les rôles utilisateur
4. **Validation** : Toujours valider les rôles côté serveur

### Points d'Attention

- Ne jamais faire confiance uniquement à la vérification frontend
- Valider les permissions avant chaque action sensible
- Éviter les modifications de rôle en cascade
- Protéger les endpoints d'administration avec des middleware appropriés

## Tests

### Tester les Rôles

1. **Frontend** : Créer des comptes avec différents rôles et vérifier l'affichage du menu
2. **Backend** : Tester les endpoints avec différents tokens utilisateur
3. **Routes** : Vérifier les redirections automatiques vers la page d'accès refusé

### Commandes Utiles

```bash
# Créer un utilisateur admin via tinker
php artisan tinker
>>> User::where('email', 'admin@example.com')->update(['role' => 'admin']);

# Tester une route protégée
curl -H "Authorization: Bearer {token}" http://localhost:8080/api/admin/users
```

## Migration des Utilisateurs Existants

Pour mettre à jour les utilisateurs existants :

```php
// Migration ou commande artisan
User::whereNull('role')->update(['role' => 'user']);
```

Ce système offre une base solide pour la gestion des permissions dans l'application tout en restant flexible et extensible.
