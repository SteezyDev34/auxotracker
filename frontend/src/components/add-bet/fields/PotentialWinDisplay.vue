<template>
  <div v-if="showPotentialWin && potentialWin > 0" class="w-full mt-3">
    <!-- Mode pourcentage : affichage détaillé -->
    <div v-if="stakeType === 'percentage'" class="w-full bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-green-700">Gain potentiel</span>
        <span class="text-lg font-bold text-green-800">{{ formatCurrency(potentialWin) }}</span>
      </div>
      <div class="text-xs text-green-600 space-y-1">
        <div class="flex justify-between">
          <span>Capital actuel :</span>
          <span>{{ formatCurrency(currentCapital) }}</span>
        </div>
        <div class="flex center">
          <span>Mise ({{ stakeValue }}%) :</span>
          <span>{{ formatCurrency(calculatedStake) }}</span>
        </div>
        <div class="flex justify-between">
          <span>Cote :</span>
          <span>{{ globalOdds }}</span>
        </div>
      </div>
    </div>

    <!-- Mode devise : affichage simple -->
    <div v-else class="w-full bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
      <div class="text-center">
        <span class="text-sm font-medium text-green-700">Gain potentiel : </span>
        <span class="text-lg font-bold text-green-800">{{ formatCurrency(potentialWin) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

// ===== PROPS =====

/**
 * Props du composant PotentialWinDisplay
 */
const props = defineProps({
  /**
   * Valeur de la mise
   * @type {Number}
   */
  stakeValue: {
    type: Number,
    default: 0
  },
  
  /**
   * Type de mise (currency ou percentage)
   * @type {String}
   */
  stakeType: {
    type: String,
    default: 'currency',
    validator: (value) => ['currency', 'percentage'].includes(value)
  },
  
  /**
   * Cote globale
   * @type {Number}
   */
  globalOdds: {
    type: Number,
    default: 1
  },
  
  /**
   * Capital actuel (pour mode pourcentage)
   * @type {Number}
   */
  currentCapital: {
    type: Number,
    default: 0
  },
  
  /**
   * Mise calculée en euros (pour mode pourcentage)
   * @type {Number}
   */
  calculatedStake: {
    type: Number,
    default: 0
  },
  
  /**
   * Afficher ou masquer le gain potentiel
   * @type {Boolean}
   */
  showPotentialWin: {
    type: Boolean,
    default: true
  }
});

// ===== PROPRIÉTÉS CALCULÉES =====

/**
 * Calcul du gain potentiel basé sur la mise et la cote globale
 * @returns {Number} Gain potentiel en euros
 */
const potentialWin = computed(() => {
  let stake = 0;
  
  // Utiliser la mise calculée en mode pourcentage, sinon la mise directe
  if (props.stakeType === 'percentage' && props.calculatedStake > 0) {
    stake = props.calculatedStake;
  } else {
    stake = parseFloat(props.stakeValue);
  }
  
  const odds = parseFloat(props.globalOdds);
  
  // Validation des valeurs
  if (isNaN(stake) || isNaN(odds) || stake <= 0 || odds <= 0) {
    return 0;
  }
  
  return stake * odds;
});

// ===== FONCTIONS =====

/**
 * Formater un montant en devise
 * @param {Number} amount - Montant à formater
 * @returns {String} Montant formaté
 */
function formatCurrency(amount) {
  if (!amount || isNaN(amount)) {
    return '0,00 €';
  }
  
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount);
}
</script>

<style scoped>
/**
 * Styles spécifiques au composant PotentialWinDisplay
 * Utilise principalement les classes Tailwind CSS
 */

</style>