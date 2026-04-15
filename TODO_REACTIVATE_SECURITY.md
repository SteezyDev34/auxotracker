# ⚠️ RÉACTIVER LA SÉCURITÉ APRÈS LES TESTS

## Fichiers modifiés temporairement pour les tests

### Backend

1. **backend/app/Http/Controllers/TeamSearchNotFoundController.php**
   ```php
   // Décommenter cette ligne dans __construct() :
   $this->middleware(['auth:sanctum', 'role:superadmin']);
   ```

### Frontend

2. **frontend/src/service/TeamSearchService.js**
   ```javascript
   // Remettre /admin/ dans toutes les routes :
   getAll(params = {}) {
     return ApiService.get('/admin/team-searches/not-found', { params });
   }
   // Idem pour store(), resolve(), delete()
   ```

3. **frontend/src/router/index.js**
   ```javascript
   // Décommenter le meta pour la route :
   {
     path: "/gestion/equipes-non-trouvees",
     name: "gestionEquipesNonTrouvees",
     component: () => import("@/views/admin/team-searches-not-found.vue"),
     meta: { requiresAuth: true, requiresRole: ["superadmin"] }, // ← Décommenter
   }
   ```

4. **frontend/src/layout/AppMenu.vue**
   ```javascript
   // Remettre la condition isSuperAdmin :
   if (isSuperAdmin.value) {
     gestionItems.push({
       label: 'Équipes non trouvées',
       icon: 'pi pi-fw pi-question-circle',
       to: '/gestion/equipes-non-trouvees',
     });
   }
   ```

## Commande de vérification après réactivation

```bash
# Tester avec un token valide de superadmin
curl -k -X POST https://api.auxotracker.lan/api/admin/team-searches/not-found \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer VOTRE_TOKEN_SUPERADMIN" \
  -d '{
    "search_term": "Test Final",
    "sport_id": 1
  }'
```

## ⚠️ NE PAS DÉPLOYER EN PRODUCTION SANS RÉACTIVER CES SÉCURITÉS
