<template>
  <div class="flex flex-col gap-2">
    <!-- Champ de saisie de la cote d'événement -->
    <InputText 
      :id="fieldId" 
      :ref="fieldRef"
      v-model="localOdds" 
      placeholder="Cote de l'événement *"
      class="w-full"
      :class="{ 'p-invalid': hasError }"
      type="text"
      @input="handleEventOddsInput"
      @keypress="handleEventOddsKeypress"
      @blur="validateOdds"
    />
    
    <!-- Message d'erreur -->
    <small v-if="hasError" class="text-red-500 block mt-1">
      {{ errorMessage }}
    </small>
    
    <!-- Affichage de la cote dans la liste des événements (optionnel) -->
    <div v-if="showOddsDisplay && localOdds" class="text-xs">
      <span class="text-green-600 font-medium">
        Cote: {{ parseFloat(localOdds).toFixed(2) }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import InputText from 'primevue/inputtext'

/**
 * Props du composant EventOddsField
 */
const props = defineProps({
  // Valeur de la cote de l'événement
  modelValue: {
    type: [Number, String, null],
    default: null
  },
  
  // Index de l'événement pour l'identification unique
  eventIndex: {
    type: [Number, String],
    required: true
  },
  
  // Message d'erreur externe
  error: {
    type: String,
    default: ''
  },
  
  // Validation automatique
  autoValidate: {
    type: Boolean,
    default: true
  },
  
  // Afficher la cote dans la liste des événements
  showOddsDisplay: {
    type: Boolean,
    default: false
  },
  
  // Valeurs min/max pour la validation
  minValue: {
    type: Number,
    default: 1.01
  },
  
  maxValue: {
    type: Number,
    default: 1000
  },
  
  // Nombre maximum de décimales
  maxDecimals: {
    type: Number,
    default: 2
  },
  
  // Champ requis
  required: {
    type: Boolean,
    default: true
  }
})

/**
 * Événements émis par le composant
 */
const emit = defineEmits([
  'update:modelValue',
  'input',
  'change',
  'error',
  'valid',
  'odds-changed' // Événement spécifique pour le recalcul de la cote globale
])

/**
 * État local du composant
 */
const localOdds = ref(props.modelValue)
const internalError = ref('')

/**
 * Propriétés calculées
 */

// ID unique pour le champ
const fieldId = computed(() => {
  return `event_odds_${props.eventIndex}`
})

// Référence unique pour le champ
const fieldRef = computed(() => {
  return `eventOddsInput_${props.eventIndex}`
})

// Message d'erreur (externe ou interne)
const errorMessage = computed(() => {
  return props.error || internalError.value
})

// Indicateur d'erreur
const hasError = computed(() => {
  return Boolean(errorMessage.value)
})

/**
 * Watchers
 */

// Synchroniser avec la valeur externe
watch(() => props.modelValue, (newValue) => {
  if (newValue !== localOdds.value) {
    localOdds.value = newValue
  }
})

// Émettre les changements de valeur
watch(localOdds, (newValue, oldValue) => {
  emit('update:modelValue', newValue)
  emit('change', newValue)
  
  // Émettre l'événement spécifique pour le recalcul de la cote globale
  if (newValue !== oldValue) {
    emit('odds-changed', {
      eventIndex: props.eventIndex,
      odds: newValue,
      previousOdds: oldValue
    })
  }
})

// Surveiller les erreurs externes
watch(() => props.error, (newError) => {
  if (newError) {
    internalError.value = ''
  }
})

/**
 * Gérer la saisie de la cote d'événement pour remplacer immédiatement les virgules par des points
 * @param {Event} event - Événement d'input
 */
function handleEventOddsInput(event) {
  let inputValue = event.target.value
  console.log('EventOddsField - handleEventOddsInput - Valeur tapée:', inputValue, 'pour événement', props.eventIndex)
  
  // Remplacer immédiatement toutes les virgules par des points
  const normalizedValue = inputValue.replace(/,/g, '.')
  console.log('EventOddsField - handleEventOddsInput - Valeur normalisée:', normalizedValue)
  
  // Si une virgule a été détectée, forcer le remplacement immédiat
  if (inputValue !== normalizedValue) {
    console.log('EventOddsField - handleEventOddsInput - Virgule détectée, remplacement en cours...')
    // Sauvegarder la position du curseur
    const cursorPosition = event.target.selectionStart
    
    // Mettre à jour immédiatement la valeur de l'input
    event.target.value = normalizedValue
    
    // Restaurer la position du curseur
    nextTick(() => {
      event.target.setSelectionRange(cursorPosition, cursorPosition)
    })
    
    // Mettre à jour le v-model
    localOdds.value = normalizedValue
    console.log('EventOddsField - handleEventOddsInput - Remplacement terminé, nouvelle valeur:', event.target.value)
    
    // Émettre l'événement input
    emit('input', normalizedValue)
    return
  }
  
  // Vérifier que la valeur est un nombre réel valide
  if (normalizedValue === '' || normalizedValue === '.') {
    localOdds.value = null
    clearError()
    emit('input', null)
    return
  }
  
  // Validation du format nombre réel
  const numericValue = parseFloat(normalizedValue)
  if (!isNaN(numericValue) && isFinite(numericValue) && numericValue > 0) {
    localOdds.value = numericValue
    if (props.autoValidate) {
      validateOdds()
    }
  } else {
    // Si la valeur n'est pas valide, on garde la dernière valeur valide
    console.warn('EventOddsField - Valeur de cote d\'événement invalide:', normalizedValue)
    if (props.autoValidate) {
      setError('La cote doit être un nombre valide')
    }
  }
  
  // Émettre l'événement input
  emit('input', localOdds.value)
}

/**
 * Gérer les touches pressées pour la cote d'événement (permettre point et virgule)
 * @param {KeyboardEvent} event - Événement de frappe
 */
function handleEventOddsKeypress(event) {
  const char = String.fromCharCode(event.which)
  const currentValue = event.target.value
  
  // Permettre les chiffres, le point, la virgule et les touches de contrôle
  if (!/[0-9.,]/.test(char) && event.which !== 8 && event.which !== 46 && event.which !== 37 && event.which !== 39) {
    event.preventDefault()
    return
  }
  
  // Empêcher plusieurs séparateurs décimaux (point ou virgule)
  if ((char === '.' || char === ',') && (currentValue.includes('.') || currentValue.includes(','))) {
    event.preventDefault()
    return
  }
  
  // Empêcher le point/virgule en première position
  if ((char === '.' || char === ',') && currentValue === '') {
    event.preventDefault()
    return
  }
}

/**
 * Valider la cote selon les règles définies
 */
function validateOdds() {
  clearError()
  
  const oddsValue = parseFloat(localOdds.value)
  
  // Si le champ est requis et vide
  if (props.required && (!localOdds.value || localOdds.value === '')) {
    setError('La cote de l\'événement est requise')
    return false
  }
  
  // Si pas de valeur et pas requis, pas d'erreur
  if (!localOdds.value || localOdds.value === '') {
    emit('valid', true)
    return true
  }
  
  // Vérifier que c'est un nombre valide
  if (isNaN(oddsValue) || !isFinite(oddsValue)) {
    setError('La cote doit être un nombre valide')
    return false
  }
  
  // Vérifier la valeur minimale
  if (oddsValue < props.minValue) {
    setError(`La cote doit être supérieure ou égale à ${props.minValue}`)
    return false
  }
  
  // Vérifier la valeur maximale
  if (oddsValue > props.maxValue) {
    setError(`La cote ne peut pas dépasser ${props.maxValue}`)
    return false
  }
  
  // Vérifier le nombre de décimales
  const decimalPart = localOdds.value.toString().split('.')[1]
  if (decimalPart && decimalPart.length > props.maxDecimals) {
    setError(`Maximum ${props.maxDecimals} décimales autorisées`)
    return false
  }
  
  emit('valid', true)
  return true
}

/**
 * Définir un message d'erreur
 * @param {string} message - Message d'erreur
 */
function setError(message) {
  internalError.value = message
  emit('error', message)
  emit('valid', false)
}

/**
 * Effacer l'erreur
 */
function clearError() {
  internalError.value = ''
  emit('error', '')
}

/**
 * Méthodes exposées pour l'utilisation externe
 */
defineExpose({
  validate: validateOdds,
  clearError,
  focus: () => {
    const input = document.getElementById(fieldId.value)
    if (input) input.focus()
  },
  getValue: () => localOdds.value,
  setValue: (value) => {
    localOdds.value = value
  }
})
</script>

<style scoped>
/* Styles spécifiques au composant EventOddsField */
.p-invalid {
  border-color: #ef4444;
}

.text-red-500 {
  color: #ef4444;
}

.text-green-600 {
  color: #16a34a;
}

.text-gray-600 {
  color: #4b5563;
}

.text-gray-800 {
  color: #1f2937;
}
</style>