# Documentation du Composant SportField

## Vue d'ensemble

Le composant `SportField.vue` est un champ de sélection de sport utilisant le composant `AutoComplete` de PrimeVue. Il permet aux utilisateurs de rechercher et sélectionner un sport parmi une liste prédéfinie, avec affichage en mode "chip" pour une meilleure expérience utilisateur.

## Localisation

```
/src/components/add-bet/fields/SportField.vue
```

## Fonctionnalités principales

### 1. Sélection de sport avec recherche
- **Recherche en temps réel** : L'utilisateur peut taper pour filtrer les sports
- **Affichage en chips** : Le sport sélectionné s'affiche sous forme de "chip" élégant
- **Mode multiple** : Configuré pour accepter plusieurs sélections (limité à 1 dans la logique métier)
- **Dropdown interactif** : Menu déroulant avec bouton dédié

### 2. Gestion des événements
- **Focus automatique** : Ouverture du dropdown au focus sur le champ
- **Clic sur dropdown** : Bouton dédié pour ouvrir/fermer le menu
- **Fermeture automatique** : Le dropdown se ferme après sélection avec retrait du focus
- **Recherche intelligente** : Filtrage des résultats basé sur la saisie utilisateur

### 3. Intégration avec l'API
- **Chargement dynamique** : Les sports sont chargés depuis l'API
- **Cache local** : Évite les appels répétés à l'API
- **Gestion d'erreurs** : Affichage des erreurs de chargement

## Structure du composant

### Props

| Prop | Type | Défaut | Description |
|------|------|--------|-------------|
| `modelValue` | Array | `[]` | Valeur sélectionnée (tableau pour mode multiple) |
| `eventIndex` | Number | `0` | Index de l'événement dans le formulaire |
| `sportId` | Number/String | `null` | ID du sport sélectionné |
| `error` | String | `''` | Message d'erreur à afficher |
| `loading` | Boolean | `false` | État de chargement |
| `apiBaseUrl` | String | `''` | URL de base de l'API |
| `isDarkTheme` | Boolean | `false` | Mode sombre activé |

### Événements émis

| Événement | Paramètres | Description |
|-----------|------------|-------------|
| `update:modelValue` | `value: Array` | Mise à jour de la valeur sélectionnée |
| `search-sports` | `event, eventIndex` | Recherche de sports déclenchée |
| `sport-select` | `event, eventIndex` | Sport sélectionné |
| `sport-clear` | `eventIndex` | Sport désélectionné |
| `sport-dropdown-show` | `eventIndex` | Dropdown ouvert |

### Données réactives

```javascript
data() {
  return {
    selectedSport: [],           // Sport(s) sélectionné(s)
    sportSearchResults: [],      // Résultats de recherche
    allSports: [],              // Tous les sports disponibles
    isLoading: false            // État de chargement local
  }
}
```

## Méthodes principales

### `loadSports()`
**Objectif** : Charger la liste complète des sports depuis l'API

```javascript
async loadSports() {
  // Chargement des sports avec gestion d'erreurs
  // Cache les résultats pour éviter les appels répétés
}
```

### `onSearchSports(event)`
**Objectif** : Filtrer les sports selon la recherche utilisateur

```javascript
onSearchSports(event) {
  // Filtre les sports selon event.query
  // Met à jour sportSearchResults
  // Émet l'événement search-sports
}
```

### `onSportSelect(event)`
**Objectif** : Gérer la sélection d'un sport

```javascript
onSportSelect(event) {
  // Met à jour selectedSport avec [event.value]
  // Émet update:modelValue et sport-select
  // Ferme le dropdown et retire le focus
}
```

### `onSportClear()`
**Objectif** : Gérer la désélection du sport

```javascript
onSportClear() {
  // Remet selectedSport à []
  // Émet update:modelValue et sport-clear
}
```

### `onDropdownClick()`
**Objectif** : Gérer le clic sur le bouton dropdown

```javascript
async onDropdownClick() {
  // Charge les sports si nécessaire
  // Déclenche une recherche vide
  // Force l'ouverture du dropdown
}
```

### `onInputFocus()`
**Objectif** : Gérer le focus sur le champ de saisie

```javascript
async onInputFocus() {
  // Charge les sports si nécessaire
  // Déclenche une recherche vide
  // Ouvre le dropdown après un délai
}
```

### `closeDropdownAndBlur()`
**Objectif** : Fermer le dropdown et retirer le focus

```javascript
closeDropdownAndBlur() {
  // Ferme le dropdown avec sportRef.hide()
  // Retire le focus avec inputElement.blur()
  // Log de confirmation
}
```

## Configuration PrimeVue AutoComplete

```vue
<AutoComplete
  ref="sportRef"
  v-model="selectedSport"
  :suggestions="sportSearchResults"
  @complete="onSearchSports"
  @item-select="onSportSelect"
  @clear="onSportClear"
  @focus="onInputFocus"
  @dropdown-click="onDropdownClick"
  optionLabel="name"
  :multiple="true"
  display="chip"
  dropdownMode="blank"
  :forceSelection="false"
  :loading="isLoading"
  placeholder="Rechercher un sport..."
  :class="fieldClasses"
/>
```

### Propriétés clés

- **`multiple="true"`** : Permet la sélection multiple (limité à 1 en pratique)
- **`display="chip"`** : Affiche les sélections sous forme de chips
- **`dropdownMode="blank"`** : Dropdown vide par défaut, rempli dynamiquement
- **`forceSelection="false"`** : Permet la saisie libre (non utilisé en pratique)

## Gestion des styles

### Classes CSS dynamiques

```javascript
computed: {
  fieldClasses() {
    return [
      'w-full',
      {
        'p-invalid': this.error,
        'dark-theme': this.isDarkTheme
      }
    ]
  }
}
```

### Styles personnalisés

```scss
<style scoped>
// Styles pour le mode sombre
.dark-theme {
  // Personnalisations pour le thème sombre
}

// Styles pour les erreurs
.p-invalid {
  // Styles d'erreur
}
</style>
```

## Intégration avec AddBetForm

### Utilisation dans le formulaire

```vue
<SportField
  :event-index="eventIndex"
  v-model="eventData.selectedSport"
  :sport-id="eventData.sport_id"
  :error="errors[`sport_id_${eventIndex}`]"
  :loading="eventData.sportLoading"
  :api-base-url="apiBaseUrl"
  :is-dark-theme="isDarkTheme"
  @search-sports="searchSports"
  @sport-select="onSportSelect"
  @sport-clear="onSportClear"
  @sport-dropdown-show="onSportDropdownShow"
  :ref="(el) => { if (el) sportAutoCompleteRefs[eventIndex] = el }"
/>
```

### Gestion des données

```javascript
// Dans AddBetForm.vue
eventData: {
  selectedSport: [],        // Tableau pour le v-model
  sport_id: null,          // ID du sport sélectionné
  sportLoading: false,     // État de chargement
  sportSearchResults: []   // Résultats de recherche
}
```

## Flux de données

### 1. Initialisation
```
AddBetForm → SportField
- selectedSport: []
- sportId: null
- loading: false
```

### 2. Ouverture du dropdown
```
User Focus/Click → onInputFocus/onDropdownClick
→ loadSports() → API Call
→ onSearchSports('') → Affichage de tous les sports
→ sportRef.show() → Ouverture du dropdown
```

### 3. Recherche
```
User Input → onSearchSports(event)
→ Filtrage des sports selon event.query
→ Mise à jour de sportSearchResults
→ Affichage des résultats filtrés
```

### 4. Sélection
```
User Select → onSportSelect(event)
→ selectedSport = [event.value]
→ emit('update:modelValue', selectedSport)
→ emit('sport-select', event, eventIndex)
→ closeDropdownAndBlur()
→ AddBetForm.onSportSelect()
```

### 5. Fermeture
```
closeDropdownAndBlur()
→ sportRef.hide()
→ inputElement.blur()
→ Dropdown fermé, focus retiré
```

## Gestion des erreurs

### Types d'erreurs gérées

1. **Erreurs de chargement API**
   ```javascript
   catch (error) {
     console.error('Erreur lors du chargement des sports:', error);
     this.isLoading = false;
   }
   ```

2. **Erreurs de validation**
   ```vue
   <small v-if="error" class="p-error">{{ error }}</small>
   ```

3. **Erreurs de référence**
   ```javascript
   if (this.sportRef && this.sportRef.show) {
     this.sportRef.show();
   }
   ```

## Optimisations

### 1. Cache des données
- Les sports chargés sont mis en cache dans `allSports`
- Évite les appels API répétés

### 2. Gestion des références
- Utilisation de `ref="sportRef"` pour accéder aux méthodes du composant
- Vérification de l'existence avant utilisation

### 3. Gestion asynchrone
- Utilisation de `$nextTick()` pour les opérations DOM
- Délais appropriés pour les animations

## Bonnes pratiques

### 1. Émission d'événements
- Toujours émettre `update:modelValue` pour la réactivité
- Émettre des événements spécifiques pour la logique métier

### 2. Gestion des états
- État de chargement local et global
- Validation des données avant traitement

### 3. Accessibilité
- Labels appropriés
- Messages d'erreur clairs
- Navigation au clavier supportée

## Dépendances

- **PrimeVue** : Composant AutoComplete
- **Vue 3** : Composition API et réactivité
- **API Backend** : Endpoint `/sports` pour les données

## Tests recommandés

1. **Test de chargement** : Vérifier le chargement des sports
2. **Test de recherche** : Vérifier le filtrage des résultats
3. **Test de sélection** : Vérifier la sélection et l'émission d'événements
4. **Test de fermeture** : Vérifier la fermeture du dropdown après sélection
5. **Test d'erreurs** : Vérifier la gestion des erreurs API

## Évolutions possibles

1. **Sélection multiple réelle** : Permettre plusieurs sports
2. **Recherche avancée** : Filtres par catégorie, popularité
3. **Favoris** : Sports favoris de l'utilisateur
4. **Historique** : Sports récemment utilisés
5. **Validation avancée** : Règles de validation complexes