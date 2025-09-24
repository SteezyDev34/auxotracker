# Documentation du Composant TypePariField.vue

## Table des matières
1. [Vue d'ensemble](#vue-densemble)
2. [Architecture du composant](#architecture-du-composant)
3. [Propriétés et émissions](#propriétés-et-émissions)
4. [Fonctionnalités principales](#fonctionnalités-principales)
5. [Validation et gestion d'erreurs](#validation-et-gestion-derreurs)
6. [Intégration avec AddBetForm](#intégration-avec-addbetform)
7. [Dépendances et composables](#dépendances-et-composables)
8. [Cycle de vie et interactions](#cycle-de-vie-et-interactions)
9. [Migration depuis AddBetForm](#migration-depuis-addbetform)
10. [Résumé des améliorations](#résumé-des-améliorations)

---

## 1. Vue d'ensemble

Le composant `TypePariField.vue` est un composant autonome extrait de `AddBetForm.vue` pour gérer la sélection des types de pari. Il suit les mêmes patterns architecturaux que les autres composants de champ (`SportField.vue`, `CountryField.vue`, `LeagueField.vue`, `TeamField.vue`).

### Objectifs du composant :
- **Réutilisabilité** : Composant autonome et réutilisable
- **Maintenabilité** : Code isolé et plus facile à maintenir
- **Cohérence** : Architecture uniforme avec les autres champs
- **Performance** : Optimisation des re-rendus et de la logique

### Localisation :
- **Fichier** : `/frontend/src/components/add-bet/fields/TypePariField.vue`
- **Intégration** : Utilisé dans `AddBetForm.vue` via import et props
- **Pattern** : Suit le modèle des autres composants de champ

---

## 2. Architecture du composant

### Structure du template
```vue
<template>
  <div class="flex flex-col gap-2 mb-4">
    <Select 
      :id="`bet_type_${eventIndex}`" 
      v-model="localValue" 
      :options="filteredBetTypes" 
      optionLabel="label" 
      optionValue="value"
      @change="onBetTypeChange"
      @click="onDropdownShow"
      placeholder="Sélectionner un type de pari"
      class="w-full select-custom"
      :class="{ 'p-invalid': error }"
      :disabled="!sportId"
      dropdown
      dropdownMode="blank"
    />
    <small v-if="error" class="text-red-500 block mt-1">{{ error }}</small>
  </div>
</template>
```

### Structure du script
```vue
<script setup>
import { computed, watch } from 'vue';
import Select from 'primevue/select';
import { useBetTypes } from '@/composables/useBetTypes';

// Props et émissions
// Logique de filtrage
// Watchers et computed properties
</script>
```

---

## 3. Propriétés et émissions

### Props
```javascript
const props = defineProps({
  modelValue: {
    type: [String, Number, null],
    default: null
  },
  eventIndex: {
    type: Number,
    required: true
  },
  sportId: {
    type: [String, Number, null],
    default: null
  },
  sportSlug: {
    type: String,
    default: null
  },
  availableSports: {
    type: Array,
    default: () => []
  },
  error: {
    type: String,
    default: null
  }
});
```

### Émissions
```javascript
const emit = defineEmits([
  'update:modelValue',
  'bet-type-select', 
  'dropdown-show'
]);
```

---

## 4. Fonctionnalités principales

### Filtrage des types de pari
```javascript
const filteredBetTypes = computed(() => {
  if (!props.sportId) {
    return allBetTypeOptions.value;
  }
  
  let sportSlug = props.sportSlug;
  if (!sportSlug && props.availableSports.length > 0) {
    const selectedSport = props.availableSports.find(sport => sport.id === props.sportId);
    if (!selectedSport || !selectedSport.slug) {
      return allBetTypeOptions.value;
    }
    sportSlug = selectedSport.slug;
  }
  
  const sportBetTypes = getBetTypesForSport(sportSlug);
  return allBetTypeOptions.value.filter(option => 
    sportBetTypes.includes(option.value)
  );
});
```

### Gestion des changements
```javascript
function onBetTypeChange(value) {
  emit('update:modelValue', value);
  emit('bet-type-select', value);
}

function onDropdownShow() {
  emit('dropdown-show', props.eventIndex);
}
```

### Watcher pour réinitialisation
```javascript
watch(() => props.sportId, (newSportId, oldSportId) => {
  if (newSportId !== oldSportId && props.modelValue) {
    emit('update:modelValue', null);
  }
});
```

---

## 5. Validation et gestion d'erreurs

### Affichage des erreurs
- **Classe CSS** : `p-invalid` appliquée conditionnellement
- **Message d'erreur** : Affiché via la prop `error`
- **Validation visuelle** : Bordure rouge et texte d'erreur

### États de désactivation
- **Condition** : Champ désactivé si aucun sport n'est sélectionné
- **Logique** : `:disabled="!sportId"`

---

## 6. Intégration avec AddBetForm

### Utilisation dans AddBetForm.vue
```vue
<TypePariField
  v-model="eventData.bet_type"
  :event-index="eventIndex"
  :sport-id="eventData.sport_id"
  :available-sports="availableSports"
  :error="errors[`bet_type-${eventIndex}`]"
  @bet-type-select="(betType) => onBetTypeSelect(betType, eventIndex)"
  @dropdown-show="(index) => onBetTypeDropdownShow(index)"
/>
```

### Import dans AddBetForm.vue
```javascript
import TypePariField from './fields/TypePariField.vue';
```

---

## 7. Dépendances et composables

### Composables utilisés
- **`useBetTypes`** : Gestion des types de pari
  - `getBetTypesForSport()` : Filtrage par sport
  - `allBetTypeOptions` : Toutes les options disponibles

### Composants PrimeVue
- **`Select`** : Composant de sélection avec dropdown

### Dépendances Vue
- **`computed`** : Pour les propriétés calculées
- **`watch`** : Pour surveiller les changements de sport

---

## 8. Cycle de vie et interactions

### Initialisation
1. Chargement des options de types de pari via `useBetTypes`
2. Configuration des props et émissions
3. Mise en place des watchers

### Interactions utilisateur
1. **Sélection de sport** → Filtrage automatique des types de pari
2. **Ouverture dropdown** → Émission de l'événement `dropdown-show`
3. **Sélection type de pari** → Émission de `bet-type-select` et `update:modelValue`

### Réactivité
- **Changement de sport** → Réinitialisation du type de pari
- **Filtrage dynamique** → Mise à jour des options disponibles

---

## 9. Migration depuis AddBetForm

### Code supprimé d'AddBetForm.vue
- **Fonction** : `getFilteredBetTypesForEvent()`
- **Computed** : `filteredBetTypes`
- **Import** : `useBetTypes`
- **Variables** : `getBetTypesForSport`, `allBetTypeOptions`
- **Template** : Bloc HTML du sélecteur de type de pari

### Code conservé dans AddBetForm.vue
- **Fonction** : `onBetTypeDropdownShow()` (utilisée par le composant)
- **Fonction** : `onBetTypeSelect()` (nouvelle, pour gérer la sélection)

### Avantages de la migration
1. **Séparation des responsabilités**
2. **Réduction de la complexité d'AddBetForm**
3. **Réutilisabilité du composant**
4. **Maintenance facilitée**

---

## 10. Résumé des améliorations

### Améliorations architecturales
- ✅ **Composant autonome** : Logique isolée et réutilisable
- ✅ **Props typées** : Interface claire et documentée
- ✅ **Émissions explicites** : Communication claire avec le parent
- ✅ **Pattern uniforme** : Cohérence avec les autres champs

### Améliorations fonctionnelles
- ✅ **Filtrage optimisé** : Logique de filtrage encapsulée
- ✅ **Gestion d'erreurs** : Validation et affichage intégrés
- ✅ **Réactivité améliorée** : Watchers pour la synchronisation
- ✅ **Performance** : Re-rendus optimisés

### Améliorations de maintenance
- ✅ **Code modulaire** : Facilite les modifications
- ✅ **Tests isolés** : Possibilité de tester indépendamment
- ✅ **Documentation** : Composant bien documenté
- ✅ **Évolutivité** : Facilite l'ajout de nouvelles fonctionnalités

---

## Conclusion

L'extraction du composant `TypePariField.vue` représente une amélioration significative de l'architecture de l'application. Le composant suit les meilleures pratiques Vue.js et s'intègre parfaitement dans l'écosystème existant tout en offrant une meilleure maintenabilité et réutilisabilité.