# Gestion des équipes non trouvées

## Vue d'ensemble

Cette fonctionnalité permet aux super administrateurs de gérer les recherches d'équipes qui n'ont pas retourné de résultats. Elle facilite l'association de termes de recherche à des équipes existantes dans la base de données, améliorant ainsi la recherche future.

## Architecture

### Backend (Laravel)

#### Migration
**Fichier:** `backend/database/migrations/2026_04_14_000000_create_team_search_not_found_table.php`

Table `team_search_not_found` :
- `id` : Identifiant unique
- `search_term` : Terme recherché par l'utilisateur
- `user_id` : ID de l'utilisateur ayant effectué la recherche (nullable)
- `sport_id` : ID du sport concerné (nullable)
- `resolved` : Booléen indiquant si la recherche a été associée (défaut: false)
- `team_id` : ID de l'équipe associée (nullable, si résolu)
- `timestamps` : Dates de création et mise à jour

#### Modèle
**Fichier:** `backend/app/Models/TeamSearchNotFound.php`

Relations :
- `user()` : BelongsTo User
- `sport()` : BelongsTo Sport
- `team()` : BelongsTo Team

#### Contrôleur
**Fichier:** `backend/app/Http/Controllers/TeamSearchNotFoundController.php`

Middleware : `auth:sanctum` + `role:superadmin`

Méthodes :
- `index()` : Liste paginée des recherches non trouvées
- `store()` : Enregistrer un nouveau terme de recherche
- `resolve($id)` : Associer une recherche à une équipe existante
- `destroy($id)` : Supprimer une recherche

#### Routes API
**Fichier:** `backend/routes/api.php`

```php
// Routes superadmin uniquement
Route::middleware(['auth:sanctum', 'role:superadmin'])->prefix('admin')->group(function () {
    Route::get('/team-searches/not-found', [TeamSearchNotFoundController::class, 'index']);
    Route::post('/team-searches/not-found', [TeamSearchNotFoundController::class, 'store']);
    Route::put('/team-searches/not-found/{id}/resolve', [TeamSearchNotFoundController::class, 'resolve']);
    Route::delete('/team-searches/not-found/{id}', [TeamSearchNotFoundController::class, 'destroy']);
});
```

### Frontend (Vue.js)

#### Service
**Fichier:** `frontend/src/service/TeamSearchService.js`

Méthodes :
- `getAll(params)` : Récupérer toutes les recherches
- `store(data)` : Enregistrer une recherche
- `resolve(searchId, teamId)` : Associer une recherche à une équipe
- `delete(searchId)` : Supprimer une recherche

#### Page admin
**Fichier:** `frontend/src/views/admin/team-searches-not-found.vue`

Composants utilisés :
- DataTable (PrimeVue) : Affichage des recherches
- AutoComplete (PrimeVue) : Sélection d'équipe
- Button, Card, Toast

Fonctionnalités :
- Affichage paginé des recherches non trouvées
- Recherche d'équipe avec autocomplete
- Association d'un terme à une équipe existante
- Suppression d'une recherche

#### Route
**Fichier:** `frontend/src/router/index.js`

```javascript
{
  path: "/gestion/equipes-non-trouvees",
  name: "gestionEquipesNonTrouvees",
  component: () => import("@/views/admin/team-searches-not-found.vue"),
  meta: { requiresAuth: true, requiresRole: ["superadmin"] },
}
```

## Utilisation

### Enregistrer une recherche non trouvée

```bash
# Exemple de requête POST
POST /api/admin/team-searches/not-found
{
  "search_term": "PSG",
  "sport_id": 1
}
```

### Accéder à la page d'administration

1. Se connecter en tant que super administrateur
2. Naviguer vers `/gestion/equipes-non-trouvees`
3. La liste des recherches non trouvées s'affiche

### Associer une recherche à une équipe

1. Dans la colonne "Associer à une équipe", rechercher l'équipe correspondante
2. Sélectionner l'équipe dans la liste déroulante
3. Cliquer sur "Valider"
4. Le terme de recherche est ajouté au champ `nickname` de l'équipe
5. La recherche est marquée comme résolue et retirée de la liste

### Supprimer une recherche

1. Cliquer sur l'icône de corbeille à droite de la ligne
2. La recherche est supprimée définitivement

## Intégration future (optionnelle)

Pour enregistrer automatiquement les recherches infructueuses depuis le formulaire d'ajout de paris :

1. Modifier `TeamField.vue` pour détecter quand une recherche ne retourne aucun résultat
2. Appeler `TeamSearchService.store()` avec le terme recherché et le sport_id
3. Afficher une notification à l'utilisateur

Exemple d'implémentation dans `TeamField.vue` :

```javascript
const onSearchTeams = async (event) => {
  const query = event.query || '';
  // ... recherche d'équipes
  const results = await SportService.searchTeamsBySport(sportId, query, 1, 50);
  
  if (results.data.length === 0 && query.trim().length > 2) {
    // Aucun résultat trouvé, enregistrer la recherche
    try {
      await TeamSearchService.store({
        search_term: query,
        sport_id: sportId
      });
    } catch (e) {
      // Ignorer les erreurs silencieusement
    }
  }
  
  teamSearchResults.value = results.data || [];
};
```

## Permissions

- **Super Admin uniquement** : Accès complet à toutes les fonctionnalités
- Les autres rôles (admin, user, etc.) n'ont pas accès à cette fonctionnalité

## Base de données

Pour créer la table, exécuter :

```bash
cd backend
php artisan migrate
```

## Notes techniques

- Les doublons dans le champ `nickname` sont évités automatiquement
- La pagination est limitée à 50 éléments par page par défaut
- Les recherches résolues peuvent être filtrées via le paramètre `resolved=true`
- Les logos d'équipe sont affichés dans l'autocomplete si disponibles
