# Documentation Complète des Champs "Équipe 1" et "Équipe 2" - AddBetForm.vue

## Table des Matières
1. [Vue d'ensemble](#vue-densemble)
2. [Structure du Template](#structure-du-template)
3. [Propriétés Réactives et Variables d'État](#propriétés-réactives-et-variables-détat)
4. [Fonctions et Méthodes](#fonctions-et-méthodes)
5. [Watchers et Computed Properties](#watchers-et-computed-properties)
6. [Validation et Contraintes](#validation-et-contraintes)
7. [Dépendances et Interactions](#dépendances-et-interactions)
8. [Cycle de Vie et Comportement](#cycle-de-vie-et-comportement)
9. [Gestion des Erreurs](#gestion-des-erreurs)
10. [Performance et Optimisations](#performance-et-optimisations)

---

## Vue d'ensemble

Les champs "Équipe 1" et "Équipe 2" dans `AddBetForm.vue` sont des composants `AutoComplete` de PrimeVue qui permettent aux utilisateurs de rechercher et sélectionner des équipes sportives pour créer des paris. Ces champs sont dynamiques, supportent la recherche en temps réel, la pagination, et incluent des validations pour éviter la sélection de la même équipe deux fois.

### Caractéristiques principales :
- **Recherche dynamique** avec API calls
- **Pagination automatique** (lazy loading)
- **Exclusion mutuelle** (une équipe ne peut pas être sélectionnée dans les deux champs)
- **Validation en temps réel**
- **Support multi-événements** (plusieurs événements par pari)
- **Interface responsive** avec logos d'équipes

---

## Structure du Template

### Équipe 1 (Lignes 70-145)

```vue
<AutoComplete
  :ref="(el) => { if (el) team1AutoCompleteRefs[eventIndex] = el }"
  :id="`team1-${eventIndex}`"
  v-model="eventData.selectedTeam1"
  :suggestions="eventData.team1SearchResults"
  @complete="(event) => searchTeam1(event, eventIndex)"
  @focus="() => onTeam1DropdownShow(eventIndex)"
  @click="() => onTeam1DropdownShow(eventIndex)"
  @item-select="(event) => onTeam1Select(event, eventIndex)"
  optionLabel="name"
  :placeholder="eventData.selectedTeam1 && eventData.selectedTeam1.length > 0 ? '' : 'Équipe 1'"
  forceSelection
  :class="{ 'p-invalid': errors[`team1-${eventIndex}`] }"
  :loading="eventData.team1Loading"
  :disabled="!eventData.sport_id"
  :minLength="0"
  dropdown
  dropdownMode="current"
  multiple
  display="chip"
  aria-label="Sélection équipe 1"
  role="combobox"
  aria-expanded="false"
  aria-autocomplete="list"
  @panel-scroll="handleTeam1PanelScroll"
>
```

### Équipe 2 (Lignes 146-220)

```vue
<AutoComplete
  :ref="(el) => { if (el) team2AutoCompleteRefs[eventIndex] = el }"
  :id="`team2-${eventIndex}`"
  v-model="eventData.selectedTeam2"
  :suggestions="eventData.team2SearchResults"
  @complete="(event) => searchTeam2(event, eventIndex)"
  @focus="() => onTeam2DropdownShow(eventIndex)"
  @click="() => onTeam2DropdownShow(eventIndex)"
  @item-select="(event) => onTeam2Select(event, eventIndex)"
  optionLabel="name"
  :placeholder="eventData.selectedTeam2 && eventData.selectedTeam2.length > 0 ? '' : 'Équipe 2'"
  forceSelection
  :class="{ 'p-invalid': errors[`team2-${eventIndex}`] }"
  :loading="eventData.team2Loading"
  :disabled="!eventData.sport_id"
  :minLength="0"
  dropdown
  dropdownMode="current"
  multiple
  display="chip"
  aria-label="Sélection équipe 2"
  role="combobox"
  aria-expanded="false"
  aria-autocomplete="list"
  @panel-scroll="handleTeam2PanelScroll"
>
```

### Attributs HTML et Vue Spécifiques

#### Attributs de Base
- **`:ref`** : Référence dynamique pour accès programmatique au composant
- **`:id`** : Identifiant unique par événement (`team1-${eventIndex}`, `team2-${eventIndex}`)
- **`v-model`** : Liaison bidirectionnelle avec les équipes sélectionnées
- **`:suggestions`** : Liste des résultats de recherche à afficher

#### Événements
- **`@complete`** : Déclenché lors de la saisie pour la recherche
- **`@focus`** : Déclenché lors du focus sur le champ
- **`@click`** : Déclenché lors du clic sur le champ
- **`@item-select`** : Déclenché lors de la sélection d'une équipe
- **`@panel-scroll`** : Déclenché lors du défilement pour le lazy loading

#### Propriétés de Configuration
- **`optionLabel="name"`** : Propriété à afficher pour chaque option
- **`forceSelection`** : Force la sélection d'une option valide
- **`dropdown`** : Active le mode dropdown
- **`dropdownMode="current"`** : Mode d'affichage du dropdown
- **`multiple`** : Permet la sélection multiple (utilisé pour l'affichage chip)
- **`display="chip"`** : Affichage sous forme de chips
- **`:minLength="0"`** : Nombre minimum de caractères pour déclencher la recherche

#### États Conditionnels
- **`:class="{ 'p-invalid': errors[...] }"`** : Classe d'erreur conditionnelle
- **`:loading`** : État de chargement
- **`:disabled="!eventData.sport_id"`** : Désactivé si aucun sport sélectionné
- **`:placeholder`** : Texte d'aide conditionnel

#### Accessibilité
- **`aria-label`** : Label pour les lecteurs d'écran
- **`role="combobox"`** : Rôle ARIA
- **`aria-expanded="false"`** : État d'expansion
- **`aria-autocomplete="list"`** : Type d'autocomplétion

### Templates Personnalisés

#### Template d'Affichage des Équipes Sélectionnées
```vue
<template #chip="slotProps">
  <div class="flex items-center gap-2 bg-blue-100 dark:bg-blue-900 px-2 py-1 rounded">
    <img 
      :src="`${apiBaseUrl}/storage/team_logos/${slotProps.value.id}.png`"
      :alt="`Logo ${slotProps.value.name}`"
      class="w-4 h-4 object-contain"
      @error="(e) => e.target.style.display = 'none'"
    />
    <span class="text-sm font-medium">{{ slotProps.value.name }}</span>
  </div>
</template>
```

#### Template des Options de Recherche
```vue
<template #option="slotProps">
  <div class="flex items-center gap-3 p-2">
    <img 
      :src="`${apiBaseUrl}/storage/team_logos/${slotProps.option.id}.png`"
      :alt="`Logo ${slotProps.option.name}`"
      class="w-6 h-6 object-contain"
      @error="(e) => e.target.style.display = 'none'"
    />
    <span>{{ slotProps.option.name }}</span>
  </div>
</template>
```

#### Template de Footer pour Pagination
```vue
<template #footer v-if="team1HasMore">
  <div class="flex justify-center items-center p-2" v-if="team1Loading">
    <ProgressSpinner style="width: 20px; height: 20px" strokeWidth="4" />
    <span class="ml-2 text-sm">Chargement...</span>
  </div>
</template>
```

---

## Propriétés Réactives et Variables d'État

### Variables Globales (Lignes 509-533)

#### Équipe 1
```javascript
const team1SearchQuery = ref('');        // Requête de recherche actuelle
const team1SearchResults = ref([]);      // Résultats de la recherche
const team1Loading = ref(false);         // État de chargement
const team1CurrentPage = ref(1);         // Page actuelle pour pagination
const team1HasMore = ref(false);         // Indicateur de données supplémentaires
const selectedTeam1 = ref([]);           // Équipe(s) sélectionnée(s)
```

#### Équipe 2
```javascript
const team2SearchQuery = ref('');        // Requête de recherche actuelle
const team2SearchResults = ref([]);      // Résultats de la recherche
const team2Loading = ref(false);         // État de chargement
const team2CurrentPage = ref(1);         // Page actuelle pour pagination
const team2HasMore = ref(false);         // Indicateur de données supplémentaires
const selectedTeam2 = ref([]);           // Équipe(s) sélectionnée(s)
```

#### Références des Composants
```javascript
const team1AutoCompleteRefs = ref({});   // Références des composants AutoComplete équipe 1
const team2AutoCompleteRefs = ref({});   // Références des composants AutoComplete équipe 2
```

#### Variables de Contrôle
```javascript
const team1DropdownOpeningInProgress = ref({}); // Prévention ouverture multiple dropdown
const team2DropdownOpeningInProgress = ref({}); // Prévention ouverture multiple dropdown
const availableTeams = ref([]);                  // Cache des équipes disponibles
```

### Structure des Données d'Événement

#### Modèle d'Événement (Lignes 554-570)
```javascript
{
  sport_id: null,
  country_id: null,
  league_id: null,
  team1: null,                    // ID de l'équipe 1 sélectionnée
  team2: null,                    // ID de l'équipe 2 sélectionnée
  selectedTeam1: [],              // Tableau des équipes 1 sélectionnées (format AutoComplete)
  selectedTeam2: [],              // Tableau des équipes 2 sélectionnées (format AutoComplete)
  team1SearchResults: [],         // Résultats de recherche équipe 1
  team1Loading: false,            // État de chargement équipe 1
  team2SearchResults: [],         // Résultats de recherche équipe 2
  team2Loading: false             // État de chargement équipe 2
}
```

### États des Données

#### Initialisation
- Toutes les variables sont initialisées à des valeurs vides ou `false`
- Les tableaux de résultats sont vides au démarrage
- Les états de chargement sont à `false`

#### Pendant la Recherche
- `team1Loading` / `team2Loading` passent à `true`
- Les résultats sont accumulés dans `team1SearchResults` / `team2SearchResults`
- La pagination est gérée via `team1CurrentPage` / `team2CurrentPage`

#### Après Sélection
- `selectedTeam1` / `selectedTeam2` contiennent l'équipe sélectionnée
- `team1` / `team2` contiennent l'ID de l'équipe pour l'API
- Les résultats de l'autre équipe sont filtrés pour exclure l'équipe sélectionnée

---

## Fonctions et Méthodes

### 1. Fonctions de Recherche

#### `searchTeam1(event, eventIndex, resetSearch = false)` (Lignes 979-1037)

**Objectif :** Recherche des équipes pour le champ équipe 1

**Paramètres :**
- `event` : Objet contenant la requête de recherche (`event.query`)
- `eventIndex` : Index de l'événement dans le formulaire multi-événements
- `resetSearch` : Boolean pour réinitialiser la recherche (défaut: false)

**Fonctionnement :**
1. **Validation préalable :**
   ```javascript
   if (!eventData.sport_id) {
     console.log('❌ searchTeam1: Aucun sport sélectionné pour événement', eventIndex);
     return;
   }
   ```

2. **Gestion de la requête :**
   ```javascript
   const query = event.query || '';
   eventData.team1SearchQuery = query;
   ```

3. **Réinitialisation conditionnelle :**
   ```javascript
   if (!eventData.team1SearchResults || resetSearch) {
     eventData.team1CurrentPage = 1;
     eventData.team1SearchResults = [];
   }
   ```

4. **Appel API avec exclusion :**
   ```javascript
   eventData.team1Loading = true;
   const response = await SportService.searchTeamsBySport(
     eventData.sport_id,
     query,
     eventData.team1CurrentPage,
     10,
     eventData.country_id,
     eventData.league_id,
     eventData.team2 // Exclusion de l'équipe 2
   );
   ```

5. **Traitement des résultats :**
   ```javascript
   let filteredData = response.data;
   if (eventData.team2) {
     filteredData = response.data.filter(team => team.id !== eventData.team2);
   }
   eventData.team1SearchResults = filteredData;
   ```

#### `searchTeam2(event, eventIndex, resetSearch = false)` (Lignes 1039-1090)

**Fonctionnement identique à `searchTeam1`** avec exclusion de `team1` au lieu de `team2`.

### 2. Fonctions de Sélection

#### `onTeam1Select(event, eventIndex)` (Lignes 1091-1136)

**Objectif :** Gestion de la sélection d'une équipe 1

**Fonctionnement :**
1. **Validation de la sélection :**
   ```javascript
   if (event.value && event.value.id) {
     eventData.selectedTeam1 = [event.value];
     eventData.team1 = event.value.id;
   }
   ```

2. **Fermeture du dropdown :**
   ```javascript
   team1DropdownOpeningInProgress.value[eventIndex] = true;
   const team1Ref = team1AutoCompleteRefs.value[eventIndex];
   if (team1Ref && typeof team1Ref.hide === 'function') {
     team1Ref.hide();
   }
   ```

3. **Rafraîchissement de l'équipe 2 :**
   ```javascript
   if (eventData.team2SearchResults && eventData.team2SearchResults.length > 0) {
     searchTeam2({ query: eventData.team2SearchQuery || '' }, eventIndex, true);
   }
   ```

#### `onTeam2Select(event, eventIndex)` (Lignes 1138-1183)

**Fonctionnement similaire avec rafraîchissement de l'équipe 1.**

### 3. Fonctions d'Affichage du Dropdown

#### `onTeam1DropdownShow(eventIndex)` (Lignes 1216-1264)

**Objectif :** Gestion de l'ouverture du dropdown équipe 1

**Fonctionnement :**
1. **Prévention des ouvertures multiples :**
   ```javascript
   if (team1DropdownOpeningInProgress.value[eventIndex]) {
     return;
   }
   team1DropdownOpeningInProgress.value[eventIndex] = true;
   ```

2. **Chargement initial si nécessaire :**
   ```javascript
   if ((!eventData.team1SearchResults || eventData.team1SearchResults.length === 0) && eventData.sport_id) {
     searchTeam1({ query: '' }, eventIndex, true);
   }
   ```

3. **Affichage forcé du dropdown :**
   ```javascript
   const team1Ref = team1AutoCompleteRefs.value[eventIndex];
   if (team1Ref && typeof team1Ref.show === 'function') {
     team1Ref.show();
   }
   ```

### 4. Fonctions de Pagination

#### `loadMoreTeam1()` (Lignes 1185-1197)

**Objectif :** Chargement de la page suivante pour équipe 1

**Fonctionnement :**
```javascript
async function loadMoreTeam1() {
  if (team1Loading.value || !team1HasMore.value) {
    return;
  }
  team1CurrentPage.value++;
  searchTeam1({ query: team1SearchQuery.value });
}
```

### 5. Fonctions de Défilement

#### `handleTeam1PanelScroll(event)` (Lignes 1321-1362)

**Objectif :** Gestion du lazy loading lors du défilement

**Fonctionnement :**
1. **Calcul de la position de défilement :**
   ```javascript
   const { scrollTop, scrollHeight, clientHeight } = event.target;
   const scrollPercentage = (scrollTop + clientHeight) / scrollHeight;
   ```

2. **Déclenchement du chargement :**
   ```javascript
   if (team1HasMore.value && !team1Loading.value && scrollPercentage > 0.8) {
     loadMoreTeam1();
   }
   ```

### 6. Fonctions de Nettoyage

#### `clearTeam1()` (Lignes 1873-1879)

**Objectif :** Réinitialisation complète de l'équipe 1

```javascript
function clearTeam1() {
  selectedTeam1.value = [];
  formData.value.team1 = null;
}
```

#### `resetEventFields()` (Lignes 1937-1960)

**Objectif :** Réinitialisation de tous les champs d'un événement

```javascript
eventData.team1 = null;
eventData.team2 = null;
eventData.selectedTeam1 = [];
eventData.selectedTeam2 = [];
eventData.team1SearchResults = [];
eventData.team2SearchResults = [];
```

---

## Watchers et Computed Properties

### Watchers Identifiés

#### 1. Watcher sur `betTypeValue` (Lignes 2140-2150)
```javascript
watch(betTypeValue, (newValue) => {
  // Recalcul du capital et pourcentage lors du changement de type de pari
});
```

#### 2. Watcher sur `formData.value.stake` (Lignes 2150-2160)
```javascript
watch(() => formData.value.stake, (newStake) => {
  // Recalcul de la mise en pourcentage
});
```

#### 3. Watcher sur les résultats d'événements (Lignes 2160-2170)
```javascript
watch([
  () => events.value.map(e => e.result),
  () => currentEvent.value.result
], () => {
  // Recalcul du résultat global du pari
});
```

#### 4. Watcher sur les changements de sport (Lignes 2170-2180)
```javascript
watch([
  () => eventCards.value.map(e => e.sport_id),
  () => formData.value.sport_id
], () => {
  // Réinitialisation du type de pari lors du changement de sport
});
```

### Computed Properties Identifiées

#### 1. `isDarkTheme` (Ligne 2140)
```javascript
const isDarkTheme = computed(() => {
  // Détection du thème sombre
});
```

#### 2. `potentialWin` (Ligne 2141)
```javascript
const potentialWin = computed(() => {
  // Calcul du gain potentiel basé sur les cotes et la mise
});
```

#### 3. `showSportFields` (Ligne 2142)
```javascript
const showSportFields = computed(() => {
  // Détermine si les champs sport doivent être affichés
});
```

#### 4. `filteredBetTypes` (Ligne 2143)
```javascript
const filteredBetTypes = computed(() => {
  // Filtrage des types de paris selon le sport sélectionné
});
```

#### 5. `isFormValid` (Ligne 2144)
```javascript
const isFormValid = computed(() => {
  // Validation globale du formulaire incluant les équipes
});
```

### Impact sur les Champs Équipes

Les watchers et computed properties n'affectent pas directement les champs équipes, mais ils participent à l'écosystème global du formulaire :

- **Validation globale :** `isFormValid` inclut la validation des équipes
- **Réinitialisation :** Les changements de sport déclenchent la réinitialisation des équipes
- **Calculs :** Les équipes sélectionnées participent aux calculs de gains potentiels

---

## Validation et Contraintes

### 1. Règles de Validation Principales

#### Validation des Équipes Différentes (Lignes 1689-1692)
```javascript
if (formData.value.team1 && formData.value.team2 && formData.value.team1 === formData.value.team2) {
  errors.value.team1 = 'Les deux équipes doivent être différentes';
  errors.value.team2 = 'Les deux équipes doivent être différentes';
  isValid = false;
}
```

**Contraintes :**
- Les équipes 1 et 2 ne peuvent pas être identiques
- Validation déclenchée lors de la soumission du formulaire
- Messages d'erreur affichés sur les deux champs

#### Validation des Champs Requis (Ligne 1891)
```javascript
if (!formData.value.sport_id || !formData.value.league || !formData.value.team1 || !formData.value.team2 || !currentEvent.value.description) {
  // Erreur : champs requis manquants
}
```

**Contraintes :**
- `team1` et `team2` sont obligatoires pour ajouter un événement
- Dépendance avec `sport_id` et `league`
- Validation lors de l'ajout d'événement

### 2. Contraintes d'Interface

#### Désactivation Conditionnelle
```vue
:disabled="!eventData.sport_id"
```

**Contraintes :**
- Les champs équipes sont désactivés si aucun sport n'est sélectionné
- Prévention de la sélection d'équipes sans contexte sportif

#### Validation Visuelle
```vue
:class="{ 'p-invalid': errors[`team1-${eventIndex}`] }"
```

**Contraintes :**
- Affichage visuel des erreurs via la classe `p-invalid`
- Messages d'erreur contextuels sous chaque champ

### 3. Contraintes de Données

#### Exclusion Mutuelle
```javascript
// Dans searchTeam1
excludedTeamId: eventData.team2

// Dans searchTeam2  
excludedTeamId: eventData.team1
```

**Contraintes :**
- Une équipe sélectionnée dans un champ est automatiquement exclue de l'autre
- Filtrage côté client ET côté serveur
- Rafraîchissement automatique des résultats lors des changements

#### Validation de Sélection
```vue
forceSelection
```

**Contraintes :**
- Seules les équipes de la liste peuvent être sélectionnées
- Pas de saisie libre autorisée
- Validation automatique par PrimeVue

### 4. Contraintes de Performance

#### Recherche Minimale
```vue
:minLength="0"
```

**Contraintes :**
- Recherche déclenchée dès l'ouverture du dropdown
- Pas de caractères minimum requis

#### Pagination
```javascript
// Limite de 10 résultats par page
const response = await SportService.searchTeamsBySport(
  eventData.sport_id,
  query,
  eventData.team1CurrentPage,
  10, // Limite par page
  // ...
);
```

**Contraintes :**
- Maximum 10 équipes chargées par requête
- Chargement progressif via défilement
- Optimisation des performances réseau

---

## Dépendances et Interactions

### 1. Dépendances Externes

#### Services API
```javascript
import { SportService } from '@/service/SportService';
```

**Méthodes utilisées :**
- `SportService.searchTeamsBySport()` : Recherche d'équipes par sport
- `SportService.getTeamsBySport()` : Récupération d'équipes par sport
- `SportService.getTeamsByLeague()` : Récupération d'équipes par ligue

#### Composants PrimeVue
```javascript
import AutoComplete from 'primevue/autocomplete';
import ProgressSpinner from 'primevue/progressspinner';
```

**Fonctionnalités utilisées :**
- `AutoComplete` : Composant principal des champs équipes
- `ProgressSpinner` : Indicateur de chargement dans les footers

### 2. Interactions avec d'Autres Champs

#### Dépendance avec le Champ Sport
```javascript
// Réinitialisation lors du changement de sport
function onSportSelect(sport, eventIndex) {
  // ...
  eventData.team1 = null;
  eventData.team2 = null;
  eventData.selectedTeam1 = [];
  eventData.selectedTeam2 = [];
}
```

**Interactions :**
- Changement de sport → Réinitialisation des équipes
- Sport requis pour activer les champs équipes
- Filtrage des équipes par sport

#### Dépendance avec le Champ Ligue
```javascript
// Réinitialisation lors du changement de ligue
function onLeagueSelect(league, eventIndex) {
  // ...
  eventData.team1 = null;
  eventData.team2 = null;
}
```

**Interactions :**
- Changement de ligue → Réinitialisation des équipes
- Filtrage des équipes par ligue (optionnel)

#### Dépendance avec le Champ Pays
```javascript
// Filtrage par pays dans la recherche
const response = await SportService.searchTeamsBySport(
  eventData.sport_id,
  query,
  eventData.team1CurrentPage,
  10,
  eventData.country_id, // Filtrage par pays
  eventData.league_id,
  excludedTeamId
);
```

**Interactions :**
- Pays sélectionné → Filtrage des équipes par pays
- Changement de pays → Rafraîchissement des résultats

### 3. Interactions Internes

#### Exclusion Mutuelle
```javascript
// Rafraîchissement de l'équipe 2 lors de la sélection de l'équipe 1
if (eventData.team2SearchResults && eventData.team2SearchResults.length > 0) {
  searchTeam2({ query: eventData.team2SearchQuery || '' }, eventIndex, true);
}
```

**Mécanisme :**
- Sélection équipe 1 → Exclusion de cette équipe des résultats équipe 2
- Sélection équipe 2 → Exclusion de cette équipe des résultats équipe 1
- Rafraîchissement automatique des listes

#### Gestion des Références
```javascript
const team1AutoCompleteRefs = ref({});
const team2AutoCompleteRefs = ref({});

// Attribution des références
:ref="(el) => { if (el) team1AutoCompleteRefs[eventIndex] = el }"
```

**Utilisation :**
- Contrôle programmatique des dropdowns
- Fermeture forcée après sélection
- Ouverture forcée lors du focus

### 4. Interactions avec le Formulaire Global

#### Validation Globale
```javascript
function validateForm() {
  // Validation des équipes dans le contexte global
  if (formData.value.team1 && formData.value.team2 && formData.value.team1 === formData.value.team2) {
    errors.value.team1 = 'Les deux équipes doivent être différentes';
    errors.value.team2 = 'Les deux équipes doivent être différentes';
    isValid = false;
  }
}
```

#### Soumission du Formulaire
```javascript
// Inclusion des équipes dans les données soumises
team1_id: eventData.team1,
team2_id: eventData.team2,
```

#### Gestion Multi-Événements
```javascript
// Ajout d'événement avec équipes
const newEvent = {
  // ...
  team1: selectedTeam1.value,
  team2: selectedTeam2.value,
  // ...
};
```

---

## Cycle de Vie et Comportement

### 1. Initialisation

#### Montage du Composant
```javascript
// État initial des variables
const team1SearchResults = ref([]);
const team1Loading = ref(false);
const selectedTeam1 = ref([]);
// ... équivalents pour team2
```

**Étapes :**
1. Initialisation des variables réactives
2. Création des références vides pour les composants
3. Configuration des états de chargement à `false`

#### Premier Affichage
```vue
:disabled="!eventData.sport_id"
```

**Comportement :**
- Champs désactivés par défaut (aucun sport sélectionné)
- Placeholders affichés
- Aucune donnée chargée

### 2. Activation des Champs

#### Sélection d'un Sport
```javascript
function onSportSelect(sport, eventIndex) {
  // Activation des champs équipes
  eventData.sport_id = sport.id;
  // Réinitialisation des équipes précédentes
  eventData.team1 = null;
  eventData.team2 = null;
}
```

**Séquence :**
1. Sport sélectionné → Activation des champs équipes
2. Réinitialisation des sélections précédentes
3. Préparation pour la recherche d'équipes

### 3. Interaction Utilisateur

#### Ouverture du Dropdown
```javascript
function onTeam1DropdownShow(eventIndex) {
  // 1. Vérification des ouvertures multiples
  if (team1DropdownOpeningInProgress.value[eventIndex]) return;
  
  // 2. Chargement initial si nécessaire
  if (!eventData.team1SearchResults.length && eventData.sport_id) {
    searchTeam1({ query: '' }, eventIndex, true);
  }
  
  // 3. Affichage forcé
  const team1Ref = team1AutoCompleteRefs.value[eventIndex];
  team1Ref.show();
}
```

**Séquence :**
1. Clic/Focus sur le champ
2. Vérification de l'état d'ouverture
3. Chargement des données si nécessaire
4. Affichage du dropdown

#### Recherche d'Équipes
```javascript
async function searchTeam1(event, eventIndex, resetSearch = false) {
  // 1. Validation du sport
  if (!eventData.sport_id) return;
  
  // 2. Préparation de la requête
  const query = event.query || '';
  eventData.team1Loading = true;
  
  // 3. Appel API
  const response = await SportService.searchTeamsBySport(/* ... */);
  
  // 4. Filtrage et affichage
  eventData.team1SearchResults = filteredData;
  eventData.team1Loading = false;
}
```

**Séquence :**
1. Saisie utilisateur → Déclenchement de `@complete`
2. Validation des prérequis (sport sélectionné)
3. Affichage de l'indicateur de chargement
4. Appel API avec filtres
5. Traitement et affichage des résultats

#### Sélection d'une Équipe
```javascript
function onTeam1Select(event, eventIndex) {
  // 1. Validation et stockage
  if (event.value && event.value.id) {
    eventData.selectedTeam1 = [event.value];
    eventData.team1 = event.value.id;
  }
  
  // 2. Fermeture du dropdown
  const team1Ref = team1AutoCompleteRefs.value[eventIndex];
  team1Ref.hide();
  
  // 3. Mise à jour de l'autre équipe
  searchTeam2({ query: eventData.team2SearchQuery || '' }, eventIndex, true);
}
```

**Séquence :**
1. Clic sur une option → Déclenchement de `@item-select`
2. Stockage de la sélection
3. Fermeture automatique du dropdown
4. Exclusion de l'équipe sélectionnée des résultats de l'autre champ
5. Rafraîchissement des résultats de l'autre équipe

### 4. Pagination et Défilement

#### Défilement dans la Liste
```javascript
function handleTeam1PanelScroll(event) {
  const { scrollTop, scrollHeight, clientHeight } = event.target;
  const scrollPercentage = (scrollTop + clientHeight) / scrollHeight;
  
  if (team1HasMore.value && !team1Loading.value && scrollPercentage > 0.8) {
    loadMoreTeam1();
  }
}
```

**Séquence :**
1. Défilement utilisateur → Déclenchement de `@panel-scroll`
2. Calcul de la position de défilement
3. Vérification des conditions (plus de données, pas de chargement en cours)
4. Déclenchement du chargement de la page suivante

#### Chargement de Pages Supplémentaires
```javascript
async function loadMoreTeam1() {
  team1CurrentPage.value++;
  searchTeam1({ query: team1SearchQuery.value });
}
```

**Séquence :**
1. Incrémentation du numéro de page
2. Nouvelle recherche avec la page suivante
3. Ajout des résultats aux résultats existants

### 5. Nettoyage et Réinitialisation

#### Changement de Sport/Ligue
```javascript
function onSportClear(eventIndex) {
  // Réinitialisation complète
  eventData.team1 = null;
  eventData.team2 = null;
  eventData.selectedTeam1 = [];
  eventData.selectedTeam2 = [];
  eventData.team1SearchResults = [];
  eventData.team2SearchResults = [];
}
```

#### Soumission du Formulaire
```javascript
function resetEventFields() {
  // Réinitialisation pour le prochain événement
  eventData.team1 = null;
  eventData.team2 = null;
  eventData.selectedTeam1 = [];
  eventData.selectedTeam2 = [];
}
```

### 6. Démontage du Composant

#### Nettoyage des Event Listeners
```javascript
onBeforeUnmount(() => {
  // Suppression des listeners de défilement
  if (panel.hasTeam1ScrollListener) {
    panel.removeEventListener('scroll', handleTeam1PanelScroll);
  }
  if (panel.hasTeam2ScrollListener) {
    panel.removeEventListener('scroll', handleTeam2PanelScroll);
  }
});
```

---

## Gestion des Erreurs

### 1. Erreurs de Validation

#### Messages d'Erreur Affichés
```vue
<small v-if="errors[`team1-${eventIndex}`]" class="text-red-500 block mt-1">
  {{ errors[`team1-${eventIndex}`] }}
</small>
```

**Types d'erreurs :**
- `"Les deux équipes doivent être différentes"` : Équipes identiques sélectionnées
- Messages contextuels selon la validation

#### Styling des Erreurs
```vue
:class="{ 'p-invalid': errors[`team1-${eventIndex}`] }"
```

**Comportement :**
- Bordure rouge sur les champs en erreur
- Message d'erreur en rouge sous le champ
- Validation en temps réel

### 2. Erreurs de Chargement

#### Gestion des Erreurs API
```javascript
try {
  const response = await SportService.searchTeamsBySport(/* ... */);
  // Traitement des résultats
} catch (error) {
  console.error('Erreur lors de la recherche d\'équipes:', error);
  eventData.team1Loading = false;
}
```

**Stratégies :**
- Logging des erreurs dans la console
- Arrêt de l'indicateur de chargement
- Maintien de l'état précédent

#### Gestion des Images Manquantes
```vue
<img 
  :src="`${apiBaseUrl}/storage/team_logos/${slotProps.value.id}.png`"
  @error="(e) => e.target.style.display = 'none'"
/>
```

**Comportement :**
- Masquage automatique des logos manquants
- Pas d'interruption de l'interface utilisateur

### 3. Erreurs d'État

#### Prévention des Ouvertures Multiples
```javascript
if (team1DropdownOpeningInProgress.value[eventIndex]) {
  return; // Prévention de l'ouverture multiple
}
```

#### Validation des Prérequis
```javascript
if (!eventData.sport_id) {
  console.log('❌ searchTeam1: Aucun sport sélectionné');
  return;
}
```

**Stratégies :**
- Validation préalable avant les actions
- Messages de debug informatifs
- Arrêt silencieux des actions invalides

### 4. Erreurs de Référence

#### Vérification des Références de Composants
```javascript
const team1Ref = team1AutoCompleteRefs.value[eventIndex];
if (team1Ref && typeof team1Ref.hide === 'function') {
  team1Ref.hide();
} else {
  console.log('❌ Référence du composant équipe 1 non trouvée');
}
```

**Stratégies :**
- Vérification de l'existence des références
- Vérification des méthodes disponibles
- Fallback gracieux en cas d'échec

---

## Performance et Optimisations

### 1. Optimisations de Recherche

#### Pagination Intelligente
```javascript
// Limite de 10 résultats par page
const response = await SportService.searchTeamsBySport(
  eventData.sport_id,
  query,
  eventData.team1CurrentPage,
  10, // Optimisation : petites pages
  // ...
);
```

**Avantages :**
- Réduction de la charge réseau
- Amélioration du temps de réponse initial
- Chargement progressif selon les besoins

#### Lazy Loading
```javascript
function handleTeam1PanelScroll(event) {
  const scrollPercentage = (scrollTop + clientHeight) / scrollHeight;
  if (scrollPercentage > 0.8) { // Déclenchement à 80%
    loadMoreTeam1();
  }
}
```

**Avantages :**
- Chargement anticipé avant la fin de la liste
- Expérience utilisateur fluide
- Optimisation de la bande passante

### 2. Optimisations de Rendu

#### Références Dynamiques
```vue
:ref="(el) => { if (el) team1AutoCompleteRefs[eventIndex] = el }"
```

**Avantages :**
- Gestion efficace des composants multiples
- Accès direct aux instances de composants
- Évite les recherches DOM coûteuses

#### Affichage Conditionnel
```vue
<template #footer v-if="team1HasMore">
  <!-- Footer de pagination seulement si nécessaire -->
</template>
```

**Avantages :**
- Rendu conditionnel des éléments
- Réduction du DOM inutile
- Amélioration des performances de rendu

### 3. Optimisations de Données

#### Cache des Équipes
```javascript
const availableTeams = ref([]); // Cache global
```

**Utilisation :**
- Stockage temporaire des équipes chargées
- Réduction des appels API répétitifs
- Amélioration de la réactivité

#### Filtrage Côté Client
```javascript
if (eventData.team2) {
  filteredData = response.data.filter(team => team.id !== eventData.team2);
}
```

**Avantages :**
- Réduction de la charge serveur
- Filtrage instantané
- Meilleure expérience utilisateur

### 4. Optimisations d'État

#### Prévention des Actions Multiples
```javascript
const team1DropdownOpeningInProgress = ref({});
const team2DropdownOpeningInProgress = ref({});
```

**Avantages :**
- Évite les appels API simultanés
- Prévient les états incohérents
- Améliore la stabilité

#### Gestion Efficace des Événements
```javascript
// Nettoyage des event listeners
onBeforeUnmount(() => {
  if (panel.hasTeam1ScrollListener) {
    panel.removeEventListener('scroll', handleTeam1PanelScroll);
  }
});
```

**Avantages :**
- Prévention des fuites mémoire
- Nettoyage automatique des ressources
- Amélioration des performances globales

### 5. Optimisations UX

#### Indicateurs de Chargement
```vue
:loading="eventData.team1Loading"
```

**Avantages :**
- Feedback visuel immédiat
- Indication claire de l'état du système
- Amélioration de la perception de performance

#### Placeholders Intelligents
```vue
:placeholder="eventData.selectedTeam1 && eventData.selectedTeam1.length > 0 ? '' : 'Équipe 1'"
```

**Avantages :**
- Interface claire et intuitive
- Guidance contextuelle
- Réduction de la confusion utilisateur

---

## Conclusion

Les champs "Équipe 1" et "Équipe 2" dans `AddBetForm.vue` constituent un système complexe et bien orchestré qui offre une expérience utilisateur riche tout en maintenant des performances optimales. 

### Points Clés :

1. **Architecture Modulaire :** Séparation claire des responsabilités entre recherche, sélection, validation et affichage
2. **Performance Optimisée :** Pagination, lazy loading, et cache pour une expérience fluide
3. **Validation Robuste :** Contraintes métier respectées avec feedback utilisateur immédiat
4. **Accessibilité :** Support complet des standards ARIA et navigation clavier
5. **Maintenabilité :** Code structuré avec gestion d'erreurs et nettoyage approprié

Cette implémentation démontre une approche professionnelle du développement d'interfaces utilisateur complexes avec Vue.js et PrimeVue.