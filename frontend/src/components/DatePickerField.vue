<template>
  <div class="flex flex-col gap-2">
    <!-- DatePicker -->
    <DatePicker 
      :id="fieldId" 
      :modelValue="modelValue" 
      @update:modelValue="handleDateChange"
      :dateFormat="dateFormat" 
      :showIcon="showIcon" 
      :placeholder="computedPlaceholder"
      :class="computedClasses"
      v-bind="$attrs"
    />
    
    <!-- Message d'erreur -->
    <small 
      v-if="hasError" 
      class="text-red-500 block mt-1"
    >
      {{ errorMessage }}
    </small>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import DatePicker from 'primevue/datepicker';

/**
 * Composant DatePickerField réutilisable optimisé
 * Encapsule le DatePicker de PrimeVue avec validation et gestion d'erreurs
 */

// Props essentiels uniquement
const props = defineProps({
  // Valeur du champ
  modelValue: {
    type: [Date, String, Array],
    default: null
  },
  
  // Configuration de base
  placeholder: {
    type: String,
    default: 'Sélectionner une date'
  },
  
  required: {
    type: Boolean,
    default: false
  },
  
  // Configuration du DatePicker
  dateFormat: {
    type: String,
    default: 'dd/mm/yy'
  },
  
  showIcon: {
    type: Boolean,
    default: true
  },
  
  // Validation et erreurs
  error: {
    type: [String, Boolean],
    default: false
  },
  
  errorMessage: {
    type: String,
    default: ''
  },
  
  // Styles personnalisés
  fieldClass: {
    type: String,
    default: 'w-full'
  },
  
  // ID personnalisé
  fieldId: {
    type: String,
    default: () => `datepicker-${Math.random().toString(36).substr(2, 9)}`
  }
});

// Emits
const emit = defineEmits(['update:modelValue', 'change']);

// Computed
/**
 * Placeholder calculé avec indicateur de champ requis
 */
const computedPlaceholder = computed(() => {
  if (props.required && !props.placeholder.includes('*')) {
    return `${props.placeholder} *`;
  }
  return props.placeholder;
});

/**
 * Classes CSS calculées pour le DatePicker
 */
const computedClasses = computed(() => {
  const classes = [props.fieldClass];
  
  // Ajouter la classe d'erreur si nécessaire
  if (hasError.value) {
    classes.push('p-invalid');
  }
  
  return classes.join(' ');
});

/**
 * Vérifier s'il y a une erreur
 */
const hasError = computed(() => {
  return props.error === true || (typeof props.error === 'string' && props.error.length > 0) || props.errorMessage.length > 0;
});

// Méthodes
/**
 * Gérer le changement de date
 * @param {Date|String|Array} value - Nouvelle valeur de date
 */
function handleDateChange(value) {
  // Émettre la mise à jour du modèle
  emit('update:modelValue', value);
  
  // Émettre l'événement de changement avec plus de détails
  emit('change', {
    value,
    target: {
      name: props.fieldId,
      value
    }
  });
}
</script>