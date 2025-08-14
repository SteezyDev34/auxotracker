# Guide de Résolution - Problème d'Authentification 401

## Problème Identifié
Lorsque vous modifiez l'avatar, vous recevez une erreur 401 (non autorisé) même après vous être connecté. Cela indique que le backend n'enregistrait pas correctement la connexion utilisateur.

## Cause du Problème
Le système d'authentification n'utilisait pas correctement Laravel Sanctum pour générer et valider les tokens d'API.

## Solutions Appliquées

### 1. Modification de l'AuthController
**Fichier:** `backend/app/Http/Controllers/AuthController.php`

**Changements:**
- ✅ Ajout de la génération de token Sanctum lors de la connexion
- ✅ Suppression des anciens tokens avant d'en créer un nouveau
- ✅ Retour du token dans la réponse de connexion
- ✅ Ajout de méthodes `logout()` et `user()` pour une gestion complète

**Avant:**
```php
if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
    return response()->json(['success' => true]);
}
```

**Après:**
```php
if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
    $user = Auth::user();
    
    // Supprimer les anciens tokens
    $user->tokens()->delete();
    
    // Créer un nouveau token
    $token = $user->createToken('auth-token')->plainTextToken;
    
    return response()->json([
        'success' => true,
        'token' => $token,
        'user' => $user
    ]);
}
```

### 2. Mise à jour des Routes API
**Fichier:** `backend/routes/api.php`

**Changements:**
- ✅ Protection de la route `/logout` avec le middleware `auth:sanctum`
- ✅ Ajout de la route `/user` pour récupérer les infos utilisateur
- ✅ Maintien de la protection de la route `/user/avatar`

### 3. Vérification du Modèle User
**Fichier:** `backend/app/Models/User.php`

- ✅ Confirmation que le trait `HasApiTokens` est bien utilisé
- ✅ Le modèle peut créer et gérer les tokens Sanctum

### 4. Frontend - Gestion des Tokens
**Fichier:** `frontend/src/views/pages/auth/Login.vue`

- ✅ Le code stocke déjà correctement le token dans localStorage
- ✅ Le token est envoyé dans les en-têtes Authorization

**Fichier:** `frontend/src/views/profile/infos.vue`

- ✅ Le code récupère et utilise correctement le token pour l'upload d'avatar

## Test de Fonctionnement

Un fichier de test a été créé : `test_auth.html`

Ce fichier permet de tester :
1. **Connexion** - Vérification que le token est généré
2. **Upload d'avatar** - Test de l'authentification avec token
3. **Récupération des infos utilisateur** - Validation du token

## Utilisation

### Pour tester l'authentification :
1. Ouvrez `test_auth.html` dans votre navigateur
2. Utilisez les identifiants de test :
   - Email: `test@example.com`
   - Mot de passe: `password123`
3. Testez la connexion, puis l'upload d'avatar

### Dans l'application :
1. Connectez-vous normalement via l'interface
2. Le token sera automatiquement stocké dans localStorage
3. L'upload d'avatar devrait maintenant fonctionner sans erreur 401

## Points Importants

- ✅ **Sécurité** : Les anciens tokens sont supprimés à chaque nouvelle connexion
- ✅ **Persistance** : Le token est stocké dans localStorage côté frontend
- ✅ **Validation** : Toutes les routes protégées utilisent le middleware `auth:sanctum`
- ✅ **Gestion d'erreurs** : Messages d'erreur appropriés en cas d'échec

## Commandes Utiles

```bash
# Démarrer le serveur backend
cd backend && php artisan serve --host=0.0.0.0 --port=8000

# Vérifier les tokens en base (optionnel)
php artisan tinker
>>> \App\Models\User::find(1)->tokens;

# Supprimer tous les tokens (si nécessaire)
php artisan tinker
>>> \Laravel\Sanctum\PersonalAccessToken::truncate();
```

Le problème d'authentification 401 lors de la modification de l'avatar est maintenant résolu ! 🎉