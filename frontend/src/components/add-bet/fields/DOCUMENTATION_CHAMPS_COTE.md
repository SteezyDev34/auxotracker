# Documentation Ultra Détaillée des Champs de Cote - Architecture Modulaire

## Table des Matières

1. [Vue d'ensemble Architecturale](#vue-densemble-architecturale)
2. [Composants Dédiés](#composants-dédiés)
3. [GlobalOddsField.vue](#globaloddsfieldvue)
4. [EventOddsField.vue](#eventoddsfieldvue)
5. [OddsCalculator.vue](#oddscalculatorvue)
6. [Intégration dans AddBetForm.vue](#intégration-dans-addbetformvue)
7. [Flux de Données et Communication](#flux-de-données-et-communication)
8. [Système de Validation Distribué](#système-de-validation-distribué)
9. [Gestion des Événements](#gestion-des-événements)
10. [Optimisations et Performance](#optimisations-et-performance)
11. [Migration et Compatibilité](#migration-et-compatibilité)
12. [Tests et Debugging](#tests-et-debugging)

---

## Vue d'ensemble Architecturale

### Nouvelle Architecture Modulaire

Les champs de cote ont été refactorisés en composants dédiés pour améliorer :

- **Réutilisabilité** : Composants autonomes et réutilisables
- **Maintenabilité** : Code organisé et spécialisé
- **Testabilité** : Tests unitaires ciblés
- **Performance** : Optimisations spécifiques par composant
- **Évolutivité** : Ajout facile de nouvelles fonctionnalités

### Architecture Technique Modulaire

```javascript
// Nouvelle structure modulaire
AddBetForm.vue
├── GlobalOddsField.vue (Cote globale + Gain potentiel)
├── EventOddsField.vue (Cotes d'événements)
├── OddsCalculator.vue (Calcul automatique)
└── Communication par événements
```

### Flux de Données Modernisé

```
Saisie → Composant Dédié → Validation → Événement → Parent → Calcul → Mise à jour
```

---

## Composants Dédiés

### Vue d'ensemble des Composants

La nouvelle architecture se compose de trois composants principaux :

1. **GlobalOddsField.vue** : Gestion de la cote globale et affichage du gain potentiel
2. **EventOddsField.vue** : Gestion des cotes d'événements individuels
3. **OddsCalculator.vue** : Calcul automatique de la cote globale

### Avantages de la Modularité

- **Séparation des responsabilités** : Chaque composant a un rôle spécifique
- **Réutilisabilité** : Composants utilisables dans d'autres contextes
- **Maintenance simplifiée** : Code plus facile à comprendre et modifier
- **Tests ciblés** : Tests unitaires spécifiques à chaque composant

---

## GlobalOddsField.vue

### Responsabilités

- Affichage et saisie de la cote globale
- Calcul et affichage du gain potentiel
- Validation de la cote globale
- Gestion des erreurs spécifiques

### Props

```javascript
const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: null
  },
  stake: {
    type: [Number, String],
    default: 0
  },
  showPotentialWin: {
    type: Boolean,
    default: true
  },
  error: {
    type: String,
    default: ''
  },
  minValue: {
    type: Number,
    default: 1
  },
  maxValue: {
    type: Number,
    default: 999
  },
  decimalPlaces: {
    type: Number,
    default: 2
  }
})
```

### Événements Émis

```javascript
const emit = defineEmits([
  'update:modelValue',
  'odds-changed',
  'error',
  'valid'
])
```

### Fonctionnalités Clés

- **Normalisation automatique** : Remplacement des virgules par des points
- **Validation temps réel** : Contrôle des valeurs min/max
- **Calcul du gain potentiel** : Affichage automatique basé sur la mise
- **Gestion des erreurs** : Messages d'erreur contextuels

---

## EventOddsField.vue

### Responsabilités

- Affichage et saisie des cotes d'événements
- Validation des cotes individuelles
- Communication avec le système de calcul automatique
- Gestion des erreurs par événement

### Props

```javascript
const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: null
  },
  eventIndex: {
    type: Number,
    required: true
  },
  error: {
    type: String,
    default: ''
  },
  minValue: {
    type: Number,
    default: 1
  },
  maxValue: {
    type: Number,
    default: 999
  },
  decimalPlaces: {
    type: Number,
    default: 2
  }
})
```

### Événements Émis

```javascript
const emit = defineEmits([
  'update:modelValue',
  'odds-changed',
  'error',
  'valid'
])
```

### Fonctionnalités Clés

- **Identification unique** : Chaque champ est identifié par son index
- **Validation spécialisée** : Règles de validation pour les cotes d'événements
- **Communication bidirectionnelle** : Émission d'événements vers le parent
- **Interface cohérente** : Design uniforme avec GlobalOddsField

---

## OddsCalculator.vue

### Responsabilités

- Calcul automatique de la cote globale
- Surveillance des changements de cotes d'événements
- Validation de la possibilité de calcul
- Émission d'événements de calcul

### Props

```javascript
const props = defineProps({
  eventCards: {
    type: Array,
    default: () => []
  },
  globalOdds: {
    type: [Number, String],
    default: null
  },
  autoCalculate: {
    type: Boolean,
    default: true
  },
  decimalPlaces: {
    type: Number,
    default: 2
  }
})
```

### Événements Émis

```javascript
const emit = defineEmits([
  'global-odds-calculated',
  'calculation-cleared',
  'calculation-failed'
])
```

### Algorithme de Calcul

```javascript
function calculateGlobalOdds() {
  if (!canCalculateAutomatically.value) {
    return null
  }
  
  let result = 1
  validEventOdds.value.forEach(odds => {
    result *= parseFloat(odds)
  })
  
  return parseFloat(result.toFixed(props.decimalPlaces))
}
```

### Fonctionnalités Clés

- **Calcul automatique** : Surveillance des changements et recalcul
- **Validation intelligente** : Vérification de la validité des cotes
- **Performance optimisée** : Calcul uniquement quand nécessaire
- **Gestion d'erreurs** : Émission d'événements en cas d'échec

---

## Intégration dans AddBetForm.vue

### Imports des Composants

```javascript
import GlobalOddsField from './fields/GlobalOddsField.vue'
import EventOddsField from './fields/EventOddsField.vue'
import OddsCalculator from './fields/OddsCalculator.vue'
```

### Utilisation dans le Template

```vue
<!-- Cote globale -->
<GlobalOddsField
  v-model="formData.global_odds"
  :stake="calculatedStake"
  :show-potential-win="true"
  :error="errors.global_odds"
  @error="handleGlobalOddsError"
  @valid="handleGlobalOddsValid"
/>

<!-- Cotes d'événements -->
<EventOddsField
  v-for="(eventCard, index) in eventCards"
  :key="`event-odds-${index}`"
  v-model="eventCard.odds"
  :event-index="index"
  :error="errors[`event_odds-${index}`]"
  @odds-changed="onEventOddsChanged"
  @error="handleEventOddsError"
  @valid="handleEventOddsValid"
/>

<!-- Calculateur automatique -->
<OddsCalculator
  :event-cards="eventCards"
  :global-odds="formData.global_odds"
  :auto-calculate="true"
  @global-odds-calculated="onGlobalOddsCalculated"
  @calculation-cleared="onGlobalOddsCleared"
  @calculation-failed="onGlobalOddsCalculationFailed"
/>
```

### Gestionnaires d'Événements

```javascript
/**
 * Gérer le calcul automatique de la cote globale
 */
function onGlobalOddsCalculated(calculatedOdds) {
  formData.value.global_odds = calculatedOdds
  delete errors.value.global_odds
}

/**
 * Gérer les erreurs de cote globale
 */
function handleGlobalOddsError(message) {
  if (message) {
    errors.value.global_odds = message
  } else {
    delete errors.value.global_odds
  }
}

/**
 * Gérer les erreurs de cote d'événement
 */
function handleEventOddsError(eventIndex, message) {
  if (message) {
    errors.value[`event_odds-${eventIndex}`] = message
  } else {
    delete errors.value[`event_odds-${eventIndex}`]
  }
}
```

---

## Flux de Données et Communication

### Schéma de Communication

```
EventOddsField → onEventOddsChanged → OddsCalculator → calculateGlobalOdds → onGlobalOddsCalculated → GlobalOddsField
```

### Types d'Événements

1. **Événements de Données** : `update:modelValue`, `odds-changed`
2. **Événements de Validation** : `error`, `valid`
3. **Événements de Calcul** : `global-odds-calculated`, `calculation-failed`

### Gestion des États

- **État Local** : Chaque composant gère son état interne
- **État Partagé** : Communication via événements et props
- **État Global** : Maintenu dans AddBetForm.vue

---

## Système de Validation Distribué

### Validation par Composant

Chaque composant gère sa propre validation :

- **GlobalOddsField** : Validation de la cote globale
- **EventOddsField** : Validation des cotes d'événements
- **OddsCalculator** : Validation de la possibilité de calcul

### Agrégation des Erreurs

```javascript
// Dans AddBetForm.vue
const errors = ref({})

function handleGlobalOddsError(message) {
  if (message) {
    errors.value.global_odds = message
  } else {
    delete errors.value.global_odds
  }
}

function handleEventOddsError(eventIndex, message) {
  if (message) {
    errors.value[`event_odds-${eventIndex}`] = message
  } else {
    delete errors.value[`event_odds-${eventIndex}`]
  }
}
```

### Validation Globale

```javascript
function validateForm() {
  // Les erreurs sont déjà gérées par les composants individuels
  const isValid = Object.keys(errors.value).length === 0
  return isValid
}
```

---

## Gestion des Événements

### Événements Personnalisés

Chaque composant émet des événements spécifiques :

```javascript
// GlobalOddsField.vue
emit('update:modelValue', newValue)
emit('odds-changed', { value: newValue, type: 'global' })
emit('error', errorMessage)
emit('valid', isValid)

// EventOddsField.vue
emit('update:modelValue', newValue)
emit('odds-changed', { value: newValue, eventIndex: props.eventIndex })
emit('error', props.eventIndex, errorMessage)
emit('valid', props.eventIndex, isValid)

// OddsCalculator.vue
emit('global-odds-calculated', calculatedOdds)
emit('calculation-cleared')
emit('calculation-failed', errorMessage)
```

### Écoute des Événements

```javascript
// Dans AddBetForm.vue
function onEventOddsChanged(eventData) {
  console.log('Cote d\'événement modifiée:', eventData)
  // Le calcul automatique est géré par OddsCalculator
}

function onGlobalOddsCalculated(calculatedOdds) {
  console.log('Cote globale calculée:', calculatedOdds)
  formData.value.global_odds = calculatedOdds
  delete errors.value.global_odds
}
```

---

## Optimisations et Performance

### Optimisations par Composant

1. **GlobalOddsField** :
   - Calcul du gain potentiel en propriété calculée
   - Debounce sur la validation
   - Mise à jour conditionnelle

2. **EventOddsField** :
   - Validation locale avant émission
   - Normalisation optimisée
   - Gestion mémoire des erreurs

3. **OddsCalculator** :
   - Calcul différé avec watchers
   - Validation intelligente
   - Cache des résultats

### Gestion Mémoire

```javascript
// Nettoyage automatique des erreurs
function cleanupErrors() {
  Object.keys(errors.value).forEach(key => {
    if (key.startsWith('event_odds-')) {
      const eventIndex = parseInt(key.split('-')[1])
      if (eventIndex >= eventCards.value.length) {
        delete errors.value[key]
      }
    }
  })
}
```

### Performance de Rendu

- **v-model optimisé** : Utilisation efficace de la réactivité Vue 3
- **Événements ciblés** : Émission uniquement quand nécessaire
- **Propriétés calculées** : Cache automatique des calculs

---

## Migration et Compatibilité

### Changements par rapport à l'Ancienne Version

1. **Fonctions Supprimées** :
   - `handleOddsInput()`
   - `handleEventOddsInput()`
   - `handleOddsKeypress()`
   - `handleEventOddsKeypress()`
   - `calculateGlobalOdds()`
   - Propriété calculée `potentialWin`

2. **Nouvelles Fonctions** :
   - `onEventOddsChanged()`
   - `handleEventOddsError()`
   - `handleEventOddsValid()`
   - `handleGlobalOddsError()`
   - `handleGlobalOddsValid()`
   - `onGlobalOddsCalculated()`
   - `onGlobalOddsCleared()`
   - `onGlobalOddsCalculationFailed()`

### Compatibilité des Données

- **Structure des données** : Inchangée (`formData.global_odds`, `eventCards[].odds`)
- **Validation** : Améliorée avec gestion distribuée
- **API** : Compatible avec l'existant

### Guide de Migration

1. **Remplacement des champs** : Utiliser les nouveaux composants
2. **Mise à jour des gestionnaires** : Adapter aux nouveaux événements
3. **Tests** : Vérifier le fonctionnement des nouvelles fonctionnalités

---

## Tests et Debugging

### Tests Unitaires par Composant

```javascript
// GlobalOddsField.test.js
describe('GlobalOddsField', () => {
  test('calcule correctement le gain potentiel', () => {
    // Test du calcul
  })
  
  test('valide les cotes correctement', () => {
    // Test de validation
  })
  
  test('émet les événements appropriés', () => {
    // Test des événements
  })
})

// EventOddsField.test.js
describe('EventOddsField', () => {
  test('normalise les valeurs d\'entrée', () => {
    // Test de normalisation
  })
  
  test('gère les erreurs par événement', () => {
    // Test de gestion d'erreurs
  })
})

// OddsCalculator.test.js
describe('OddsCalculator', () => {
  test('calcule la cote globale correctement', () => {
    // Test de calcul
  })
  
  test('gère les cas d\'erreur', () => {
    // Test de gestion d'erreurs
  })
})
```

### Debugging et Logs

```javascript
// Logs de debugging dans chaque composant
console.log('GlobalOddsField - Valeur mise à jour:', newValue)
console.log('EventOddsField - Cote événement modifiée:', eventData)
console.log('OddsCalculator - Calcul effectué:', result)
console.log('AddBetForm - Événement reçu:', eventType, data)
```

### Outils de Développement

- **Vue DevTools** : Inspection des composants et événements
- **Console Logs** : Traçage des flux de données
- **Tests E2E** : Validation du comportement utilisateur

---

## Résumé de l'Architecture Modulaire

### Avantages Obtenus

1. **Code plus maintenable** : Séparation claire des responsabilités
2. **Réutilisabilité accrue** : Composants autonomes
3. **Tests simplifiés** : Tests unitaires ciblés
4. **Performance optimisée** : Optimisations spécifiques
5. **Évolutivité améliorée** : Ajout facile de fonctionnalités

### Structure Finale

```
src/components/add-bet/fields/
├── GlobalOddsField.vue (Cote globale + Gain potentiel)
├── EventOddsField.vue (Cotes d'événements)
├── OddsCalculator.vue (Calcul automatique)
└── DOCUMENTATION_CHAMPS_COTE.md (Cette documentation)
```

### Intégration Réussie

La nouvelle architecture modulaire offre une solution robuste, maintenable et évolutive pour la gestion des champs de cote dans l'application de paris sportifs.

### Variables Réactives Principales

```javascript
// Données du formulaire principal
const formData = ref({
  global_odds: null,           // Cote globale (Number|null)
  stake: null,                 // Mise en euros (Number|null)
  bet_type: 'simple',          // Type de pari ('simple'|'combine')
  // ... autres propriétés
});

// Événement en cours de saisie
const currentEvent = ref({
  odds: null,                  // Cote de l'événement (Number|null)
  sport_id: null,              // ID du sport
  league_id: null,             // ID de la ligue
  team_home: '',               // Équipe domicile
  team_away: '',               // Équipe extérieur
  // ... autres propriétés
});

// Liste des événements ajoutés (paris combinés)
const eventCards = ref([]);    // Array d'objets événement

// Gestion des erreurs
const errors = ref({});        // Objet contenant les erreurs de validation

// États de l'interface
const loading = ref(false);    // État de chargement global
const isSubmitting = ref(false); // État de soumission
```

### Structure d'un Événement

```javascript
// Structure complète d'un événement dans eventCards
{
  id: String,                  // Identifiant unique généré
  odds: Number,                // Cote de l'événement (obligatoire)
  sport_id: Number,            // ID du sport sélectionné
  league_id: Number,           // ID de la ligue sélectionnée
  team_home: String,           // Nom équipe domicile
  team_away: String,           // Nom équipe extérieur
  bet_choice: String,          // Choix du pari ('1', 'N', '2', etc.)
  event_date: String,          // Date de l'événement (ISO)
  created_at: Date,            // Timestamp de création
  updated_at: Date             // Timestamp de dernière modification
}
```

---

## Champ Cote Globale (global_odds)

### Template HTML Complet

```vue
<div class="flex flex-col space-y-1">
  <label for="global_odds" class="text-xs font-medium text-gray-700">
    Cote
  </label>
  <InputText 
    id="global_odds" 
    v-model="formData.global_odds" 
    type="text"
    placeholder="Cote"
    class="w-full text-xs"
    :class="{ 'p-invalid': errors.global_odds }"
    :disabled="isGlobalOddsCalculated"
    @input="handleOddsInput"
    @keypress="handleOddsKeypress"
    @blur="validateGlobalOdds"
    @focus="clearGlobalOddsError"
  />
  <small v-if="errors.global_odds" class="text-red-500 text-xs truncate">
    {{ errors.global_odds }}
  </small>
  <small v-if="isGlobalOddsCalculated" class="text-blue-500 text-xs">
    Calculée automatiquement
  </small>
</div>
```

### Propriétés et Attributs Détaillés

| Attribut | Type | Valeur | Description | Comportement |
|----------|------|--------|-------------|--------------|
| `id` | String | `"global_odds"` | Identifiant unique | Liaison label/input |
| `v-model` | Reactive | `formData.global_odds` | Liaison bidirectionnelle | Mise à jour automatique |
| `type` | String | `"text"` | Type d'input HTML | Permet saisie décimale |
| `placeholder` | String | `"Cote"` | Texte d'aide | Guide utilisateur |
| `class` | String | `"w-full text-xs"` | Classes CSS de base | Style par défaut |
| `:class` | Object | `{ 'p-invalid': errors.global_odds }` | Classes conditionnelles | Indication erreur |
| `:disabled` | Boolean | `isGlobalOddsCalculated` | État désactivé | Calcul automatique |
| `@input` | Function | `handleOddsInput` | Gestionnaire saisie | Validation temps réel |
| `@keypress` | Function | `handleOddsKeypress` | Gestionnaire touches | Contrôle caractères |
| `@blur` | Function | `validateGlobalOdds` | Validation finale | Perte de focus |
| `@focus` | Function | `clearGlobalOddsError` | Nettoyage erreurs | Gain de focus |

### États et Conditions

```javascript
// Calcul de l'état de désactivation
const isGlobalOddsCalculated = computed(() => {
  return formData.value.bet_type === 'combine' && 
         eventCards.value.length > 1 &&
         eventCards.value.every(event => event.odds && event.odds > 0);
});

// Validation de la cote globale
const isGlobalOddsValid = computed(() => {
  const odds = formData.value.global_odds;
  return odds && odds >= 1.01 && odds <= 1000;
});
```

---

## Champs Cote d'Événement (event_odds)

### Template HTML pour Événement Actuel

```vue
<div class="flex flex-col space-y-1">
  <label for="current_event_odds" class="text-xs font-medium text-gray-700">
    Cote de l'événement
  </label>
  <InputText 
    id="current_event_odds" 
    v-model="currentEvent.odds" 
    type="text"
    placeholder="Cote"
    class="w-full text-xs"
    :class="{ 'p-invalid': errors.current_event_odds }"
    @input="(event) => handleEventOddsInput(event, 'current')"
    @keypress="handleEventOddsKeypress"
    @blur="validateCurrentEventOdds"
  />
  <small v-if="errors.current_event_odds" class="text-red-500 text-xs">
    {{ errors.current_event_odds }}
  </small>
</div>
```

### Template HTML pour Événements dans les Cartes

```vue
<div v-for="(event, index) in eventCards" :key="event.id" class="event-card">
  <div class="flex flex-col space-y-1">
    <label :for="`event_odds_${index}`" class="text-xs font-medium text-gray-700">
      Cote
    </label>
    <InputText 
      :id="`event_odds_${index}`"
      v-model="event.odds" 
      type="text"
      placeholder="Cote"
      class="w-full text-xs"
      :class="{ 'p-invalid': errors[`event_odds-${index}`] }"
      @input="(e) => handleEventOddsInput(e, index)"
      @keypress="handleEventOddsKeypress"
      @blur="() => validateEventOdds(index)"
    />
    <small v-if="errors[`event_odds-${index}`]" class="text-red-500 text-xs">
      {{ errors[`event_odds-${index}`] }}
    </small>
  </div>
</div>
```

### Propriétés Dynamiques des Événements

| Propriété | Type | Description | Génération |
|-----------|------|-------------|------------|
| `id` | String | Identifiant unique | `event_odds_${index}` |
| `v-model` | Reactive | Liaison données | `eventCards[index].odds` |
| `error-key` | String | Clé d'erreur | `event_odds-${index}` |
| `handler` | Function | Gestionnaire spécifique | `(e) => handleEventOddsInput(e, index)` |

---

## Fonctions de Gestion Complètes

### 1. handleOddsInput (Cote Globale)

```javascript
/**
 * Gestionnaire de saisie pour la cote globale
 * @param {Event} event - Événement DOM de saisie
 */
function handleOddsInput(event) {
  const inputValue = event.target.value;
  console.log('handleOddsInput - Valeur tapée:', inputValue);
  
  // Normalisation de la valeur (remplacement virgule par point)
  const normalizedValue = inputValue.replace(',', '.');
  console.log('handleOddsInput - Valeur normalisée:', normalizedValue);
  
  // Mise à jour si normalisation nécessaire
  if (inputValue.includes(',')) {
    console.log('handleOddsInput - Virgule détectée, remplacement en cours...');
    
    // Mise à jour de la valeur dans l'input
    event.target.value = normalizedValue;
    
    // Mise à jour du modèle de données
    formData.value.global_odds = normalizedValue;
    
    // Repositionnement du curseur
    const cursorPosition = event.target.selectionStart;
    nextTick(() => {
      event.target.setSelectionRange(cursorPosition, cursorPosition);
    });
    
    console.log('handleOddsInput - Remplacement terminé, nouvelle valeur:', event.target.value);
  }
  
  // Validation en temps réel
  validateGlobalOdds();
  
  // Recalcul du gain potentiel
  // potentialWin se met à jour automatiquement via computed
}
```

### 2. handleEventOddsInput (Cotes d'Événement)

```javascript
/**
 * Gestionnaire de saisie pour les cotes d'événement
 * @param {Event} event - Événement DOM de saisie
 * @param {Number|String} eventIndex - Index de l'événement ou 'current'
 */
function handleEventOddsInput(event, eventIndex) {
  const inputValue = event.target.value;
  console.log(`handleEventOddsInput - Index: ${eventIndex}, Valeur:`, inputValue);
  
  // Normalisation de la valeur
  const normalizedValue = inputValue.replace(',', '.');
  
  // Mise à jour selon le type d'événement
  if (eventIndex === 'current') {
    // Événement en cours de saisie
    if (inputValue.includes(',')) {
      event.target.value = normalizedValue;
      currentEvent.value.odds = normalizedValue;
    }
    validateCurrentEventOdds();
  } else {
    // Événement dans une carte existante
    if (inputValue.includes(',')) {
      event.target.value = normalizedValue;
      eventCards.value[eventIndex].odds = normalizedValue;
    }
    
    // Validation de l'événement spécifique
    validateEventOdds(eventIndex);
    
    // Recalcul automatique de la cote globale
    calculateGlobalOdds();
  }
}
```

### 3. handleOddsKeypress / handleEventOddsKeypress

```javascript
/**
 * Gestionnaire de pression de touches pour les cotes
 * Contrôle les caractères autorisés
 * @param {KeyboardEvent} event - Événement clavier
 */
function handleOddsKeypress(event) {
  const char = event.key;
  const currentValue = event.target.value;
  
  // Caractères autorisés : chiffres, point, virgule
  const allowedChars = /[0-9.,]/;
  
  // Blocage des caractères non autorisés
  if (!allowedChars.test(char) && !['Backspace', 'Delete', 'Tab', 'Enter'].includes(char)) {
    event.preventDefault();
    return;
  }
  
  // Contrôle du point/virgule décimal
  if ((char === '.' || char === ',') && (currentValue.includes('.') || currentValue.includes(','))) {
    event.preventDefault();
    return;
  }
  
  // Limitation de la longueur
  if (currentValue.length >= 10 && !['Backspace', 'Delete'].includes(char)) {
    event.preventDefault();
    return;
  }
}

/**
 * Gestionnaire spécifique pour les cotes d'événement
 * Même logique que handleOddsKeypress
 */
function handleEventOddsKeypress(event) {
  handleOddsKeypress(event);
}
```

---

## Système de Calcul Automatique

### Fonction calculateGlobalOdds

```javascript
/**
 * Calcule automatiquement la cote globale pour les paris combinés
 * Multiplie toutes les cotes d'événements valides
 */
function calculateGlobalOdds() {
  console.log('calculateGlobalOdds - Début du calcul');
  
  // Vérification du type de pari
  if (formData.value.bet_type !== 'combine') {
    console.log('calculateGlobalOdds - Pari simple, pas de calcul automatique');
    return;
  }
  
  // Vérification de la présence d'événements
  if (!eventCards.value || eventCards.value.length === 0) {
    console.log('calculateGlobalOdds - Aucun événement, réinitialisation');
    formData.value.global_odds = null;
    return;
  }
  
  // Extraction et validation des cotes
  const validOdds = [];
  let allOddsValid = true;
  
  eventCards.value.forEach((event, index) => {
    const odds = parseFloat(event.odds);
    console.log(`calculateGlobalOdds - Événement ${index}, cote:`, event.odds, 'parsée:', odds);
    
    if (isNaN(odds) || odds <= 0) {
      allOddsValid = false;
      console.log(`calculateGlobalOdds - Cote invalide pour l'événement ${index}`);
    } else {
      validOdds.push(odds);
    }
  });
  
  // Calcul seulement si toutes les cotes sont valides
  if (allOddsValid && validOdds.length === eventCards.value.length && validOdds.length > 0) {
    const globalOdds = validOdds.reduce((acc, odds) => acc * odds, 1);
    const roundedOdds = Math.round(globalOdds * 100) / 100;
    
    formData.value.global_odds = roundedOdds;
    console.log('calculateGlobalOdds - Calcul automatique:', roundedOdds);
    
    // Nettoyage des erreurs de cote globale
    if (errors.value.global_odds) {
      delete errors.value.global_odds;
    }
  } else {
    console.log('calculateGlobalOdds - Conditions non remplies pour le calcul automatique');
    console.log('- Toutes cotes valides:', allOddsValid);
    console.log('- Nombre cotes valides:', validOdds.length);
    console.log('- Nombre événements:', eventCards.value.length);
  }
}
```

### Conditions de Déclenchement

```javascript
// Déclenchement automatique dans plusieurs contextes :

// 1. Modification d'une cote d'événement
function handleEventOddsInput(event, eventIndex) {
  // ... logique de saisie
  calculateGlobalOdds(); // Recalcul automatique
}

// 2. Ajout d'un nouvel événement
function addEventCard() {
  // ... ajout de l'événement
  calculateGlobalOdds(); // Recalcul automatique
}

// 3. Suppression d'un événement
function removeEventCard(index) {
  // ... suppression de l'événement
  calculateGlobalOdds(); // Recalcul automatique
}

// 4. Changement de type de pari
watch(() => formData.value.bet_type, (newType) => {
  if (newType === 'combine') {
    calculateGlobalOdds();
  }
});
```

---

## Propriété Calculée potentialWin

### Implémentation Complète

```javascript
/**
 * Calcule le gain potentiel en temps réel
 * Formule : mise × cote_globale
 * @returns {Number} Gain potentiel en euros
 */
const potentialWin = computed(() => {
  const stake = parseFloat(formData.value.stake);
  const odds = parseFloat(formData.value.global_odds);
  
  console.log('potentialWin - Calcul:', { stake, odds });
  
  // Validation des valeurs
  if (isNaN(stake) || isNaN(odds) || stake <= 0 || odds <= 0) {
    console.log('potentialWin - Valeurs invalides, retour 0');
    return 0;
  }
  
  const potential = stake * odds;
  console.log('potentialWin - Résultat:', potential);
  
  return potential;
});
```

### Utilisation dans le Template

```vue
<!-- Affichage principal du gain potentiel -->
<div class="bg-green-50 p-3 rounded-lg border border-green-200">
  <div class="flex justify-between items-center">
    <span class="text-sm font-medium text-green-800">Gain potentiel :</span>
    <span class="text-lg font-bold text-green-600">
      {{ potentialWin.toFixed(2) }} €
    </span>
  </div>
</div>

<!-- Affichage secondaire dans le résumé -->
<div class="text-sm text-gray-600">
  Gain potentiel : {{ potentialWin.toFixed(2) }} €
</div>
```

### Réactivité et Performance

```javascript
// La propriété calculée se met à jour automatiquement quand :
// - formData.value.stake change
// - formData.value.global_odds change
// - Aucun watcher manuel nécessaire
// - Calcul optimisé par Vue.js (cache automatique)
```

---

## Système de Validation Avancé

### Validation de la Cote Globale

```javascript
/**
 * Valide la cote globale selon les règles métier
 */
function validateGlobalOdds() {
  const odds = formData.value.global_odds;
  
  // Nettoyage de l'erreur existante
  if (errors.value.global_odds) {
    delete errors.value.global_odds;
  }
  
  // Validation si valeur présente
  if (odds !== null && odds !== undefined && odds !== '') {
    const numericOdds = parseFloat(odds);
    
    // Vérification format numérique
    if (isNaN(numericOdds)) {
      errors.value.global_odds = 'La cote doit être un nombre valide';
      return false;
    }
    
    // Vérification plage de valeurs
    if (numericOdds < 1.01) {
      errors.value.global_odds = 'La cote doit être supérieure à 1.00';
      return false;
    }
    
    if (numericOdds > 1000) {
      errors.value.global_odds = 'La cote ne peut pas dépasser 1000';
      return false;
    }
    
    // Vérification nombre de décimales
    const decimalPlaces = (odds.toString().split('.')[1] || '').length;
    if (decimalPlaces > 2) {
      errors.value.global_odds = 'Maximum 2 décimales autorisées';
      return false;
    }
  }
  
  return true;
}
```

### Validation des Cotes d'Événement

```javascript
/**
 * Valide une cote d'événement spécifique
 * @param {Number} index - Index de l'événement à valider
 */
function validateEventOdds(index) {
  const event = eventCards.value[index];
  const errorKey = `event_odds-${index}`;
  
  // Nettoyage de l'erreur existante
  if (errors.value[errorKey]) {
    delete errors.value[errorKey];
  }
  
  // Validation si événement et cote présents
  if (event && event.odds !== null && event.odds !== undefined && event.odds !== '') {
    const numericOdds = parseFloat(event.odds);
    
    // Vérification format numérique
    if (isNaN(numericOdds)) {
      errors.value[errorKey] = 'Cote invalide';
      return false;
    }
    
    // Vérification plage de valeurs
    if (numericOdds < 1.01) {
      errors.value[errorKey] = 'Cote trop faible (min 1.01)';
      return false;
    }
    
    if (numericOdds > 1000) {
      errors.value[errorKey] = 'Cote trop élevée (max 1000)';
      return false;
    }
  }
  
  return true;
}

/**
 * Valide la cote de l'événement en cours
 */
function validateCurrentEventOdds() {
  const odds = currentEvent.value.odds;
  
  // Nettoyage de l'erreur existante
  if (errors.value.current_event_odds) {
    delete errors.value.current_event_odds;
  }
  
  // Validation si valeur présente
  if (odds !== null && odds !== undefined && odds !== '') {
    const numericOdds = parseFloat(odds);
    
    if (isNaN(numericOdds) || numericOdds < 1.01 || numericOdds > 1000) {
      errors.value.current_event_odds = 'Cote invalide (entre 1.01 et 1000)';
      return false;
    }
  }
  
  return true;
}
```

### Validation Globale du Formulaire

```javascript
/**
 * Propriété calculée pour la validation globale
 */
const isFormValid = computed(() => {
  // Vérification absence d'erreurs
  const hasErrors = Object.keys(errors.value).length > 0;
  
  // Vérification champs obligatoires
  const hasRequiredFields = formData.value.global_odds && 
                           formData.value.stake &&
                           formData.value.bet_type;
  
  // Vérification événements pour paris combinés
  const hasValidEvents = formData.value.bet_type === 'simple' || 
                        (eventCards.value.length > 1 && 
                         eventCards.value.every(event => event.odds));
  
  return !hasErrors && hasRequiredFields && hasValidEvents;
});
```

---

## Gestion des Événements et Interactions

### Événements DOM Gérés

```javascript
// Événements sur les champs de cote
const oddsFieldEvents = {
  // Saisie en temps réel
  input: {
    handler: 'handleOddsInput | handleEventOddsInput',
    purpose: 'Normalisation et validation temps réel',
    frequency: 'Chaque caractère tapé'
  },
  
  // Contrôle des touches
  keypress: {
    handler: 'handleOddsKeypress | handleEventOddsKeypress',
    purpose: 'Filtrage des caractères autorisés',
    frequency: 'Chaque touche pressée'
  },
  
  // Validation finale
  blur: {
    handler: 'validateGlobalOdds | validateEventOdds',
    purpose: 'Validation complète à la perte de focus',
    frequency: 'Perte de focus'
  },
  
  // Nettoyage des erreurs
  focus: {
    handler: 'clearGlobalOddsError | clearEventOddsError',
    purpose: 'Amélioration UX - nettoyage visuel',
    frequency: 'Gain de focus'
  }
};
```

### Interactions avec Autres Composants

```javascript
// Interactions avec SportField, LeagueField, etc.
watch([
  () => currentEvent.value.sport_id,
  () => currentEvent.value.league_id
], () => {
  // Réinitialisation de la cote si sport/ligue change
  if (currentEvent.value.odds) {
    currentEvent.value.odds = null;
    validateCurrentEventOdds();
  }
});

// Interaction avec le sélecteur de type de pari
watch(() => formData.value.bet_type, (newType, oldType) => {
  if (newType === 'simple' && oldType === 'combine') {
    // Nettoyage des événements multiples
    eventCards.value = [];
    calculateGlobalOdds();
  } else if (newType === 'combine' && oldType === 'simple') {
    // Préparation pour paris combinés
    if (formData.value.global_odds) {
      // Sauvegarde temporaire de la cote globale
      const tempOdds = formData.value.global_odds;
      formData.value.global_odds = null;
    }
  }
});
```

---

## Cycle de Vie et Watchers

### Watchers Réactifs

```javascript
// Surveillance des changements de type de pari
watch(() => formData.value.bet_type, (newType) => {
  console.log('Changement de type de pari:', newType);
  
  if (newType === 'combine') {
    // Activation du mode combiné
    calculateGlobalOdds();
  } else {
    // Mode simple - réinitialisation
    eventCards.value = [];
    errors.value = {};
  }
}, { immediate: true });

// Surveillance des événements pour recalcul automatique
watch(() => eventCards.value.length, (newLength, oldLength) => {
  console.log(`Nombre d'événements: ${oldLength} → ${newLength}`);
  
  // Recalcul si changement d'événements
  if (formData.value.bet_type === 'combine') {
    calculateGlobalOdds();
  }
  
  // Nettoyage des erreurs obsolètes
  reindexEventErrors();
});

// Surveillance profonde des cotes d'événements
watch(() => eventCards.value.map(event => event.odds), (newOdds, oldOdds) => {
  console.log('Cotes d\'événements modifiées:', { oldOdds, newOdds });
  
  if (formData.value.bet_type === 'combine') {
    calculateGlobalOdds();
  }
}, { deep: true });
```

### Fonctions de Cycle de Vie

```javascript
/**
 * Initialisation au montage du composant
 */
onMounted(() => {
  console.log('AddBetForm monté - Initialisation des champs de cote');
  
  // Initialisation des valeurs par défaut
  if (!formData.value.global_odds) {
    formData.value.global_odds = null;
  }
  
  // Configuration des validateurs
  setupValidation();
  
  // Restauration des données si nécessaire
  restoreFormData();
});

/**
 * Nettoyage avant démontage
 */
onBeforeUnmount(() => {
  console.log('AddBetForm - Nettoyage avant démontage');
  
  // Sauvegarde des données temporaires
  saveTemporaryData();
  
  // Nettoyage des timers/intervals
  clearValidationTimers();
});
```

---

## Dépendances et Intégrations

### Dépendances Vue.js

```javascript
import { 
  ref,           // Variables réactives
  computed,      // Propriétés calculées
  watch,         // Surveillance des changements
  nextTick,      // Cycle de rendu suivant
  onMounted,     // Hook de montage
  onBeforeUnmount // Hook de démontage
} from 'vue';
```

### Dépendances PrimeVue

```javascript
import InputText from 'primevue/inputtext';  // Composant de saisie
import Button from 'primevue/button';        // Boutons d'action
import Message from 'primevue/message';      // Messages d'erreur
```

### Intégrations avec Composables

```javascript
// Composable de validation (hypothétique)
import { useValidation } from '@/composables/useValidation';

// Composable de formatage des nombres
import { useNumberFormat } from '@/composables/useNumberFormat';

// Composable de gestion des erreurs
import { useErrorHandling } from '@/composables/useErrorHandling';

// Utilisation dans le composant
const { validateNumber, validateRange } = useValidation();
const { formatDecimal, parseDecimal } = useNumberFormat();
const { setError, clearError, hasError } = useErrorHandling();
```

---

## Règles de Validation et Contraintes

### Contraintes Métier

```javascript
const ODDS_CONSTRAINTS = {
  // Valeurs limites
  MIN_ODDS: 1.01,           // Cote minimale
  MAX_ODDS: 1000,           // Cote maximale
  MAX_DECIMALS: 2,          // Nombre de décimales max
  
  // Contraintes de saisie
  MAX_LENGTH: 10,           // Longueur maximale
  ALLOWED_CHARS: /[0-9.,]/, // Caractères autorisés
  
  // Contraintes de calcul
  MAX_EVENTS: 20,           // Nombre max d'événements combinés
  MIN_EVENTS_COMBINE: 2,    // Minimum pour un combiné
  
  // Contraintes d'affichage
  DISPLAY_DECIMALS: 2,      // Décimales pour affichage
  CURRENCY_SYMBOL: '€'      // Symbole monétaire
};
```

### Règles de Validation Détaillées

```javascript
/**
 * Ensemble complet des règles de validation
 */
const validationRules = {
  // Validation format
  isNumeric: (value) => !isNaN(parseFloat(value)) && isFinite(value),
  
  // Validation plage
  isInRange: (value, min = ODDS_CONSTRAINTS.MIN_ODDS, max = ODDS_CONSTRAINTS.MAX_ODDS) => {
    const num = parseFloat(value);
    return num >= min && num <= max;
  },
  
  // Validation décimales
  hasValidDecimals: (value) => {
    const decimals = (value.toString().split('.')[1] || '').length;
    return decimals <= ODDS_CONSTRAINTS.MAX_DECIMALS;
  },
  
  // Validation longueur
  hasValidLength: (value) => {
    return value.toString().length <= ODDS_CONSTRAINTS.MAX_LENGTH;
  },
  
  // Validation caractères
  hasValidChars: (value) => {
    return /^[0-9.,]+$/.test(value.toString());
  },
  
  // Validation complète
  isValidOdds: (value) => {
    return validationRules.isNumeric(value) &&
           validationRules.isInRange(value) &&
           validationRules.hasValidDecimals(value) &&
           validationRules.hasValidLength(value) &&
           validationRules.hasValidChars(value);
  }
};
```

---

## Optimisations et Performance

### Optimisations de Calcul

```javascript
// Debouncing pour les calculs coûteux
import { debounce } from 'lodash-es';

const debouncedCalculateGlobalOdds = debounce(() => {
  calculateGlobalOdds();
}, 300); // 300ms de délai

// Utilisation dans les handlers
function handleEventOddsInput(event, eventIndex) {
  // ... logique de saisie
  
  // Calcul différé pour éviter les calculs excessifs
  debouncedCalculateGlobalOdds();
}
```

### Optimisations de Rendu

```javascript
// Mémorisation des composants coûteux
const MemoizedEventCard = defineAsyncComponent(() => 
  import('./EventCard.vue')
);

// Utilisation de v-memo pour les listes
// Template avec optimisation
<template v-for="(event, index) in eventCards" 
          :key="event.id" 
          v-memo="[event.odds, errors[`event_odds-${index}`]]">
  <!-- Contenu de la carte événement -->
</template>
```

### Optimisations Mémoire

```javascript
/**
 * Nettoyage des erreurs obsolètes
 */
function reindexEventErrors() {
  const currentErrors = { ...errors.value };
  
  // Suppression des erreurs d'événements supprimés
  Object.keys(currentErrors).forEach(key => {
    if (key.startsWith('event_odds-')) {
      const index = parseInt(key.split('-')[1]);
      if (index >= eventCards.value.length) {
        delete errors.value[key];
      }
    }
  });
}

/**
 * Nettoyage périodique des données temporaires
 */
function cleanupTemporaryData() {
  // Suppression des propriétés non utilisées
  const cleanedEvents = eventCards.value.map(event => ({
    id: event.id,
    odds: event.odds,
    sport_id: event.sport_id,
    league_id: event.league_id,
    team_home: event.team_home,
    team_away: event.team_away,
    bet_choice: event.bet_choice,
    event_date: event.event_date
    // Suppression des propriétés temporaires
  }));
  
  eventCards.value = cleanedEvents;
}
```

---

## Intégration API et Persistance

### Préparation des Données pour l'API

```javascript
/**
 * Prépare les données de cote pour l'envoi API
 */
function prepareOddsDataForAPI() {
  const oddsData = {
    global_odds: parseFloat(formData.value.global_odds),
    bet_type: formData.value.bet_type,
    stake: parseFloat(formData.value.stake),
    events: []
  };
  
  // Ajout des événements pour paris combinés
  if (formData.value.bet_type === 'combine') {
    oddsData.events = eventCards.value.map(event => ({
      odds: parseFloat(event.odds),
      sport_id: event.sport_id,
      league_id: event.league_id,
      team_home: event.team_home,
      team_away: event.team_away,
      bet_choice: event.bet_choice,
      event_date: event.event_date
    }));
  }
  
  return oddsData;
}
```

### Sauvegarde Automatique

```javascript
// Sauvegarde automatique des données
const autoSave = debounce(() => {
  const dataToSave = {
    formData: formData.value,
    eventCards: eventCards.value,
    timestamp: new Date().toISOString()
  };
  
  localStorage.setItem('bet_form_draft', JSON.stringify(dataToSave));
  console.log('Données sauvegardées automatiquement');
}, 2000); // Sauvegarde toutes les 2 secondes

// Déclenchement de la sauvegarde
watch([formData, eventCards], () => {
  autoSave();
}, { deep: true });
```

### Restauration des Données

```javascript
/**
 * Restaure les données sauvegardées
 */
function restoreFormData() {
  try {
    const savedData = localStorage.getItem('bet_form_draft');
    if (savedData) {
      const parsedData = JSON.parse(savedData);
      
      // Vérification de la fraîcheur des données (24h max)
      const savedTime = new Date(parsedData.timestamp);
      const now = new Date();
      const hoursDiff = (now - savedTime) / (1000 * 60 * 60);
      
      if (hoursDiff < 24) {
        formData.value = { ...formData.value, ...parsedData.formData };
        eventCards.value = parsedData.eventCards || [];
        
        console.log('Données restaurées depuis la sauvegarde');
        
        // Recalcul après restauration
        if (formData.value.bet_type === 'combine') {
          calculateGlobalOdds();
        }
      } else {
        // Suppression des données expirées
        localStorage.removeItem('bet_form_draft');
      }
    }
  } catch (error) {
    console.error('Erreur lors de la restauration des données:', error);
    localStorage.removeItem('bet_form_draft');
  }
}
```

---

## Gestion d'Erreurs et Debugging

### Système de Logging

```javascript
/**
 * Logger spécialisé pour les champs de cote
 */
const oddsLogger = {
  info: (message, data = {}) => {
    console.log(`[ODDS] ${message}`, data);
  },
  
  warn: (message, data = {}) => {
    console.warn(`[ODDS] ${message}`, data);
  },
  
  error: (message, error = {}) => {
    console.error(`[ODDS] ${message}`, error);
  },
  
  debug: (message, data = {}) => {
    if (process.env.NODE_ENV === 'development') {
      console.debug(`[ODDS] ${message}`, data);
    }
  }
};
```

### Gestion des Erreurs de Calcul

```javascript
/**
 * Wrapper sécurisé pour les calculs de cote
 */
function safeCalculateGlobalOdds() {
  try {
    calculateGlobalOdds();
  } catch (error) {
    oddsLogger.error('Erreur lors du calcul de la cote globale', error);
    
    // Fallback : réinitialisation
    formData.value.global_odds = null;
    
    // Notification utilisateur
    errors.value.global_odds = 'Erreur de calcul, veuillez réessayer';
  }
}
```

### Validation des États

```javascript
/**
 * Valide la cohérence des états internes
 */
function validateInternalState() {
  const issues = [];
  
  // Vérification cohérence type de pari / événements
  if (formData.value.bet_type === 'combine' && eventCards.value.length < 2) {
    issues.push('Pari combiné avec moins de 2 événements');
  }
  
  // Vérification cohérence cotes
  if (formData.value.bet_type === 'simple' && eventCards.value.length > 0) {
    issues.push('Pari simple avec des événements multiples');
  }
  
  // Vérification erreurs orphelines
  Object.keys(errors.value).forEach(key => {
    if (key.startsWith('event_odds-')) {
      const index = parseInt(key.split('-')[1]);
      if (index >= eventCards.value.length) {
        issues.push(`Erreur orpheline: ${key}`);
      }
    }
  });
  
  if (issues.length > 0) {
    oddsLogger.warn('Incohérences détectées', issues);
  }
  
  return issues.length === 0;
}
```

---

## Résumé Architectural

### Points Clés de l'Implémentation

1. **Réactivité Complète** : Utilisation optimale du système réactif de Vue.js
2. **Validation Multi-niveaux** : Validation temps réel, à la perte de focus et globale
3. **Calculs Automatiques** : Synchronisation automatique des cotes combinées
4. **Gestion d'Erreurs Robuste** : Système complet de gestion et affichage des erreurs
5. **Performance Optimisée** : Debouncing, mémorisation et nettoyage automatique
6. **Persistance Intelligente** : Sauvegarde/restauration automatique avec expiration
7. **Debugging Avancé** : Logging détaillé et validation des états internes

### Architecture Extensible

Le système est conçu pour faciliter l'ajout de nouvelles fonctionnalités :

- **Nouveaux Types de Cotes** : Système modulaire pour ajouter des types spécialisés
- **Calculs Avancés** : Framework extensible pour des formules complexes
- **Intégrations Externes** : API prête pour des services de cotes en temps réel
- **Validation Personnalisée** : Système de règles configurable
- **Optimisations Futures** : Architecture préparée pour des améliorations de performance

Cette documentation constitue la base complète pour l'extraction des champs de cote en composants dédiés, garantissant une transition sans perte de fonctionnalité.