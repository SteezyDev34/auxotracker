<template>
  <div class="flex flex-col justify-center min-w-0 w-full">
    <!-- Champ de saisie de la cote globale -->
    <div class="w-full">
      <InputText 
        id="global_odds" 
        v-model="localOdds" 
        type="text"
        placeholder="Cote"
        class="w-full text-xs"
        :class="{ 'p-invalid': hasError }"
        @input="handleOddsInput"
        @keypress="handleOddsKeypress"
        @blur="validateOdds"
      />
    </div>
    
    <!-- Message d'erreur -->
    <small v-if="hasError" class="text-red-500 text-xs truncate">
      {{ errorMessage }}
    </small>
    
    <!-- Affichage du gain potentiel (optionnel) -->
    <div v-if="showPotentialWin && localOdds && stake > 0" class="mt-2">
      <div class="flex justify-between items-center mb-2">
        <span class="text-sm text-gray-600">Cote :</span>
        <span class="text-sm font-medium">{{ parseFloat(localOdds).toFixed(2) }}</span>
      </div>
      
      <div class="flex justify-between items-center pt-2 border-t border-gray-200">
        <span class="text-sm font-semibold text-gray-800">Gain potentiel :</span>
        <span class="text-lg font-bold text-green-600">{{ potentialWin.toFixed(2) }} €</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import InputText from 'primevue/inputtext'

/**
 * Props du composant GlobalOddsField
 */
const props = defineProps({
  // Valeur de la cote globale
  modelValue: {
    type: [Number, String, null],
    default: null
  },
  
  // Mise pour le calcul du gain potentiel
  stake: {
    type: [Number, String],
    default: 0
  },
  
  // Afficher ou non le gain potentiel
  showPotentialWin: {
    type: Boolean,
    default: false
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
  'valid'
])

/**
 * État local du composant
 */
const localOdds = ref(props.modelValue)
const internalError = ref('')

/**
 * Propriétés calculées
 */

// Message d'erreur (externe ou interne)
const errorMessage = computed(() => {
  return props.error || internalError.value
})

// Indicateur d'erreur
const hasError = computed(() => {
  return Boolean(errorMessage.value)
})

// Calcul du gain potentiel
const potentialWin = computed(() => {
  const stakeValue = parseFloat(props.stake) || 0
  const oddsValue = parseFloat(localOdds.value) || 0
  
  if (stakeValue > 0 && oddsValue > 0) {
    return stakeValue * oddsValue
  }
  return 0
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
watch(localOdds, (newValue) => {
  emit('update:modelValue', newValue)
  emit('change', newValue)
})

// Surveiller les erreurs externes
watch(() => props.error, (newError) => {
  if (newError) {
    internalError.value = ''
  }
})

/**
 * Gérer la saisie de la cote pour remplacer immédiatement les virgules par des points
 * @param {Event} event - Événement d'input
 */
function handleOddsInput(event) {
  let inputValue = event.target.value
  console.log('GlobalOddsField - handleOddsInput - Valeur tapée:', inputValue)
  
  // Remplacer immédiatement toutes les virgules par des points
  const normalizedValue = inputValue.replace(/,/g, '.')
  console.log('GlobalOddsField - handleOddsInput - Valeur normalisée:', normalizedValue)
  
  // Si une virgule a été détectée, forcer le remplacement immédiat
  if (inputValue !== normalizedValue) {
    console.log('GlobalOddsField - handleOddsInput - Virgule détectée, remplacement en cours...')
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
    console.log('GlobalOddsField - handleOddsInput - Remplacement terminé, nouvelle valeur:', event.target.value)
    
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
    console.warn('GlobalOddsField - Valeur de cote invalide:', normalizedValue)
    if (props.autoValidate) {
      setError('La cote doit être un nombre valide')
    }
  }
  
  // Émettre l'événement input
  emit('input', localOdds.value)
}

/**
 * Gérer les touches pressées pour la cote globale (permettre point et virgule)
 * @param {KeyboardEvent} event - Événement de frappe
 */
function handleOddsKeypress(event) {
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
  
  // Si pas de valeur, pas d'erreur (champ optionnel)
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
    const input = document.getElementById('global_odds')
    if (input) input.focus()
  }
})
</script>

<style scoped>
/* Styles spécifiques au composant GlobalOddsField */
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

.border-gray-200 {
  border-color: #e5e7eb;
}
</style>