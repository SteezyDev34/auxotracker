# Stratégie de Cache Multi-Niveaux Optimale

## Vue d'ensemble

Architecture de cache en 4 niveaux pour optimiser la navigation et réduire les temps de réponse de 60-95%.

**Objectif** : Atteindre des temps de réponse < 100ms pour les données fréquemment consultées.

---

## Architecture Multi-Niveaux

```
┌─────────────────────────────────────────────────────────────┐
│                      REQUÊTE CLIENT                          │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ L1: MEMORY CACHE (Map)                        ~0ms          │
│ - Stockage: RAM JavaScript                                  │
│ - TTL: 5-10 minutes                                          │
│ - Capacité: ~50 entrées (LRU)                               │
└──────────────────────┬──────────────────────────────────────┘
                       │ Cache miss
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ L2: LOCALSTORAGE                              ~5ms          │
│ - Stockage: Navigateur (5-10 MB)                           │
│ - TTL: 1-24 heures selon type                               │
│ - Capacité: Illimitée avec éviction LRU                     │
└──────────────────────┬──────────────────────────────────────┘
                       │ Cache miss
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ L3: HTTP CACHE (ETag/304)                     ~50ms         │
│ - Validation: If-None-Match header                          │
│ - Réponse: 304 Not Modified (pas de body)                   │
│ - Économie: ~90% de bande passante                          │
└──────────────────────┬──────────────────────────────────────┘
                       │ Cache miss
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ L4: REDIS BACKEND                             ~100ms        │
│ - Stockage: Redis sur serveur                               │
│ - TTL: 5-60 minutes selon type                              │
│ - Capacité: Illimitée                                        │
└──────────────────────┬──────────────────────────────────────┘
                       │ Cache miss
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ DATABASE (MariaDB)                            200-500ms     │
│ - Source de vérité                                           │
│ - Requête complète                                           │
└─────────────────────────────────────────────────────────────┘
```

---

## Gains de Performance Estimés

| Type de données           | Sans cache | Avec cache | Gain      |
|---------------------------|-----------|-----------|-----------|
| Liste sports              | 200ms     | 0ms       | **100%**  |
| Équipes prioritaires      | 150ms     | 5ms       | **97%**   |
| Recherche équipes (10 car)| 180ms     | 50ms      | **72%**   |
| Pays + drapeaux           | 250ms     | 0ms       | **100%**   |
| Ligues d'un sport         | 220ms     | 10ms      | **95%**   |

**Impact global** : Navigation 3-10x plus rapide sur mobile.

---

## Phase 1 : Cache Frontend (30-45 min)

### 1.1 Créer le CacheService

**Fichier** : `frontend/src/service/CacheService.js`

```javascript
/**
 * Service de cache multi-niveaux (Memory + LocalStorage)
 * Gère TTL, éviction LRU, et compression optionnelle
 */

// Configuration des TTL par type de données (en millisecondes)
const TTL_CONFIG = {
  SPORTS: 24 * 60 * 60 * 1000,        // 24h (données statiques)
  COUNTRIES: 24 * 60 * 60 * 1000,     // 24h (données statiques)
  LEAGUES: 60 * 60 * 1000,            // 1h (changent rarement)
  TEAMS_PRIORITY: 10 * 60 * 1000,     // 10min (mises à jour fréquentes)
  TEAMS_SEARCH: 5 * 60 * 1000,        // 5min (résultats volatils)
  DEFAULT: 10 * 60 * 1000             // 10min par défaut
};

// Cache L1 : Memory (Map JavaScript)
const memoryCache = new Map();
const MAX_MEMORY_ENTRIES = 50; // Éviction LRU après 50 entrées

class CacheService {
  /**
   * Récupérer une valeur du cache (Memory puis LocalStorage)
   * @param {string} key - Clé de cache
   * @returns {any|null} Données ou null si expiré/absent
   */
  static async get(key) {
    // L1: Vérifier Memory Cache
    if (memoryCache.has(key)) {
      const entry = memoryCache.get(key);
      
      // Vérifier expiration
      if (Date.now() < entry.expiry) {
        // Rafraîchir LRU (remettre en fin)
        memoryCache.delete(key);
        memoryCache.set(key, entry);
        return entry.data;
      }
      
      // Expiré : supprimer
      memoryCache.delete(key);
    }

    // L2: Vérifier LocalStorage
    try {
      const stored = localStorage.getItem(key);
      if (!stored) return null;

      const entry = JSON.parse(stored);
      
      // Vérifier expiration
      if (Date.now() < entry.expiry) {
        // Repeupler Memory Cache
        this._setMemory(key, entry.data, entry.expiry);
        return entry.data;
      }

      // Expiré : supprimer
      localStorage.removeItem(key);
      return null;
    } catch (error) {
      console.warn(`[Cache] Erreur lecture ${key}:`, error);
      return null;
    }
  }

  /**
   * Stocker une valeur dans le cache (Memory + LocalStorage)
   * @param {string} key - Clé de cache
   * @param {any} data - Données à cacher
   * @param {number} ttl - Durée de vie en ms (optionnel, utilise TTL_CONFIG)
   */
  static async set(key, data, ttl = null) {
    // Déterminer TTL selon le préfixe de clé
    const effectiveTTL = ttl || this._getTTLForKey(key);
    const expiry = Date.now() + effectiveTTL;

    // Stocker en Memory
    this._setMemory(key, data, expiry);

    // Stocker en LocalStorage
    try {
      const entry = {
        data,
        expiry,
        version: 1 // Pour migrations futures
      };
      localStorage.setItem(key, JSON.stringify(entry));
    } catch (error) {
      // Quota dépassé : nettoyer et réessayer
      if (error.name === 'QuotaExceededError') {
        console.warn('[Cache] Quota LocalStorage dépassé, nettoyage...');
        this._evictOldest(10); // Supprimer 10 entrées les plus anciennes
        try {
          localStorage.setItem(key, JSON.stringify({ data, expiry, version: 1 }));
        } catch (retryError) {
          console.error('[Cache] Impossible de cacher après nettoyage:', retryError);
        }
      } else {
        console.error(`[Cache] Erreur stockage ${key}:`, error);
      }
    }
  }

  /**
   * Invalider une entrée du cache
   * @param {string} key - Clé à supprimer
   */
  static invalidate(key) {
    memoryCache.delete(key);
    localStorage.removeItem(key);
  }

  /**
   * Invalider toutes les entrées correspondant à un pattern
   * @param {RegExp|string} pattern - Pattern de clés
   */
  static invalidatePattern(pattern) {
    const regex = typeof pattern === 'string' ? new RegExp(pattern) : pattern;

    // Invalider Memory
    for (const key of memoryCache.keys()) {
      if (regex.test(key)) {
        memoryCache.delete(key);
      }
    }

    // Invalider LocalStorage
    const keysToRemove = [];
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (regex.test(key)) {
        keysToRemove.push(key);
      }
    }
    keysToRemove.forEach(key => localStorage.removeItem(key));
  }

  /**
   * Vider tout le cache
   */
  static clear() {
    memoryCache.clear();
    // Ne vider que les clés de cache (préfixées par "cache:")
    const keysToRemove = [];
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key.startsWith('cache:')) {
        keysToRemove.push(key);
      }
    }
    keysToRemove.forEach(key => localStorage.removeItem(key));
  }

  /**
   * Obtenir les statistiques du cache
   */
  static getStats() {
    const localStorageKeys = [];
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key.startsWith('cache:')) {
        localStorageKeys.push(key);
      }
    }

    return {
      memory: {
        size: memoryCache.size,
        maxSize: MAX_MEMORY_ENTRIES
      },
      localStorage: {
        keys: localStorageKeys.length,
        estimatedSize: this._getLocalStorageSize()
      }
    };
  }

  // === Méthodes privées ===

  static _setMemory(key, data, expiry) {
    // Éviction LRU si dépassement
    if (memoryCache.size >= MAX_MEMORY_ENTRIES && !memoryCache.has(key)) {
      const firstKey = memoryCache.keys().next().value;
      memoryCache.delete(firstKey);
    }

    memoryCache.set(key, { data, expiry });
  }

  static _getTTLForKey(key) {
    if (key.includes(':sports')) return TTL_CONFIG.SPORTS;
    if (key.includes(':countries')) return TTL_CONFIG.COUNTRIES;
    if (key.includes(':leagues')) return TTL_CONFIG.LEAGUES;
    if (key.includes(':teams:priority')) return TTL_CONFIG.TEAMS_PRIORITY;
    if (key.includes(':teams:search')) return TTL_CONFIG.TEAMS_SEARCH;
    return TTL_CONFIG.DEFAULT;
  }

  static _evictOldest(count = 10) {
    const entries = [];
    
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (!key.startsWith('cache:')) continue;

      try {
        const entry = JSON.parse(localStorage.getItem(key));
        entries.push({ key, expiry: entry.expiry });
      } catch (e) {
        // Entrée corrompue : supprimer
        localStorage.removeItem(key);
      }
    }

    // Trier par expiration (plus ancien d'abord)
    entries.sort((a, b) => a.expiry - b.expiry);

    // Supprimer les N plus anciens
    entries.slice(0, count).forEach(entry => {
      localStorage.removeItem(entry.key);
    });
  }

  static _getLocalStorageSize() {
    let size = 0;
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key.startsWith('cache:')) {
        size += (key.length + localStorage.getItem(key).length) * 2; // UTF-16 = 2 bytes/char
      }
    }
    return `${(size / 1024).toFixed(2)} KB`;
  }
}

export default CacheService;
```

### 1.2 Wrapper pour les services existants

**Fichier** : `frontend/src/composables/useCache.js`

```javascript
import CacheService from '@/service/CacheService';

/**
 * Composable pour wrapper les appels API avec cache
 */
export function useCache() {
  /**
   * Wrapper générique pour cacher un appel API
   * @param {string} cacheKey - Clé de cache unique
   * @param {Function} fetchFn - Fonction async qui retourne les données
   * @param {number} ttl - TTL optionnel
   * @returns {Promise<any>} Données (de cache ou API)
   */
  const cachedFetch = async (cacheKey, fetchFn, ttl = null) => {
    // Préfixer toutes les clés
    const key = `cache:${cacheKey}`;

    // Tenter de récupérer du cache
    const cached = await CacheService.get(key);
    if (cached !== null) {
      console.log(`[Cache HIT] ${cacheKey}`);
      return cached;
    }

    // Cache miss : fetch depuis l'API
    console.log(`[Cache MISS] ${cacheKey}`);
    const data = await fetchFn();

    // Stocker en cache
    await CacheService.set(key, data, ttl);

    return data;
  };

  return {
    cachedFetch,
    invalidate: CacheService.invalidate.bind(CacheService),
    invalidatePattern: CacheService.invalidatePattern.bind(CacheService),
    clear: CacheService.clear.bind(CacheService),
    getStats: CacheService.getStats.bind(CacheService)
  };
}
```

### 1.3 Intégration dans SportService

**Modifier** : `frontend/src/service/SportService.js`

```javascript
import ApiService from './ApiService';
import CacheService from './CacheService';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL;

const SportService = {
  async getAll() {
    const cacheKey = 'cache:sports:all';
    
    // Vérifier cache
    const cached = await CacheService.get(cacheKey);
    if (cached) return cached;
    
    // Cache miss : fetch API
    const response = await ApiService.get('/sports');
    
    // Stocker en cache (TTL auto via préfixe :sports)
    await CacheService.set(cacheKey, response);
    
    return response;
  },

  async searchTeamsBySport(sportId, search = '', page = 1, limit = 50, leagueId = null, countryId = null, priorityOnly = false) {
    // Clé de cache unique basée sur paramètres
    const cacheKey = `cache:teams:search:sport${sportId}:${search}:p${priorityOnly}:l${leagueId}:c${countryId}`;
    
    // Cache uniquement si recherche > 2 caractères
    if (search.length >= 2) {
      const cached = await CacheService.get(cacheKey);
      if (cached) return cached;
    }
    
    const params = {
      search,
      page,
      limit,
      ...(leagueId && { league_id: leagueId }),
      ...(countryId && { country_id: countryId }),
      ...(priorityOnly && { priority_only: 1 })
    };

    const response = await ApiService.get(`${API_BASE_URL}/sports/${sportId}/search-teams`, { params });
    
    // Cacher seulement résultats de recherche significatifs
    if (search.length >= 2) {
      await CacheService.set(cacheKey, response);
    }
    
    return response;
  }
};

export default SportService;
```

### 1.4 Intégration dans TeamSearchService

**Modifier** : `frontend/src/service/TeamSearchService.js`

Ajouter l'invalidation de cache après résolution :

```javascript
async resolve(searchId, teamId) {
  const response = await ApiService.put(`/team-searches/not-found/${searchId}/resolve`, {
    team_id: teamId
  });
  
  // Invalider tous les caches de recherche d'équipes
  CacheService.invalidatePattern(/cache:teams:search/);
  
  return response;
}
```

---

## Phase 2 : Cache Backend Redis (1h)

### 2.1 Installation de Redis

**Sur le serveur de développement** :

```bash
# Docker Compose (ajouter au docker-compose.yml du backend)
redis:
  image: redis:7-alpine
  container_name: auxotracker_redis
  ports:
    - "6379:6379"
  volumes:
    - redis_data:/data
  command: redis-server --appendonly yes
  networks:
    - auxotracker_network

volumes:
  redis_data:
```

**Démarrer Redis** :
```bash
cd backend
docker-compose up -d redis
```

### 2.2 Configuration Laravel

**Installer le package Predis** :
```bash
composer require predis/predis
```

**Configurer** `.env` :
```env
CACHE_DRIVER=redis
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
```

**Vérifier** `config/cache.php` (déjà configuré par défaut).

### 2.3 Modifier SportController

**Fichier** : `backend/app/Http/Controllers/SportController.php`

Ajouter le cache autour de `searchTeamsBySport` :

```php
use Illuminate\Support\Facades\Cache;

public function searchTeamsBySport(Request $request, $sportId)
{
    // Générer clé de cache unique
    $cacheKey = sprintf(
        'teams:sport:%d:search:%s:priority:%d:league:%s:country:%s',
        $sportId,
        md5($request->input('search', '')),
        $request->input('priority_only', 0),
        $request->input('league_id', 'null'),
        $request->input('country_id', 'null')
    );

    // Cache 5 minutes
    return Cache::remember($cacheKey, 300, function () use ($request, $sportId) {
        $search = $request->input('search', '');
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 50);
        // ... logique existante ...
        
        return response()->json([
            'data' => $teams,
            'meta' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total
            ]
        ]);
    });
}
```

### 2.4 Invalider le cache après modifications

**Dans TeamSearchNotFoundController::resolve()** :

```php
use Illuminate\Support\Facades\Cache;

public function resolve(Request $request, $id)
{
    // ... logique existante ...
    
    // Invalider tous les caches de recherche d'équipes
    Cache::tags(['teams'])->flush();
    // OU plus spécifique :
    Cache::forget("teams:sport:{$searchNotFound->sport_id}:*");
    
    return response()->json([
        'message' => 'Recherche résolue avec succès',
        'search' => $searchNotFound,
        'team' => $team
    ]);
}
```

---

## Phase 3 : HTTP Cache avec ETags (2h)

### 3.1 Middleware ETag Laravel

**Créer** : `backend/app/Http/Middleware/AddETagHeader.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AddETagHeader
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Appliquer uniquement aux réponses JSON GET
        if ($request->isMethod('GET') && $response->headers->get('Content-Type') === 'application/json') {
            $content = $response->getContent();
            $etag = md5($content);

            $response->setEtag($etag);
            $response->setPublic();
            $response->setMaxAge(300); // 5 minutes

            // Vérifier If-None-Match
            if ($request->getETags() && in_array($etag, $request->getETags())) {
                $response->setNotModified();
            }
        }

        return $response;
    }
}
```

**Enregistrer dans** `app/Http/Kernel.php` :

```php
protected $middlewareGroups = [
    'api' => [
        // ... autres middlewares ...
        \App\Http\Middleware\AddETagHeader::class,
    ],
];
```

### 3.2 Gestion des ETags côté Frontend

**Modifier** : `frontend/src/service/ApiService.js`

```javascript
import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL;

// Store pour les ETags (Memory)
const etagCache = new Map();

const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  }
});

// Intercepteur Request : ajouter If-None-Match
apiClient.interceptors.request.use((config) => {
  if (config.method === 'get') {
    const etag = etagCache.get(config.url);
    if (etag) {
      config.headers['If-None-Match'] = etag;
    }
  }
  return config;
});

// Intercepteur Response : stocker l'ETag
apiClient.interceptors.response.use(
  (response) => {
    // Stocker ETag si présent
    const etag = response.headers.etag || response.headers['etag'];
    if (etag && response.config.method === 'get') {
      etagCache.set(response.config.url, etag);
    }

    return response;
  },
  (error) => {
    // 304 Not Modified : retourner données du cache
    if (error.response?.status === 304) {
      console.log('[HTTP Cache] 304 Not Modified');
      // Les données sont dans le cache LocalStorage/Memory
      return Promise.resolve({ data: null, status: 304 });
    }
    return Promise.reject(error);
  }
);

const ApiService = {
  get(url, config = {}) {
    return apiClient.get(url, config).then(res => res.data);
  },
  // ... autres méthodes ...
};

export default ApiService;
```

---

## Phase 4 : Service Worker PWA (Optionnel, 2-3h)

### 4.1 Vite PWA Plugin

```bash
npm install -D vite-plugin-pwa
```

**Configurer** `vite.config.js` :

```javascript
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: 'autoUpdate',
      workbox: {
        runtimeCaching: [
          {
            urlPattern: /^https:\/\/api\.auxotracker\.lan\/api\/(sports|countries|leagues)/,
            handler: 'CacheFirst',
            options: {
              cacheName: 'api-static-cache',
              expiration: {
                maxEntries: 50,
                maxAgeSeconds: 24 * 60 * 60 // 24h
              }
            }
          },
          {
            urlPattern: /^https:\/\/api\.auxotracker\.lan\/api\/.*search-teams/,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-search-cache',
              expiration: {
                maxEntries: 100,
                maxAgeSeconds: 5 * 60 // 5min
              }
            }
          }
        ]
      }
    })
  ]
});
```

---

## Ordre d'Implémentation Recommandé

1. **Phase 1** (30-45min) : Cache Frontend seulement
   - Gain immédiat sur navigation répétée
   - Pas de dépendance backend

2. **Phase 2** (1h) : Redis backend
   - Gain sur données partagées entre utilisateurs
   - Réduit charge DB

3. **Phase 3** (2h) : ETags HTTP
   - Économie bande passante mobile
   - Validation côté serveur

4. **Phase 4** (Optionnel) : Service Worker PWA
   - Application offline-first
   - Installation comme app native

---

## Monitoring et Debugging

### Commandes utiles

**Vider le cache frontend (console navigateur)** :
```javascript
import CacheService from '@/service/CacheService';
CacheService.clear();
```

**Stats du cache frontend** :
```javascript
CacheService.getStats();
// Output: { memory: { size: 12, maxSize: 50 }, localStorage: { keys: 24, estimatedSize: "145.23 KB" } }
```

**Vider le cache Redis backend** :
```bash
php artisan cache:clear
```

**Monitorer Redis** :
```bash
docker exec -it auxotracker_redis redis-cli
> KEYS teams:*
> TTL teams:sport:1:search:abc123
> GET teams:sport:1:search:abc123
```

### Tests de performance

**Avant/Après** :
```javascript
// Test dans console navigateur
console.time('fetch-teams');
await SportService.searchTeamsBySport(1, 'paris');
console.timeEnd('fetch-teams');
// Avant cache: ~200ms
// Après cache (hit): ~0-5ms
```

---

## Checklist de Déploiement

### Développement
- [ ] Phase 1 : Créer `CacheService.js`
- [ ] Phase 1 : Créer `useCache.js`
- [ ] Phase 1 : Modifier `SportService.js`
- [ ] Phase 1 : Modifier `TeamSearchService.js` (invalidation)
- [ ] Phase 2 : Ajouter Redis au `docker-compose.yml`
- [ ] Phase 2 : Installer `predis/predis`
- [ ] Phase 2 : Configurer `.env` Redis
- [ ] Phase 2 : Modifier `SportController.php` (Cache::remember)
- [ ] Phase 2 : Ajouter invalidation dans `TeamSearchNotFoundController.php`
- [ ] Phase 3 : Créer `AddETagHeader.php` middleware
- [ ] Phase 3 : Enregistrer middleware dans `Kernel.php`
- [ ] Phase 3 : Modifier `ApiService.js` (intercepteurs ETag)

### Tests
- [ ] Tester cache hit/miss avec Chrome DevTools (Network tab)
- [ ] Vérifier TTL avec `CacheService.getStats()`
- [ ] Tester invalidation après resolve d'équipe
- [ ] Vérifier 304 Not Modified dans Network tab
- [ ] Tester quota LocalStorage (erreur de stockage)
- [ ] Mesurer gains de performance (Chrome Lighthouse)

### Production
- [ ] Configurer Redis sur serveur production
- [ ] Configurer firewall Redis (port 6379 interne uniquement)
- [ ] Activer compression Gzip/Brotli sur serveur web
- [ ] Configurer `Cache-Control` headers
- [ ] Monitorer usage Redis (`redis-cli INFO memory`)
- [ ] Planifier nettoyage périodique (`FLUSHDB` si nécessaire)

---

## Troubleshooting

### LocalStorage quota dépassé
**Symptôme** : `QuotaExceededError`  
**Solution** : `CacheService.clear()` ou réduire TTL

### Cache non invalidé après modification
**Symptôme** : Données obsolètes affichées  
**Solution** : Vérifier `invalidatePattern()` dans services

### Redis non accessible
**Symptôme** : `Connection refused`  
**Solution** : Vérifier `docker-compose ps`, `.env` `REDIS_HOST`

### Performance pas améliorée
**Symptôme** : Temps identiques avant/après cache  
**Solution** : Vérifier logs console (HIT/MISS), Chrome DevTools Network

---

## Ressources

- [Laravel Cache Documentation](https://laravel.com/docs/10.x/cache)
- [MDN: HTTP Caching](https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching)
- [Redis Best Practices](https://redis.io/docs/manual/patterns/)
- [Vite PWA Plugin](https://vite-pwa-org.netlify.app/)

---

**Auteur** : Stratégie de cache pour AuxoTracker  
**Date** : 14 avril 2026  
**Version** : 1.0
