<template>
  <div class="stake-field-container">
    <!-- Conteneur pour aligner le champ de mise et le sélecteur de type -->
    <div class="flex gap-2 items-start w-full">
      <!-- Champ de saisie de mise -->
      <div class="flex flex-col justify-center min-w-0 flex-1">
        <div class="w-full">
          <InputText 
            :id="fieldId"
            v-model="localStake" 
            type="text"
            :placeholder="dynamicPlaceholder"
            class="w-full text-xs"
            :class="{ 'p-invalid': hasError }"
            @input="handleStakeInput"
            @keypress="handleStakeKeypress"
          />
        </div>
        <small v-if="error" class="text-red-500 text-xs truncate">{{ error }}</small>
      </div>

      <!-- Sélecteur de type de mise -->
      <div class="flex flex-col justify-center min-w-0 flex-shrink-0">
        <div class="flex items-center">
          <SelectButton 
            v-model="localStakeType" 
            :options="stakeTypeOptions" 
            optionLabel="symbol" 
            optionValue="value"
            class="h-8 text-xs"
          />
        </div>
      </div>
    </div>

    <!-- Affichage du gain potentiel -->
    <div v-if="showPotentialWin && potentialWin > 0" class="w-full mt-3">
      <!-- Mode pourcentage : affichage détaillé -->
      <div v-if="localStakeType === 'percentage'" class="w-full bg-green-50 border border-green-200 rounded-lg p-3">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium text-green-700">Gain potentiel</span>
          <span class="text-lg font-bold text-green-800">{{ formatCurrency(potentialWin) }}</span>
        </div>
        <div class="text-xs text-green-600 space-y-1">
          <div class="flex justify-between">
            <span>Capital actuel :</span>
            <span>{{ formatCurrency(currentCapital) }}</span>
          </div>
          <div class="flex justify-between">
            <span>Mise ({{ localStake }}%) :</span>
            <span>{{ formatCurrency(calculatedStake) }}</span>
          </div>
          <div class="flex justify-between">
            <span>Cote :</span>
            <span>{{ localGlobalOdds }}</span>
          </div>
        </div>
      </div>

      <!-- Mode devise : affichage simple -->
      <div v-else class="w-full bg-blue-50 border border-blue-200 rounded-lg p-3">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-blue-700">Gain potentiel</span>
          <span class="text-lg font-bold text-blue-800">{{ formatCurrency(potentialWin) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import InputText from 'primevue/inputtext';
import SelectButton from 'primevue/selectbutton';
import { BetService } from '@/service/BetService';

/**
 * Props du composant StakeField
 * Gère la saisie de mise avec support devise/pourcentage et calcul du gain potentiel
 */
const props = defineProps({
  /**
   * Valeur de la mise (v-model principal)
   * @type {Number|String|null}
   */
  modelValue: {
    type: [Number, String],
    default: null
  },
  
  /**
   * Type de mise : 'currency' (€) ou 'percentage' (%)
   * @type {String}
   */
  stakeType: {
    type: String,
    default: 'currency',
    validator: (value) => ['currency', 'percentage'].includes(value)
  },
  
  /**
   * Cote globale pour le calcul du gain potentiel
   * @type {Number|String|null}
   */
  globalOdds: {
    type: [Number, String],
    default: null
  },
  
  /**
   * Afficher ou masquer le calcul du gain potentiel
   * @type {Boolean}
   */
  showPotentialWin: {
    type: Boolean,
    default: true
  },
  
  /**
   * Message d'erreur à afficher
   * @type {String}
   */
  error: {
    type: String,
    default: ''
  },
  
  /**
   * ID unique pour le champ (pour les labels)
   * @type {String}
   */
  fieldId: {
    type: String,
    default: 'stake'
  }
});

/**
 * Événements émis par le composant
 */
const emit = defineEmits([
  /**
   * Mise à jour de la valeur de la mise (v-model)
   * @param {Number} value - Nouvelle valeur de la mise
   */
  'update:modelValue',
  
  /**
   * Mise à jour du type de mise (v-model)
   * @param {String} type - Nouveau type de mise
   */
  'update:stakeType',
  
  /**
   * Événement complet de changement de mise
   * @param {Object} stakeData - Données complètes de la mise
   */
  'stake-changed',
  
  /**
   * Événement d'erreur de validation
   * @param {String} message - Message d'erreur
   */
  'error',
  
  /**
   * Événement de validation réussie
   * @param {Boolean} isValid - État de validation
   */
  'valid'
]);

// ===== VARIABLES RÉACTIVES =====

/**
 * Valeur locale de la mise (liaison bidirectionnelle avec le parent)
 * @type {Ref<Number|null>}
 */
const localStake = ref(props.modelValue);

/**
 * Type de mise local (liaison bidirectionnelle avec le parent)
 * @type {Ref<String>}
 */
const localStakeType = ref(props.stakeType);

/**
 * Cote globale locale (synchronisée avec le parent)
 * @type {Ref<Number|null>}
 */
const localGlobalOdds = ref(props.globalOdds);

/**
 * Capital actuel de l'utilisateur (récupéré via API)
 * @type {Ref<Number>}
 */
const currentCapital = ref(0);

/**
 * Mise calculée en euros (mode pourcentage)
 * @type {Ref<Number>}
 */
const calculatedStake = ref(0);

/**
 * État de chargement du capital
 * @type {Ref<Boolean>}
 */
const capitalLoading = ref(false);

/**
 * Options pour le sélecteur de type de mise
 * @type {Ref<Array>}
 */
const stakeTypeOptions = ref([
  { symbol: '€', value: 'currency' },
  { symbol: '%', value: 'percentage' }
]);

// ===== PROPRIÉTÉS CALCULÉES =====

/**
 * Placeholder dynamique selon le type de mise
 * @returns {String} Texte du placeholder
 */
const dynamicPlaceholder = computed(() => {
  switch (localStakeType.value) {
    case 'currency':
      return 'Mise en €';
    case 'percentage':
      return 'Mise en %';
    default:
      return 'Mise';
  }
});

/**
 * Indique si le champ a une erreur
 * @returns {Boolean} True si erreur présente
 */
const hasError = computed(() => {
  return !!props.error;
});

/**
 * Validation de la mise selon le type
 * @returns {Boolean} True si la mise est valide
 */
const isStakeValid = computed(() => {
  if (!localStake.value || localStake.value <= 0) {
    return false;
  }
  
  if (localStakeType.value === 'percentage') {
    return localStake.value <= 100;
  }
  
  return localStake.value <= 999999.99;
});

/**
 * Calcul du gain potentiel basé sur la mise et la cote globale
 * @returns {Number} Gain potentiel en euros
 */
const potentialWin = computed(() => {
  let stake = 0;
  
  // Utiliser la mise calculée en mode pourcentage, sinon la mise directe
  if (localStakeType.value === 'percentage' && calculatedStake.value > 0) {
    stake = calculatedStake.value;
  } else {
    stake = parseFloat(localStake.value);
  }
  
  const odds = parseFloat(localGlobalOdds.value);
  
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

/**
 * Récupérer le capital actuel de l'utilisateur via l'API
 * @async
 * @function fetchCurrentCapital
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
    emit('error', 'Impossible de récupérer le capital actuel');
  } finally {
    capitalLoading.value = false;
  }
}

/**
 * Calculer la mise en pourcentage du capital
 * @function calculatePercentageStake
 */
function calculatePercentageStake() {
  if (localStakeType.value === 'percentage' && localStake.value && currentCapital.value > 0) {
    const percentage = parseFloat(localStake.value);
    if (!isNaN(percentage) && percentage > 0) {
      calculatedStake.value = (currentCapital.value * percentage) / 100;
      return;
    }
  }
  calculatedStake.value = 0;
}

/**
 * Gérer la saisie de la mise pour accepter les virgules et les points comme séparateurs décimaux
 * @param {Event} event - Événement d'input
 * @function handleStakeInput
 */
function handleStakeInput(event) {
  let inputValue = event.target.value;
  console.log('StakeField - handleStakeInput - Valeur tapée:', inputValue);
  
  // Remplacer immédiatement toutes les virgules par des points
  const normalizedValue = inputValue.replace(/,/g, '.');
  console.log('StakeField - handleStakeInput - Valeur normalisée:', normalizedValue);
  
  // Si une virgule a été détectée, forcer le remplacement immédiat
  if (inputValue !== normalizedValue) {
    console.log('StakeField - handleStakeInput - Virgule détectée, remplacement en cours...');
    // Sauvegarder la position du curseur
    const cursorPosition = event.target.selectionStart;
    
    // Mettre à jour immédiatement la valeur de l'input
    event.target.value = normalizedValue;
    
    // Restaurer la position du curseur
    event.target.setSelectionRange(cursorPosition, cursorPosition);
    
    // Mettre à jour le v-model local
    localStake.value = normalizedValue;
    console.log('StakeField - handleStakeInput - Remplacement terminé, nouvelle valeur:', event.target.value);
    return;
  }
  
  // Vérifier que la valeur est un nombre réel valide
  if (normalizedValue === '' || normalizedValue === '.') {
    localStake.value = null;
    return;
  }
  
  // Validation du format nombre réel (la mise peut être 0)
  const numericValue = parseFloat(normalizedValue);
  if (!isNaN(numericValue) && isFinite(numericValue) && numericValue >= 0) {
    localStake.value = numericValue;
  } else {
    // Si la valeur n'est pas valide, on garde la dernière valeur valide
    console.warn('StakeField - Valeur de mise invalide:', normalizedValue);
  }
}

/**
 * Gérer les touches pressées pour la mise (permettre point et virgule)
 * @param {KeyboardEvent} event - Événement de frappe
 * @function handleStakeKeypress
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

/**
 * Valider la mise et émettre les événements appropriés
 * @function validateStake
 */
function validateStake() {
  let errorMessage = '';
  
  if (!localStake.value || localStake.value <= 0) {
    errorMessage = 'La mise doit être supérieure à 0';
  } else if (localStakeType.value === 'percentage' && localStake.value > 100) {
    errorMessage = 'Le pourcentage ne peut pas dépasser 100%';
  } else if (localStakeType.value === 'currency' && localStake.value > 999999.99) {
    errorMessage = 'La mise ne peut pas dépasser 999 999,99 €';
  }
  
  if (errorMessage) {
    emit('error', errorMessage);
    emit('valid', false);
  } else {
    emit('error', '');
    emit('valid', true);
  }
}

/**
 * Émettre les données complètes de changement de mise
 * @function emitStakeChanged
 */
function emitStakeChanged() {
  const stakeData = {
    value: localStake.value,
    type: localStakeType.value,
    calculated: localStakeType.value === 'percentage' ? calculatedStake.value : localStake.value,
    capital: currentCapital.value
  };
  
  emit('stake-changed', stakeData);
}

// ===== WATCHERS =====

/**
 * Surveiller les changements de la valeur de mise
 * Synchronise avec le parent et recalcule les valeurs dépendantes
 */
watch(localStake, (newValue) => {
  emit('update:modelValue', newValue);
  calculatePercentageStake();
  validateStake();
  emitStakeChanged();
});

/**
 * Surveiller les changements du type de mise
 * Récupère le capital si nécessaire et recalcule les valeurs
 */
watch(localStakeType, async (newValue) => {
  emit('update:stakeType', newValue);
  
  if (newValue === 'percentage') {
    await fetchCurrentCapital();
  }
  
  calculatePercentageStake();
  validateStake();
  emitStakeChanged();
});

/**
 * Surveiller les changements de props du parent
 */
watch(() => props.modelValue, (newValue) => {
  if (newValue !== localStake.value) {
    localStake.value = newValue;
  }
});

watch(() => props.stakeType, (newValue) => {
  if (newValue !== localStakeType.value) {
    localStakeType.value = newValue;
  }
});

watch(() => props.globalOdds, (newValue) => {
  localGlobalOdds.value = newValue;
});

// ===== LIFECYCLE =====

/**
 * Initialisation du composant
 * Récupère le capital si en mode pourcentage
 */
onMounted(async () => {
  if (localStakeType.value === 'percentage') {
    await fetchCurrentCapital();
  }
  calculatePercentageStake();
});
</script>

<style scoped>
/**
 * Styles spécifiques au composant StakeField
 * Utilise principalement les classes Tailwind CSS
 */
.stake-field-container {
  /* Container principal du champ de mise */
}

/* Styles pour les états d'erreur */
.p-invalid {
  border-color: #ef4444 !important;
}

/* Styles pour l'affichage du gain potentiel */
.percentage-details {
  /* Styles pour l'affichage détaillé en mode pourcentage */
}

.currency-simple {
  /* Styles pour l'affichage simple en mode devise */
}

/* Animation pour le spinner de chargement */
.pi-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>