<template>
  <!-- Composant invisible qui gère uniquement la logique de calcul -->
  <div style="display: none;"></div>
</template>

<script setup>
import { watch, computed } from 'vue'

/**
 * Props du composant OddsCalculator
 */
const props = defineProps({
  // Liste des événements avec leurs cotes
  eventCards: {
    type: Array,
    required: true,
    default: () => []
  },
  
  // Cote globale actuelle
  globalOdds: {
    type: [Number, String, null],
    default: null
  },
  
  // Activer/désactiver le calcul automatique
  autoCalculate: {
    type: Boolean,
    default: true
  },
  
  // Nombre de décimales pour le résultat
  decimals: {
    type: Number,
    default: 2
  }
})

/**
 * Événements émis par le composant
 */
const emit = defineEmits([
  'global-odds-calculated',
  'calculation-failed',
  'calculation-cleared'
])

/**
 * Propriétés calculées
 */

// Vérifier si toutes les cotes sont valides
const allOddsValid = computed(() => {
  if (!props.eventCards || props.eventCards.length === 0) {
    return false
  }
  
  return props.eventCards.every(eventCard => {
    return eventCard.odds && 
           parseFloat(eventCard.odds) > 0 && 
           !isNaN(parseFloat(eventCard.odds))
  })
})

// Calculer la cote globale
const calculatedGlobalOdds = computed(() => {
  if (!allOddsValid.value) {
    return null
  }
  
  let globalOdds = 1
  
  try {
    props.eventCards.forEach(eventCard => {
      const odds = parseFloat(eventCard.odds)
      globalOdds *= odds
    })
    
    return parseFloat(globalOdds.toFixed(props.decimals))
  } catch (error) {
    console.error('OddsCalculator - Erreur lors du calcul:', error)
    return null
  }
})

/**
 * Watchers
 */

// Surveiller les changements dans les cotes des événements
watch(() => props.eventCards.map(event => event.odds), (newOdds, oldOdds) => {
  if (!props.autoCalculate) {
    return
  }
  
  console.log('OddsCalculator - Changement détecté dans les cotes:', { newOdds, oldOdds })
  calculateGlobalOdds()
}, { deep: true })

// Surveiller les changements dans le nombre d'événements
watch(() => props.eventCards.length, (newLength, oldLength) => {
  if (!props.autoCalculate) {
    return
  }
  
  console.log('OddsCalculator - Changement du nombre d\'événements:', { newLength, oldLength })
  calculateGlobalOdds()
})

/**
 * Fonctions
 */

/**
 * Calculer la cote globale en multipliant toutes les cotes des événements
 */
function calculateGlobalOdds() {
  console.log('OddsCalculator - calculateGlobalOdds - Début du calcul')
  
  // Si pas d'événements, effacer la cote globale
  if (!props.eventCards || props.eventCards.length === 0) {
    console.log('OddsCalculator - Aucun événement, effacement de la cote globale')
    emit('calculation-cleared')
    return
  }
  
  // Si toutes les cotes ne sont pas valides, ne pas calculer
  if (!allOddsValid.value) {
    console.log('OddsCalculator - Toutes les cotes ne sont pas valides, pas de calcul automatique')
    const validOdds = props.eventCards.filter(event => event.odds && parseFloat(event.odds) > 0)
    console.log('OddsCalculator - Cotes valides:', validOdds.length, '/', props.eventCards.length)
    return
  }
  
  // Calculer la cote globale
  const result = calculatedGlobalOdds.value
  
  if (result !== null) {
    console.log('OddsCalculator - Cote globale calculée automatiquement:', result)
    emit('global-odds-calculated', result)
  } else {
    console.log('OddsCalculator - Échec du calcul de la cote globale')
    emit('calculation-failed', 'Erreur lors du calcul de la cote globale')
  }
}

/**
 * Forcer le recalcul de la cote globale
 */
function forceCalculate() {
  calculateGlobalOdds()
}

/**
 * Vérifier si le calcul automatique est possible
 */
function canAutoCalculate() {
  return allOddsValid.value && props.eventCards.length > 0
}

/**
 * Obtenir les détails du calcul
 */
function getCalculationDetails() {
  return {
    eventCount: props.eventCards.length,
    validOdds: props.eventCards.filter(event => event.odds && parseFloat(event.odds) > 0).length,
    allOddsValid: allOddsValid.value,
    calculatedOdds: calculatedGlobalOdds.value,
    canCalculate: canAutoCalculate()
  }
}

/**
 * Méthodes exposées pour l'utilisation externe
 */
defineExpose({
  calculateGlobalOdds: forceCalculate,
  canAutoCalculate,
  getCalculationDetails,
  allOddsValid: allOddsValid.value,
  calculatedGlobalOdds: calculatedGlobalOdds.value
})
</script>

<style scoped>
/* Aucun style nécessaire pour ce composant utilitaire */
</style>