<template>
  <div class="flex flex-col gap-2 mb-4">
    <!-- Sélecteur de type de pari -->
    <Select 
      :id="`bet_type_${eventIndex}`" 
      v-model="selectedBetType" 
      :options="filteredBetTypeOptions" 
      optionLabel="label" 
      optionValue="value"
      @click="onDropdownShow"
      placeholder="Sélectionner un type de pari"
      class="w-full select-custom"
      :class="{ 'p-invalid': error }"
      :disabled="!sportId"
      dropdown
      dropdownMode="blank"
      aria-label="Sélectionner un type de pari"
      role="combobox"
    />
    <!-- Message d'erreur -->
    <small v-if="error" class="text-red-500 block mt-1">{{ error }}</small>
  </div>
</template>

<script>
import Select from 'primevue/select';
import { useBetTypes } from '@/composables/useBetTypes';
import { computed, watch } from 'vue';

export default {
  name: 'TypePariField',
  components: {
    Select
  },
  props: {
    /**
     * Valeur du type de pari sélectionné
     */
    modelValue: {
      type: [String, Object],
      default: null
    },
    /**
     * Index de l'événement (pour l'ID unique)
     */
    eventIndex: {
      type: Number,
      required: true
    },
    /**
     * ID du sport sélectionné (pour filtrer les types de pari)
     */
    sportId: {
      type: [Number, String],
      default: null
    },
    /**
     * Slug du sport sélectionné (pour filtrer les types de pari)
     */
    sportSlug: {
      type: String,
      default: ''
    },
    /**
     * Liste des sports disponibles (pour résoudre le slug)
     */
    availableSports: {
      type: Array,
      default: () => []
    },
    /**
     * Message d'erreur à afficher
     */
    error: {
      type: String,
      default: ''
    }
  },
  emits: [
    /**
     * Émis quand la valeur change
     * @param {string|null} value - Nouvelle valeur du type de pari
     */
    'update:modelValue',
    /**
     * Émis quand un type de pari est sélectionné
     * @param {Object} betType - Type de pari sélectionné
     */
    'bet-type-select',
    /**
     * Émis quand le dropdown s'ouvre
     * @param {number} eventIndex - Index de l'événement
     */
    'dropdown-show'
  ],
  setup(props, { emit }) {
    // Composable pour la gestion des types de paris
    const { getBetTypesForSport, betTypeOptions: allBetTypeOptions } = useBetTypes();

    /**
     * Valeur interne du type de pari sélectionné
     */
    const selectedBetType = computed({
      get: () => props.modelValue,
      set: (value) => {
        emit('update:modelValue', value);
        
        // Émettre l'événement de sélection avec l'objet complet
        if (value) {
          const selectedOption = filteredBetTypeOptions.value.find(option => option.value === value);
          if (selectedOption) {
            emit('bet-type-select', selectedOption);
          }
        }
      }
    });

    /**
     * Options de types de pari filtrées selon le sport sélectionné
     */
    const filteredBetTypeOptions = computed(() => {
      // Si aucun sport n'est sélectionné, afficher tous les types de paris
      if (!props.sportId) {
        return allBetTypeOptions.value;
      }

      // Utiliser le slug fourni directement ou le résoudre depuis la liste des sports
      let sportSlug = props.sportSlug;
      
      if (!sportSlug && props.availableSports.length > 0) {
        // Trouver le sport sélectionné dans la liste des sports disponibles
        const selectedSport = props.availableSports.find(sport => sport.id === props.sportId);
        if (!selectedSport || !selectedSport.slug) {
          return allBetTypeOptions.value;
        }
        sportSlug = selectedSport.slug;
      }

      if (!sportSlug) {
        return allBetTypeOptions.value;
      }

      // Obtenir les types de paris pour ce sport spécifique
      const sportBetTypes = getBetTypesForSport(sportSlug);
      
      // Filtrer les options pour ne garder que celles disponibles pour ce sport
      return allBetTypeOptions.value.filter(option => 
        sportBetTypes.includes(option.value)
      );
    });

    /**
     * Gérer l'ouverture du dropdown des types de paris
     */
    const onDropdownShow = () => {
      console.log('🔽 Dropdown type de paris ouvert pour événement', props.eventIndex);
      emit('dropdown-show', props.eventIndex);
      // Pas de logique spéciale nécessaire, le Select gère automatiquement les options
    };

    // Watcher pour réinitialiser le type de pari quand le sport change
    watch(() => props.sportId, (newSportId, oldSportId) => {
      if (newSportId !== oldSportId && props.modelValue) {
        // Vérifier si le type de pari actuel est toujours valide pour le nouveau sport
        const isStillValid = filteredBetTypeOptions.value.some(option => option.value === props.modelValue);
        if (!isStillValid) {
          // Réinitialiser le type de pari si il n'est plus valide
          selectedBetType.value = null;
        }
      }
    });

    return {
      selectedBetType,
      filteredBetTypeOptions,
      onDropdownShow
    };
  }
};
</script>

<style scoped>
/**
 * Styles personnalisés pour le composant TypePariField
 * Hérite des styles globaux de l'application
 */
.select-custom {
  /* Styles personnalisés pour le sélecteur */
}

.p-invalid {
  /* Style d'erreur PrimeVue */
  border-color: #ef4444;
}

.text-red-500 {
  /* Couleur rouge pour les messages d'erreur */
  color: #ef4444;
}
</style>