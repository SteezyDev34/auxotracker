# Test des Permissions - Rôle Investor

## Résumé des Modifications

Les modifications suivantes ont été apportées pour limiter l'accès des utilisateurs avec le rôle `investor` :

### 1. Nouvelles Fonctions de Permission dans `useAuth.js`

```javascript
// Nouvelles fonctions ajoutées :
const canCreateBets = computed(() => {
  return hasAnyRole(["user", "admin", "superadmin", "manager"]);
});

const canAccessTools = computed(() => {
  return hasAnyRole(["user", "admin", "superadmin", "manager"]);
});
```

### 2. Menu Dynamique mis à jour dans `AppMenu.vue`

- **"Ajouter un pari"** : Visible uniquement si `canCreateBets.value` est true (pas pour investor)
- **"Mes Outils"** : Visible uniquement si `canAccessTools.value` est true (pas pour investor)
- **"Simulateur"** : Visible uniquement si `canAccessTools.value` est true (pas pour investor)

### 3. Routes Protégées dans `router/index.js`

Toutes les routes suivantes ont été mises à jour avec `requiresRole: ["user", "admin", "superadmin", "manager"]` :

- `/ajouter-pari`
- `/mes-outils`
- `/mes-outils/rembourse-si-nul`
- `/mes-outils/double-chance`
- `/mes-outils/taux-retour-joueur`
- `/mes-outils/dutching`
- `/simulateur/martingale`

## Matrice des Permissions par Rôle

| Fonctionnalité            | investor | user | manager | admin | superadmin |
| ------------------------- | -------- | ---- | ------- | ----- | ---------- |
| Dashboard                 | ✅       | ✅   | ✅      | ✅    | ✅         |
| Mes paris                 | ✅       | ✅   | ✅      | ✅    | ✅         |
| **Ajouter un pari**       | ❌       | ✅   | ✅      | ✅    | ✅         |
| Profil - Mes informations | ✅       | ✅   | ✅      | ✅    | ✅         |
| Profil - Mes Sports       | ❌       | ✅   | ✅      | ✅    | ✅         |
| Profil - Mes Bankrolls    | ❌       | ✅   | ✅      | ✅    | ✅         |
| Profil - Mes Tipsters     | ❌       | ✅   | ✅      | ✅    | ✅         |
| **Mes Outils**            | ❌       | ✅   | ✅      | ✅    | ✅         |
| **Simulateur**            | ❌       | ✅   | ✅      | ✅    | ✅         |
| UI Components             | ❌       | ❌   | ❌      | ✅    | ✅         |

## Test à Effectuer

### 1. Créer un utilisateur avec le rôle investor

```sql
-- Dans MySQL/phpMyAdmin ou via Tinker
UPDATE users SET role = 'investor' WHERE id = X;
```

### 2. Se connecter avec cet utilisateur

### 3. Vérifications attendues

**✅ L'utilisateur investor PEUT voir :**

- Dashboard
- Menu "Paris" avec seulement "Mes paris"
- Menu "Mon Profil" avec seulement "Mes informations"

**❌ L'utilisateur investor NE PEUT PAS voir :**

- "Ajouter un pari" dans le menu Paris
- Menu "Mes Outils" (complètement caché)
- Menu "Simulateur" (complètement caché)
- Sections avancées du profil (Sports, Bankrolls, Tipsters)
- UI Components

### 4. Test de Navigation Directe

Si l'investor essaie d'accéder directement aux URLs suivantes, il devrait être redirigé vers "Access Denied" :

- `/ajouter-pari`
- `/mes-outils`
- `/mes-outils/rembourse-si-nul`
- `/simulateur/martingale`

## Instructions de Test

1. **Exécuter la migration pour ajouter le rôle investor :**

   ```bash
   cd backend
   php artisan migrate
   ```

2. **Créer un utilisateur test avec le rôle investor :**

   ```bash
   php artisan tinker

   $user = \App\Models\User::create([
       'name' => 'Test Investor',
       'email' => 'investor@test.com',
       'password' => bcrypt('password'),
       'role' => 'investor'
   ]);
   ```

3. **Se connecter avec cet utilisateur et vérifier les permissions**

4. **Tester la navigation directe vers les URLs interdites**

## Résultat Attendu

L'utilisateur avec le rôle `investor` aura un accès en **lecture seule** au système :

- Il peut consulter ses paris existants
- Il peut voir ses informations de profil
- Il ne peut pas créer de nouveaux paris
- Il ne peut pas accéder aux outils et simulateurs
- Il ne peut pas modifier les sections avancées de son profil
