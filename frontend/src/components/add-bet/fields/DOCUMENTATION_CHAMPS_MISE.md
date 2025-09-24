# Documentation Ultra Détaillée des Champs de Mise - Architecture Modulaire

## Table des Matières

1. [Vue d'ensemble Architecturale](#vue-densemble-architecturale)
2. [Structure des Données](#structure-des-données)
3. [Champ Mise Principal](#champ-mise-principal)
4. [Sélecteur Type de Mise](#sélecteur-type-de-mise)
5. [Système de Capital et Pourcentage](#système-de-capital-et-pourcentage)
6. [Affichage du Gain Potentiel](#affichage-du-gain-potentiel)
7. [Fonctions de Gestion](#fonctions-de-gestion)
8. [Validation et Contraintes](#validation-et-contraintes)
9. [Watchers et Réactivité](#watchers-et-réactivité)
10. [Intégration avec l'API](#intégration-avec-lapi)
11. [Gestion des Erreurs](#gestion-des-erreurs)
12. [Optimisations et Performance](#optimisations-et-performance)
13. [Tests et Debugging](#tests-et-debugging)
14. [Migration vers Composant Dédié](#migration-vers-composant-dédié)

---

## Vue d'ensemble Architecturale

### Fonctionnalités Principales

Le système de mise dans AddBetForm.vue comprend :

- **Champ de saisie de mise** : Input principal pour la valeur
- **Sélecteur de type** : Choix entre devise (€) et pourcentage (%)
- **Calcul automatique** : Conversion pourcentage → montant en euros
- **Récupération du capital** : API pour obtenir le capital actuel
- **Affichage du gain potentiel** : Calcul temps réel basé sur la cote
- **Validation avancée** : Contrôles de format et de valeurs
- **Gestion des erreurs** : Messages contextuels

### Architecture Technique

```javascript
// Structure des données de mise
formData.value.stake          // Valeur saisie (Number|null)
betTypeValue.value           // Type: 'currency' | 'percentage'
currentCapital.value         // Capital actuel de l'utilisateur
calculatedStake.value        // Mise calculée en euros (mode %)
capitalLoading.value         // État de chargement du capital
```

### Flux de Données

```
Saisie → Normalisation → Validation → Calcul → Affichage → API
```

---

## Structure des Données

### Variables Réactives Principales

```javascript
// Données du formulaire principal
const formData = ref({
  stake: null,                 // Mise saisie (Number|null)
  global_odds: null,           // Cote globale pour calcul gain
  // ... autres propriétés
});

// Variables pour le type de mise
const betTypeValue = ref('currency');
const betTypeOptions = ref([
  { symbol: '€', value: 'currency' },
  { symbol: '%', value: 'percentage' }
]);

// Variables pour le capital actuel
const currentCapital = ref(0);        // Capital utilisateur en euros
const calculatedStake = ref(0);       // Mise calculée (mode %)
const capitalLoading = ref(false);    // État de chargement

// Gestion des erreurs
const errors = ref({});              // Objet contenant les erreurs
```

### Types de Données

| Variable | Type | Valeurs Possibles | Description |
|----------|------|-------------------|-------------|
| `formData.stake` | Number\|null | 0+ | Mise saisie par l'utilisateur |
| `betTypeValue` | String | 'currency'\|'percentage' | Type de mise sélectionné |
| `currentCapital` | Number | 0+ | Capital actuel de l'utilisateur |
| `calculatedStake` | Number | 0+ | Mise calculée en euros (mode %) |
| `capitalLoading` | Boolean | true\|false | État de chargement du capital |

---

## Champ Mise Principal

### Template HTML Complet

```vue
<!-- Mise -->
<div class="flex flex-col justify-center min-w-0 w-full">
  <div class="w-full">
    <InputText 
      id="stake" 
      v-model="formData.stake" 
      type="text"
      :placeholder="betTypeValue === 'currency' ? 'Mise en €' : betTypeValue === 'percentage' ? 'Mise en %' : 'Mise'"
      class="w-full text-xs"
      :class="{ 'p-invalid': errors.stake }"
      @input="handleStakeInput"
      @keypress="handleStakeKeypress"
    />
  </div>
  <small v-if="errors.stake" class="text-red-500 text-xs truncate">{{ errors.stake }}</small>
</div>
```

### Propriétés et Attributs Détaillés

| Attribut | Type | Valeur | Description | Comportement |
|----------|------|--------|-------------|--------------|
| `id` | String | `"stake"` | Identifiant unique | Liaison label/input |
| `v-model` | Reactive | `formData.stake` | Liaison bidirectionnelle | Mise à jour automatique |
| `type` | String | `"text"` | Type d'input HTML | Permet saisie décimale |
| `:placeholder` | Computed | Dynamique selon type | Texte d'aide contextuel | Guide utilisateur |
| `class` | String | `"w-full text-xs"` | Classes CSS de base | Style par défaut |
| `:class` | Object | `{ 'p-invalid': errors.stake }` | Classes conditionnelles | Indication erreur |
| `@input` | Function | `handleStakeInput` | Gestionnaire saisie | Normalisation temps réel |
| `@keypress` | Function | `handleStakeKeypress` | Gestionnaire touches | Contrôle caractères |

### Placeholder Dynamique

```javascript
// Logique du placeholder
const placeholder = computed(() => {
  switch (betTypeValue.value) {
    case 'currency':
      return 'Mise en €';
    case 'percentage':
      return 'Mise en %';
    default:
      return 'Mise';
  }
});
```

---

## Sélecteur Type de Mise

### Template HTML

```vue
<!-- Type de mise -->
<div class="flex flex-col justify-center min-w-0 w-full">
  <div class="w-full flex items-center">
    <SelectButton 
      v-model="betTypeValue" 
      :options="betTypeOptions" 
      optionLabel="symbol" 
      optionValue="value"
      class="h-8 text-xs w-full"
    />
  </div>
</div>
```

### Configuration des Options

```javascript
const betTypeOptions = ref([
  { 
    symbol: '€',           // Symbole affiché
    value: 'currency'      // Valeur interne
  },
  { 
    symbol: '%', 
    value: 'percentage' 
  }
]);
```

### Propriétés du SelectButton

| Propriété | Type | Valeur | Description |
|-----------|------|--------|-------------|
| `v-model` | Reactive | `betTypeValue` | Valeur sélectionnée |
| `:options` | Array | `betTypeOptions` | Options disponibles |
| `optionLabel` | String | `"symbol"` | Propriété pour l'affichage |
| `optionValue` | String | `"value"` | Propriété pour la valeur |
| `class` | String | `"h-8 text-xs w-full"` | Classes CSS |

---

## Système de Capital et Pourcentage

### Récupération du Capital

```javascript
/**
 * Récupérer le capital actuel de l'utilisateur
 */
async function fetchCurrentCapital() {
  try {
    capitalLoading.value = true;
    const response = await BetService.getCapitalEvolution();
    
    if (response.success && response.data) {
      currentCapital.value = response.current_capital || response.initial_capital || 0;
    }
  } catch (error) {
    console.error('Erreur lors de la récupération du capital actuel:', error);
    currentCapital.value = 0;
  } finally {
    capitalLoading.value = false;
  }
}
```

### Calcul de la Mise en Pourcentage

```javascript
/**
 * Calculer la mise en pourcentage du capital
 */
function calculatePercentageStake() {
  if (betTypeValue.value === 'percentage' && formData.value.stake && currentCapital.value > 0) {
    const percentage = parseFloat(formData.value.stake);
    if (!isNaN(percentage) && percentage > 0) {
      calculatedStake.value = (currentCapital.value * percentage) / 100;
      return;
    }
  }
  calculatedStake.value = 0;
}
```

### Formule de Calcul

```
Mise en euros = (Capital actuel × Pourcentage saisi) ÷ 100

Exemple :
- Capital actuel : 1000 €
- Pourcentage saisi : 5%
- Mise calculée : (1000 × 5) ÷ 100 = 50 €
```

---

## Affichage du Gain Potentiel

### Mode Pourcentage - Affichage Détaillé

```vue
<!-- Section détaillée du gain potentiel (mode pourcentage uniquement) -->
<div v-if="betTypeValue === 'percentage'" class="flex flex-col gap-2 mb-4 mt-4">
  <div class="p-4 bg-gray-50 rounded border">
    <h4 class="text-sm font-semibold text-gray-800 mb-3">Détails du gain potentiel</h4>
    
    <!-- Capital actuel -->
    <div class="flex justify-between items-center mb-2">
      <span class="text-sm text-gray-600">Capital actuel :</span>
      <span class="text-sm font-medium">
        <i v-if="capitalLoading" class="pi pi-spin pi-spinner text-xs"></i>
        <span v-else>{{ currentCapital.toFixed(2) }} €</span>
      </span>
    </div>
    
    <!-- Mise calculée -->
    <div v-if="calculatedStake > 0" class="flex justify-between items-center mb-2">
      <span class="text-sm text-gray-600">Mise calculée ({{ formData.stake }}%) :</span>
      <span class="text-sm font-medium text-blue-600">{{ calculatedStake.toFixed(2) }} €</span>
    </div>
    
    <!-- Cote -->
    <div v-if="formData.global_odds" class="flex justify-between items-center mb-2">
      <span class="text-sm text-gray-600">Cote :</span>
      <span class="text-sm font-medium">{{ parseFloat(formData.global_odds).toFixed(2) }}</span>
    </div>
    
    <!-- Gain potentiel -->
    <div class="flex justify-between items-center pt-2 border-t border-gray-200">
      <span class="text-sm font-semibold text-gray-800">Gain potentiel :</span>
      <span class="text-lg font-bold text-green-600">{{ potentialWin.toFixed(2) }} €</span>
    </div>
  </div>
</div>
```

### Mode Devise - Affichage Simple

```vue
<!-- Gain potentiel simple (mode devise uniquement) -->
<div v-if="betTypeValue === 'currency'" class="flex flex-col gap-2 mt-4 mb-4">
  <div class="p-3 bg-gray-50 rounded border text-lg font-semibold text-green-600 text-center">
    Gain potentiel : {{ potentialWin.toFixed(2) }} €
  </div>
</div>
```

### Propriété Calculée du Gain Potentiel

```javascript
/**
 * Calculer le gain potentiel basé sur la mise et la cote globale
 * @returns {Number} Gain potentiel en euros
 */
const potentialWin = computed(() => {
  let stake = 0;
  
  // Utiliser la mise calculée en mode pourcentage, sinon la mise directe
  if (betTypeValue.value === 'percentage' && calculatedStake.value > 0) {
    stake = calculatedStake.value;
  } else {
    stake = parseFloat(formData.value.stake);
  }
  
  const odds = parseFloat(formData.value.global_odds);
  
  // Validation des valeurs
  if (isNaN(stake) || isNaN(odds) || stake <= 0 || odds <= 0) {
    return 0;
  }
  
  return stake * odds;
});
```

### Logique d'Affichage Conditionnel

| Condition | Affichage | Description |
|-----------|-----------|-------------|
| `betTypeValue === 'percentage'` | Section détaillée | Capital, mise calculée, cote, gain |
| `betTypeValue === 'currency'` | Section simple | Gain potentiel uniquement |
| `capitalLoading === true` | Spinner | Chargement du capital |
| `calculatedStake > 0` | Mise calculée | Affichage du montant en euros |

---

## Fonctions de Gestion

### Fonction handleStakeInput

```javascript
/**
 * Gérer la saisie de la mise pour accepter les virgules et les points comme séparateurs décimaux
 * @param {Event} event - Événement d'input
 */
function handleStakeInput(event) {
  let inputValue = event.target.value;
  console.log('handleStakeInput - Valeur tapée:', inputValue);
  
  // Remplacer immédiatement toutes les virgules par des points
  const normalizedValue = inputValue.replace(/,/g, '.');
  console.log('handleStakeInput - Valeur normalisée:', normalizedValue);
  
  // Si une virgule a été détectée, forcer le remplacement immédiat
  if (inputValue !== normalizedValue) {
    console.log('handleStakeInput - Virgule détectée, remplacement en cours...');
    // Sauvegarder la position du curseur
    const cursorPosition = event.target.selectionStart;
    
    // Mettre à jour immédiatement la valeur de l'input
    event.target.value = normalizedValue;
    
    // Restaurer la position du curseur
    event.target.setSelectionRange(cursorPosition, cursorPosition);
    
    // Mettre à jour le v-model
    formData.value.stake = normalizedValue;
    console.log('handleStakeInput - Remplacement terminé, nouvelle valeur:', event.target.value);
    return;
  }
  
  // Vérifier que la valeur est un nombre réel valide
  if (normalizedValue === '' || normalizedValue === '.') {
    formData.value.stake = null;
    return;
  }
  
  // Validation du format nombre réel (la mise peut être 0)
  const numericValue = parseFloat(normalizedValue);
  if (!isNaN(numericValue) && isFinite(numericValue) && numericValue >= 0) {
    formData.value.stake = numericValue;
  } else {
    // Si la valeur n'est pas valide, on garde la dernière valeur valide
    console.warn('Valeur de mise invalide:', normalizedValue);
  }
}
```

#### Fonctionnalités de handleStakeInput

1. **Normalisation automatique** : Remplacement virgule → point
2. **Gestion du curseur** : Préservation de la position
3. **Validation temps réel** : Contrôle du format numérique
4. **Gestion des cas limites** : Valeurs vides, points isolés
5. **Logging détaillé** : Traçabilité des transformations

### Fonction handleStakeKeypress

```javascript
/**
 * Gérer les touches pressées pour la mise (permettre point et virgule)
 * @param {KeyboardEvent} event - Événement de frappe
 */
function handleStakeKeypress(event) {
  const char = String.fromCharCode(event.which);
  const currentValue = event.target.value;
  
  // Permettre les chiffres, le point, la virgule et les touches de contrôle
  if (!/[0-9.,]/.test(char) && event.which !== 8 && event.which !== 46 && event.which !== 37 && event.which !== 39) {
    event.preventDefault();
    return;
  }
  
  // Empêcher plusieurs séparateurs décimaux (point ou virgule)
  if ((char === '.' || char === ',') && (currentValue.includes('.') || currentValue.includes(','))) {
    event.preventDefault();
    return;
  }
  
  // Empêcher le point/virgule en première position
  if ((char === '.' || char === ',') && currentValue === '') {
    event.preventDefault();
    return;
  }
}
```

#### Règles de Validation Keypress

| Caractère | Autorisé | Condition | Action |
|-----------|----------|-----------|--------|
| `0-9` | ✅ | Toujours | Saisie normale |
| `.` `,` | ✅ | Si pas déjà présent | Séparateur décimal |
| `.` `,` | ❌ | En première position | `preventDefault()` |
| `.` `,` | ❌ | Si déjà présent | `preventDefault()` |
| Autres | ❌ | Sauf touches contrôle | `preventDefault()` |

#### Touches de Contrôle Autorisées

- `8` : Backspace
- `46` : Delete
- `37` : Flèche gauche
- `39` : Flèche droite

---

## Validation et Contraintes

### Validation dans validateForm()

```javascript
// Extrait de la fonction validateForm()
if (!formData.value.stake || formData.value.stake <= 0) {
  errors.value.stake = 'La mise doit être supérieure à 0';
}
```

### Règles de Validation

| Règle | Condition | Message d'Erreur |
|-------|-----------|------------------|
| **Obligatoire** | `!formData.value.stake` | "La mise doit être supérieure à 0" |
| **Valeur positive** | `formData.value.stake <= 0` | "La mise doit être supérieure à 0" |
| **Format numérique** | `isNaN(parseFloat(value))` | Gestion dans `handleStakeInput` |
| **Valeur finie** | `!isFinite(value)` | Gestion dans `handleStakeInput` |

### Contraintes Techniques

```javascript
// Contraintes de saisie
const STAKE_CONSTRAINTS = {
  MIN_VALUE: 0.01,           // Mise minimale
  MAX_VALUE: 999999.99,      // Mise maximale
  DECIMAL_PLACES: 2,         // Précision décimale
  ALLOWED_CHARS: /[0-9.,]/,  // Caractères autorisés
  SEPARATORS: ['.', ',']     // Séparateurs décimaux
};
```

### Validation Contextuelle

```javascript
// Validation selon le type de mise
function validateStakeByType() {
  if (betTypeValue.value === 'percentage') {
    // Validation pourcentage (0-100%)
    if (formData.value.stake > 100) {
      errors.value.stake = 'Le pourcentage ne peut pas dépasser 100%';
    }
  } else {
    // Validation devise (montant en euros)
    if (formData.value.stake > 999999.99) {
      errors.value.stake = 'La mise ne peut pas dépasser 999 999,99 €';
    }
  }
}
```

---

## Watchers et Réactivité

### Watcher du Type de Mise

```javascript
// Surveiller le changement de type de mise pour récupérer le capital
watch(betTypeValue, async (newValue) => {
  if (newValue === 'percentage') {
    await fetchCurrentCapital();
  }
  calculatePercentageStake();
});
```

#### Comportement du Watcher

1. **Déclenchement** : Changement de `betTypeValue`
2. **Condition** : Si nouveau type = 'percentage'
3. **Action** : Récupération du capital via API
4. **Finalisation** : Recalcul de la mise

### Watcher de la Mise

```javascript
// Surveiller les changements de la mise pour recalculer en mode pourcentage
watch(() => formData.value.stake, () => {
  calculatePercentageStake();
});
```

#### Déclencheurs de Recalcul

- Modification de `formData.value.stake`
- Changement de `betTypeValue`
- Mise à jour de `currentCapital`

### Propriété Calculée isFormValid

```javascript
const isFormValid = computed(() => {
  // Seuls les champs essentiels sont obligatoires
  return formData.value.bet_date &&
         formData.value.global_odds &&
         formData.value.stake;
});
```

---

## Intégration avec l'API

### Envoi des Données

```javascript
// Extrait de submitForm()
const betData = {
  stake: parseFloat(formData.value.stake),
  stake_type: betTypeValue.value, // Type de mise: 'currency' ou 'percentage'
  // ... autres données
};

const response = await BetService.createBet(betData);
```

### Structure des Données API

```javascript
// Données envoyées à l'API
{
  stake: Number,              // Valeur de la mise
  stake_type: String,         // 'currency' | 'percentage'
  global_odds: Number,        // Cote pour calcul gain
  // ... autres propriétés du pari
}
```

### Service de Capital

```javascript
// Appel API pour récupérer le capital
const response = await BetService.getCapitalEvolution();

// Structure de réponse attendue
{
  success: Boolean,
  data: {
    current_capital: Number,    // Capital actuel
    initial_capital: Number,    // Capital initial
    // ... autres données
  }
}
```

---

## Gestion des Erreurs

### Types d'Erreurs

| Type | Source | Gestion |
|------|--------|---------|
| **Validation** | Formulaire | `errors.value.stake` |
| **API** | Réseau | Toast notification |
| **Format** | Saisie | `handleStakeInput` |
| **Capital** | Service | Valeur par défaut (0) |

### Affichage des Erreurs

```vue
<small v-if="errors.stake" class="text-red-500 text-xs truncate">
  {{ errors.stake }}
</small>
```

### Gestion des Erreurs API

```javascript
try {
  const response = await BetService.getCapitalEvolution();
  // Traitement réussi
} catch (error) {
  console.error('Erreur lors de la récupération du capital actuel:', error);
  currentCapital.value = 0;  // Valeur par défaut
  // Pas de toast pour cette erreur (silencieuse)
}
```

---

## Optimisations et Performance

### Optimisations Implémentées

1. **Calcul différé** : Watchers pour éviter les calculs inutiles
2. **Validation locale** : Contrôle avant envoi API
3. **Cache du capital** : Récupération uniquement si nécessaire
4. **Normalisation efficace** : Remplacement direct dans l'input

### Gestion Mémoire

```javascript
// Nettoyage automatique des erreurs
function cleanupStakeErrors() {
  if (formData.value.stake && formData.value.stake > 0) {
    delete errors.value.stake;
  }
}
```

### Performance de Rendu

- **Propriétés calculées** : Cache automatique des calculs
- **Watchers ciblés** : Surveillance spécifique des changements
- **Affichage conditionnel** : Rendu optimisé selon le type

---

## Tests et Debugging

### Logs de Debugging

```javascript
// Logs dans handleStakeInput
console.log('handleStakeInput - Valeur tapée:', inputValue);
console.log('handleStakeInput - Valeur normalisée:', normalizedValue);
console.log('handleStakeInput - Virgule détectée, remplacement en cours...');
console.log('handleStakeInput - Remplacement terminé, nouvelle valeur:', event.target.value);
console.warn('Valeur de mise invalide:', normalizedValue);
```

### Tests Unitaires Recommandés

```javascript
describe('StakeField', () => {
  test('normalise les virgules en points', () => {
    // Test de normalisation
  });
  
  test('calcule correctement la mise en pourcentage', () => {
    // Test de calcul pourcentage
  });
  
  test('valide les contraintes de saisie', () => {
    // Test de validation
  });
  
  test('gère les erreurs API gracieusement', () => {
    // Test de gestion d'erreurs
  });
});
```

### Outils de Développement

- **Vue DevTools** : Inspection des variables réactives
- **Console Logs** : Traçage des transformations
- **Network Tab** : Vérification des appels API

---

## Migration vers Composant Dédié

### Objectifs de la Migration

1. **Réutilisabilité** : Composant autonome
2. **Maintenabilité** : Code spécialisé et organisé
3. **Testabilité** : Tests unitaires ciblés
4. **Performance** : Optimisations spécifiques

### Structure du Composant StakeField.vue

```vue
<template>
  <div class="stake-field-container">
    <!-- Champ de saisie -->
    <div class="flex flex-col justify-center min-w-0 w-full">
      <div class="w-full">
        <InputText 
          :id="fieldId"
          v-model="localStake" 
          type="text"
          :placeholder="dynamicPlaceholder"
          class="w-full text-xs"
          :class="{ 'p-invalid': hasError }"
          @input="handleInput"
          @keypress="handleKeypress"
        />
      </div>
      <small v-if="error" class="text-red-500 text-xs truncate">{{ error }}</small>
    </div>

    <!-- Sélecteur de type -->
    <div class="flex flex-col justify-center min-w-0 w-full">
      <div class="w-full flex items-center">
        <SelectButton 
          v-model="localStakeType" 
          :options="stakeTypeOptions" 
          optionLabel="symbol" 
          optionValue="value"
          class="h-8 text-xs w-full"
        />
      </div>
    </div>

    <!-- Affichage du gain potentiel -->
    <div v-if="showPotentialWin">
      <!-- Mode pourcentage -->
      <div v-if="localStakeType === 'percentage'" class="percentage-details">
        <!-- Détails complets -->
      </div>
      
      <!-- Mode devise -->
      <div v-else class="currency-simple">
        <!-- Affichage simple -->
      </div>
    </div>
  </div>
</template>

<script setup>
// Props et événements du composant
const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: null
  },
  stakeType: {
    type: String,
    default: 'currency'
  },
  globalOdds: {
    type: [Number, String],
    default: null
  },
  showPotentialWin: {
    type: Boolean,
    default: true
  },
  error: {
    type: String,
    default: ''
  }
});

const emit = defineEmits([
  'update:modelValue',
  'update:stakeType',
  'stake-changed',
  'error',
  'valid'
]);
</script>
```

### Props du Composant

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `modelValue` | Number\|String\|null | `null` | Valeur de la mise |
| `stakeType` | String | `'currency'` | Type de mise |
| `globalOdds` | Number\|String\|null | `null` | Cote pour calcul gain |
| `showPotentialWin` | Boolean | `true` | Afficher le gain potentiel |
| `error` | String | `''` | Message d'erreur |

### Événements Émis

| Événement | Payload | Description |
|-----------|---------|-------------|
| `update:modelValue` | `value: Number` | Mise à jour de la valeur |
| `update:stakeType` | `type: String` | Changement de type |
| `stake-changed` | `{value, type, calculated}` | Changement complet |
| `error` | `message: String` | Erreur de validation |
| `valid` | `isValid: Boolean` | État de validation |

### Intégration dans AddBetForm.vue

```vue
<template>
  <!-- Remplacement du code existant -->
  <StakeField
    v-model="formData.stake"
    v-model:stake-type="betTypeValue"
    :global-odds="formData.global_odds"
    :show-potential-win="true"
    :error="errors.stake"
    @stake-changed="onStakeChanged"
    @error="handleStakeError"
    @valid="handleStakeValid"
  />
</template>

<script setup>
import StakeField from './fields/StakeField.vue';

// Gestionnaires d'événements
function onStakeChanged(stakeData) {
  console.log('Mise modifiée:', stakeData);
  // Logique additionnelle si nécessaire
}

function handleStakeError(message) {
  if (message) {
    errors.value.stake = message;
  } else {
    delete errors.value.stake;
  }
}

function handleStakeValid(isValid) {
  if (isValid) {
    delete errors.value.stake;
  }
}
</script>
```

### Avantages de la Migration

1. **Code réutilisable** : Utilisable dans d'autres contextes
2. **Tests ciblés** : Tests unitaires spécifiques au champ mise
3. **Maintenance simplifiée** : Code organisé et spécialisé
4. **Performance optimisée** : Optimisations spécifiques au composant
5. **API claire** : Props et événements bien définis

---

## Résumé de l'Architecture

### Fonctionnalités Complètes

Le système de mise comprend :

1. **Saisie intelligente** : Normalisation automatique des séparateurs
2. **Types de mise** : Devise (€) et pourcentage (%)
3. **Calcul automatique** : Conversion pourcentage → euros
4. **Récupération du capital** : API pour obtenir le capital utilisateur
5. **Gain potentiel** : Calcul temps réel basé sur la cote
6. **Validation avancée** : Contrôles de format et de valeurs
7. **Gestion d'erreurs** : Messages contextuels et récupération gracieuse
8. **Optimisations** : Watchers, cache, calculs différés

### Structure Finale Recommandée

```
src/components/add-bet/fields/
├── StakeField.vue (Nouveau composant dédié)
├── DOCUMENTATION_CHAMPS_MISE.md (Cette documentation)
└── ... (autres composants de champs)
```

### Migration Réussie

La migration vers un composant dédié offre une solution robuste, maintenable et évolutive pour la gestion du champ mise dans l'application de paris sportifs, tout en conservant toutes les fonctionnalités avancées existantes.