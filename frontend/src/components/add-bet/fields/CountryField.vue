<template>
  <div class="flex flex-col gap-2">
    <AutoComplete 
      :ref="(el) => { if (el) countryRef = el }"
      :id="`country_${eventIndex}`" 
      v-model="selectedCountry" 
      :suggestions="countrySearchResults || []" 
      @complete="onSearchCountries"
      @focus="onCountryDropdownShow"
      @click="onCountryDropdownShow"
      @item-select="onCountrySelect"
      @clear="onCountryClear"
      @dropdown-click="onDropdownClick"
      optionLabel="name"
      :placeholder="selectedCountry && selectedCountry.length > 0 ? '' : 'Pays'"
      class="w-full max-w-full select-custom"
      :class="{ 'p-invalid': error }"
      :loading="isLoading"
      :disabled="!sportId"
      :minLength="0"
      dropdown
      dropdownMode="blank"
      multiple
      display="chip"
      aria-label="Rechercher et sélectionner un pays"
      role="combobox"
      aria-expanded="false"
      aria-autocomplete="list"
    >
      <!-- Template pour afficher le pays sélectionné avec son drapeau -->
      <template #chip="slotProps">
        <div class="flex items-center gap-2">
          <!-- Drapeau du pays sélectionné -->
          <img 
            v-if="slotProps.value && slotProps.value.id"
            :src="`${apiBaseUrl}/storage/country_flags/${slotProps.value.id}.png`" 
            :alt="slotProps.value.name"
            class="w-4 h-4 rounded object-cover flex-shrink-0" 
            @error="$event.target.style.display='none'"
          />
          <!-- Nom du pays sélectionné -->
          <span>{{ slotProps.value ? slotProps.value.name : '' }}</span>
        </div>
      </template>
      
      <!-- Template pour les options du dropdown -->
      <template #option="slotProps">
        <div class="flex items-center gap-2 truncate max-w-full" :title="slotProps.option.name">
          <!-- Drapeau du pays -->
          <img 
            v-if="slotProps.option.id"
            :src="`${apiBaseUrl}/storage/country_flags/${slotProps.option.id}.png`" 
            :alt="slotProps.option.name"
            class="w-4 h-4 rounded object-cover flex-shrink-0" 
            @error="$event.target.style.display='none'"
          />
          <!-- Nom du pays -->
          <span class="truncate">{{ slotProps.option.name }}</span>
        </div>
      </template>
    </AutoComplete>
    <small v-if="error" class="text-red-500 block mt-1">{{ error }}</small>
  </div>
</template>

<script>
import AutoComplete from 'primevue/autocomplete';
import { SportService } from '@/service/SportService';

export default {
  name: 'CountryField',
  components: {
    AutoComplete
  },
  props: {
    modelValue: {
      type: Object,
      default: () => null
    },
    eventIndex: {
      type: Number,
      required: true
    },
    sportId: {
      type: [Number, String],
      default: null
    },
    error: {
      type: String,
      default: ''
    }
  },
  emits: ['update:modelValue', 'country-select', 'country-change', 'country-clear'],
  setup() {
    const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
    
    return {
      apiBaseUrl
    };
  },
  data() {
    return {
      selectedCountry: this.modelValue,
      countrySearchResults: [],
      countriesData: [], // Stocke tous les pays chargés pour ce sport
      countryRef: null,
      isLoading: false,
      dropdownOpeningInProgress: false
    }
  },
  watch: {
    modelValue: {
      handler(newVal) {
        this.selectedCountry = newVal;
      },
      deep: true
    },
    sportId: {
      handler(newSportId) {
        if (newSportId) {
          this.loadCountriesBySport(newSportId);
        } else {
          this.resetCountryData();
        }
      },
      immediate: true
    }
  },
  methods: {
    /**
     * Charger les pays qui ont des ligues pour un sport spécifique
     * @param {number} sportId - ID du sport sélectionné
     */
    async loadCountriesBySport(sportId) {
      if (!sportId) return;
      
      try {
        this.isLoading = true;
        console.log('🔄 Chargement des pays pour le sport:', sportId);
        
        const countriesData = await SportService.getCountriesBySport(sportId);
        this.countriesData = countriesData;
        this.countrySearchResults = [...countriesData];
        
        console.log('✅ Pays chargés pour le sport', sportId, ':', countriesData.length, 'pays');
      } catch (error) {
        console.error('❌ Erreur lors du chargement des pays par sport:', error);
        this.countriesData = [];
        this.countrySearchResults = [];
      } finally {
        this.isLoading = false;
      }
    },

    /**
     * Rechercher des pays avec filtrage côté client
     * @param {Object} event - Événement de recherche
     */
    onSearchCountries(event) {
      const query = event.query || '';
      
      // Si aucun sport n'est sélectionné, ne pas afficher de pays
      if (!this.sportId) {
        this.countrySearchResults = [];
        return;
      }

      // Si aucun pays n'est chargé, charger les pays pour ce sport
      if (!this.countriesData || this.countriesData.length === 0) {
        this.loadCountriesBySport(this.sportId);
        return;
      }

      if (query.trim() === '') {
        // Afficher tous les pays disponibles pour ce sport
        this.countrySearchResults = [...this.countriesData];
      } else {
        // Filtrer les pays selon la requête
        this.countrySearchResults = this.countriesData.filter((country) => {
          return country.name.toLowerCase().includes(query.toLowerCase());
        });
      }
      
      console.log('🔍 Recherche pays avec query:', query, 'Résultats:', this.countrySearchResults.length);
    },

    /**
     * Gérer l'affichage du dropdown des pays
     */
    onCountryDropdownShow() {
      // Vérifier si l'ouverture est déjà en cours
      if (this.dropdownOpeningInProgress) {
        return;
      }
      
      // Marquer l'ouverture comme en cours
      this.dropdownOpeningInProgress = true;
      
      console.log('🔽 Dropdown pays ouvert pour événement', this.eventIndex);
      
      // Si aucun sport sélectionné, ne rien faire
      if (!this.sportId) {
        // Réinitialiser le drapeau après un court délai
        setTimeout(() => {
          this.dropdownOpeningInProgress = false;
        }, 300);
        return;
      }

      // Charger les pays si nécessaire
      if (!this.countriesData || this.countriesData.length === 0) {
        this.loadCountriesBySport(this.sportId);
      } else if (!this.countrySearchResults || this.countrySearchResults.length === 0) {
        this.countrySearchResults = [...this.countriesData];
      }

      // Forcer l'ouverture du dropdown
      this.$nextTick(() => {
        if (this.countryRef && typeof this.countryRef.show === 'function') {
          this.countryRef.show();
          console.log('✅ Dropdown pays forcé à s\'ouvrir');
        }
        
        // Réinitialiser le drapeau
        setTimeout(() => {
          this.dropdownOpeningInProgress = false;
        }, 300);
      });
    },

    /**
     * Gérer la sélection d'un pays
     * @param {Object} event - Événement de sélection contenant le pays
     */
    onCountrySelect(event) {
      if (event.value) {
        // Remplacer l'élément existant par le nouveau pays sélectionné
        this.selectedCountry = [event.value];
        
        // Émettre la mise à jour du modèle
        this.$emit('update:modelValue', this.selectedCountry);
        
        // Émettre l'événement de sélection pour le parent
        this.$emit('country-select', event, this.eventIndex);
        
        // Émettre l'événement de changement
        this.$emit('country-change', this.eventIndex);
        
        console.log('✅ Pays sélectionné pour événement', this.eventIndex, ':', event.value.name);
        
        // Fermer le dropdown après sélection
        this.$nextTick(() => {
          this.closeDropdownAndBlur();
        });
      } else {
        this.selectedCountry = [];
        this.$emit('update:modelValue', this.selectedCountry);
        this.$emit('country-change', this.eventIndex);
        console.log('✅ Pays désélectionné pour événement', this.eventIndex);
      }
    },

    /**
     * Gérer l'effacement du pays sélectionné
     */
    onCountryClear() {
      this.$emit('country-clear', this.eventIndex);
      // Réinitialiser la valeur sélectionnée
      this.selectedCountry = [];
      // Émettre la mise à jour du modèle
      this.$emit('update:modelValue', this.selectedCountry);
      console.log('🗑️ Pays effacé pour événement', this.eventIndex);
    },

    /**
     * Gérer le clic sur le bouton dropdown
     */
    async onDropdownClick() {
      console.log('🔽 Clic sur le bouton dropdown pays pour événement', this.eventIndex);
      
      // Si aucun sport sélectionné, ne rien faire
      if (!this.sportId) {
        return;
      }
      
      // Charger les pays si nécessaire
      if (!this.countriesData || this.countriesData.length === 0) {
        await this.loadCountriesBySport(this.sportId);
      }
      
      // Déclencher la recherche avec une chaîne vide pour afficher tous les pays
      this.onSearchCountries({ query: '' });
    },

    /**
     * Réinitialiser les données du pays
     */
    resetCountryData() {
      this.selectedCountry = [];
      this.countriesData = [];
      this.countrySearchResults = [];
      this.$emit('update:modelValue', this.selectedCountry);
    },

    /**
     * Fermer le dropdown et retirer le focus
     */
    closeDropdownAndBlur() {
      if (this.countryRef) {
        // Fermer le dropdown
        if (this.countryRef.hide) {
          this.countryRef.hide();
        }
        
        // Retirer le focus du champ de saisie
        const inputElement = this.countryRef.$el?.querySelector('input') || this.countryRef.$el?.querySelector('.p-inputtext');
        if (inputElement) {
          inputElement.blur();
          console.log('✅ Focus retiré du champ pays après sélection');
        }
      }
    }
  }
}
</script>