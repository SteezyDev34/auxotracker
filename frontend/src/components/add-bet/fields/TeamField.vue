<template>
  <div class="flex flex-col gap-2 mb-4">
    <AutoComplete 
      :ref="(el) => { if (el) teamRef = el }"
      :id="`team_${teamType}_${eventIndex}`" 
      v-model="selectedTeam" 
      :suggestions="teamSearchResults || []" 
      @complete="onSearchTeams"
      @item-select="onTeamSelect"
      @clear="onTeamClear"
      @dropdown-click="onDropdownClick"
      @click="onInputFocus"
      optionLabel="name"
      :placeholder="selectedTeam && selectedTeam.length > 0 ? '' : placeholder"
      class="w-full max-w-full select-custom"
      :class="{ 'p-invalid': hasError }"
      :loading="isLoading"
      panelClass="select-panel-custom"
      @show="onDropdownShow"
      :minLength="0"
      dropdown
      dropdownMode="blank"
      multiple
      display="chip"
      :forceSelection="false"
      :aria-label="`Rechercher et sélectionner ${placeholder.toLowerCase()}`"
      role="combobox"
      aria-expanded="false"
      aria-autocomplete="list"
    >
      <!-- Template pour afficher l'équipe sélectionnée avec son logo -->
      <template #chip="slotProps">
        <div class="flex items-center gap-2">
          <!-- Logo de l'équipe sélectionnée -->
          <img
            v-if="slotProps.value && slotProps.value.id"
            :src="`${apiBaseUrl}/storage/team_logos/${slotProps.value.id}.png`"
            :alt="slotProps.value.name"
            class="w-4 h-4 rounded object-cover flex-shrink-0"
            @error="$event.target.style.display='none'"
          />
          <!-- Nom de l'équipe sélectionnée -->
          <span>{{ slotProps.value ? slotProps.value.name : '' }}</span>
        </div>
      </template>
        
      <!-- Template pour les options du dropdown -->
      <template #option="slotProps">
        <div class="flex items-center gap-2 truncate max-w-full" :title="slotProps.option.name">
          <!-- Logo de l'équipe -->
          <img
            :src="`${apiBaseUrl}/storage/team_logos/${slotProps.option.id}.png`"
            :alt="slotProps.option.name"
            class="w-5 h-5 object-contain"
            @error="$event.target.style.display='none'"
          />

          <!-- Nom de l'équipe -->
          <span class="truncate">{{ slotProps.option.name }}</span>
        </div>
      </template>
    </AutoComplete>
    <small v-if="hasError && errorMessage" class="text-red-500 block mt-1">{{ errorMessage }}</small>
  </div>
</template>

<script>
import { ref, watch, computed, nextTick } from 'vue';
import AutoComplete from 'primevue/autocomplete';
import { SportService } from '@/service/SportService';
import { useToast } from 'primevue/usetoast';

export default {
  name: 'TeamField',
  components: {
    AutoComplete
  },
  props: {
    /**
     * Type d'équipe (team1 ou team2)
     */
    teamType: {
      type: String,
      required: true,
      validator: (value) => ['team1', 'team2'].includes(value)
    },
    /**
     * Index de l'événement dans le formulaire multi-événements
     */
    eventIndex: {
      type: Number,
      required: true
    },
    /**
     * ID du sport sélectionné
     */
    sportId: {
      type: [Number, String],
      default: null
    },
    /**
     * ID du pays sélectionné
     */
    countryId: {
      type: [Number, String],
      default: null
    },
    /**
     * ID de la ligue sélectionnée
     */
    leagueId: {
      type: [Number, String],
      default: null
    },
    /**
     * Équipe(s) sélectionnée(s)
     */
    modelValue: {
      type: Array,
      default: () => []
    },
    /**
     * ID de l'équipe à exclure des résultats (pour éviter team1 = team2)
     */
    excludedTeamId: {
      type: [Number, String],
      default: null
    },
    /**
     * Indique si le champ a une erreur
     */
    hasError: {
      type: Boolean,
      default: false
    },
    /**
     * Message d'erreur à afficher
     */
    errorMessage: {
      type: String,
      default: ''
    },
    /**
     * Texte d'aide pour le champ
     */
    placeholder: {
      type: String,
      default: 'Sélectionner une équipe'
    }
  },
  emits: ['update:modelValue', 'team-select', 'team-clear', 'search-refresh'],
  setup(props, { emit }) {
    // Variables réactives
    const selectedTeam = ref([]);
    const teamSearchResults = ref([]);
    const isLoading = ref(false);
    const currentPage = ref(1);
    const hasMore = ref(true);
    const searchQuery = ref('');
    const teamRef = ref(null);
    const toast = useToast();

    // URL de base de l'API
    const apiBaseUrl = computed(() => {
      return import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
    });

    /**
     * Rechercher des équipes par sport avec pagination et exclusion
     * @param {string} query - Terme de recherche
     * @param {number} page - Numéro de page
     * @param {boolean} append - Ajouter aux résultats existants ou remplacer
     */
    const searchTeams = async (query = '', page = 1, append = false) => {
      if (!props.sportId) {
        console.log(`searchTeams: Aucun sport sélectionné pour ${props.teamType}`);
        return;
      }

      try {
        isLoading.value = true;
        
        console.log(`Recherche équipes ${props.teamType}:`, {
          sport_id: props.sportId,
          search: query,
          page: page,
          country_id: props.countryId,
          league_id: props.leagueId
        });

        const response = await SportService.searchTeamsBySport(
          props.sportId,
          query,
          page,
          30,
          props.leagueId,
          props.countryId
        );

        // Filtrer pour exclure l'équipe opposée si elle est sélectionnée
        let filteredData = response.data;
        if (props.excludedTeamId) {
          filteredData = response.data.filter(team => team.id !== props.excludedTeamId);
          console.log(`Équipe opposée exclue des résultats ${props.teamType}:`, {
            originalCount: response.data.length,
            filteredCount: filteredData.length,
            excludedTeamId: props.excludedTeamId
          });
        }

        if (append && page > 1) {
          teamSearchResults.value = [...teamSearchResults.value, ...filteredData];
        } else {
          teamSearchResults.value = filteredData;
        }

        // Mettre à jour les informations de pagination
        currentPage.value = page;
        hasMore.value = response.current_page < response.last_page;

        console.log(`Équipes chargées pour ${props.teamType}:`, {
          count: filteredData.length,
          total: teamSearchResults.value.length,
          hasMore: hasMore.value,
          page: page
        });

      } catch (error) {
        console.error(`Erreur lors du chargement des équipes ${props.teamType}:`, error);
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: `Impossible de charger les équipes pour ${props.teamType}`,
          life: 3000
        });
      } finally {
        isLoading.value = false;
      }
    };

    // Synchroniser avec modelValue
    watch(() => props.modelValue, (newValue) => {
      selectedTeam.value = newValue || [];
    }, { immediate: true });

    // Watcher pour le sport - recharger les équipes quand le sport change
    watch(() => props.sportId, (newSportId) => {
      if (newSportId) {
        console.log(`Sport changé pour ${props.teamType}, rechargement des équipes`);
        searchTeams('', 1, false);
      } else {
        // Réinitialiser si aucun sport
        teamSearchResults.value = [];
        if (selectedTeam.value.length > 0) {
          selectedTeam.value = [];
          emit('update:modelValue', []);
          emit('team-clear');
        }
      }
    }, { immediate: true });

    // Watcher pour le pays - recharger les équipes quand le pays change
    watch(() => props.countryId, () => {
      if (props.sportId) {
        console.log(`Pays changé pour ${props.teamType}, rechargement des équipes`);
        searchTeams(searchQuery.value, 1, false);
      }
    });

    // Watcher pour la ligue - recharger les équipes quand la ligue change
    watch(() => props.leagueId, () => {
      if (props.sportId) {
        console.log(`Ligue changée pour ${props.teamType}, rechargement des équipes`);
        searchTeams(searchQuery.value, 1, false);
      }
    });

    // Watcher pour l'équipe exclue - recharger les équipes quand l'équipe opposée change
    watch(() => props.excludedTeamId, () => {
      if (props.sportId) {
        console.log(`Équipe exclue changée pour ${props.teamType}, rechargement des équipes`);
        searchTeams(searchQuery.value, 1, false);
      }
    });

    /**
     * Gérer la saisie de recherche utilisateur
     * @param {Object} event - Événement contenant la query
     */
    const onSearchTeams = (event) => {
      const query = event.query || '';
      searchQuery.value = query;
      console.log(`Recherche utilisateur ${props.teamType}:`, query);
      searchTeams(query, 1, false);
    };

    /**
     * Gérer l'ouverture du dropdown
     */
    const onDropdownShow = () => {
      console.log(`Dropdown ouvert pour ${props.teamType}`);
      if (teamSearchResults.value.length === 0 && props.sportId) {
        searchTeams('', 1, false);
      }
    };

    /**
     * Gérer la sélection d'une équipe
     * @param {Object} event - Événement de sélection
     */
    const onTeamSelect = (event) => {
      const team = event.value;
      console.log(`Équipe sélectionnée pour ${props.teamType}:`, team);
      
      selectedTeam.value = [team];
      emit('update:modelValue', [team]);
      emit('team-select', team);
      
      // Fermer le dropdown et retirer le focus
      closeDropdownAndBlur();
    };

    /**
     * Gérer l'effacement de la sélection
     */
    const onTeamClear = () => {
      console.log(`Équipe effacée pour ${props.teamType}`);
      
      selectedTeam.value = [];
      emit('update:modelValue', []);
      emit('team-clear');
      
      // Recharger les équipes sans filtre de recherche
      if (props.sportId) {
        searchTeams('', 1, false);
      }
    };

    /**
     * Gérer le clic sur le bouton dropdown
     */
    const onDropdownClick = async () => {
      console.log(`Clic dropdown pour ${props.teamType}`);
      
      // Charger les équipes si nécessaire
      if (teamSearchResults.value.length === 0 && props.sportId) {
        await searchTeams('', 1, false);
      }
      
      // Déclencher la recherche avec une chaîne vide pour afficher toutes les équipes
      onSearchTeams({ query: '' });
    };

    /**
     * Gérer le focus sur le champ de saisie
     */
    const onInputFocus = async () => {
      console.log(`Focus sur le champ de saisie équipe pour ${props.teamType}`);
      
      // Charger les équipes si nécessaire
      if (teamSearchResults.value.length === 0 && props.sportId) {
        await searchTeams('', 1, false);
      }
      
      // Déclencher la recherche avec une chaîne vide pour afficher toutes les équipes
      onSearchTeams({ query: '' });
      
      // Forcer l'ouverture du menu déroulant après un court délai
      nextTick(() => {
        if (teamRef.value && teamRef.value.show) {
          teamRef.value.show();
        }
      });
    };

    /**
     * Fermer le dropdown et retirer le focus
     */
    const closeDropdownAndBlur = async () => {
      await nextTick();
      if (teamRef.value) {
        // Fermer le dropdown
        if (teamRef.value.hide) {
          teamRef.value.hide();
        }
        
        // Retirer le focus du champ de saisie
        const inputElement = teamRef.value.$el?.querySelector('input') || teamRef.value.$el?.querySelector('.p-inputtext');
        if (inputElement) {
          inputElement.blur();
          console.log(`✅ Focus retiré du champ équipe ${props.teamType} après sélection`);
        }
      }
    };

    /**
     * Gérer le scroll pour la pagination
     * @param {Event} event - Événement de scroll
     */
    const handlePanelScroll = (event) => {
      const panel = event.target;
      if (panel.scrollTop + panel.clientHeight >= panel.scrollHeight - 5) {
        if (hasMore.value && !isLoading.value) {
          console.log(`Chargement page suivante pour ${props.teamType}`);
          searchTeams(searchQuery.value, currentPage.value + 1, true);
        }
      }
    };

    /**
     * Rafraîchir les résultats de recherche
     */
    const refreshResults = () => {
      console.log(`Rafraîchissement demandé pour ${props.teamType}`);
      emit('search-refresh');
      if (props.sportId) {
        searchTeams(searchQuery.value, 1, false);
      }
    };

    return {
      selectedTeam,
      teamSearchResults,
      isLoading,
      teamRef,
      hasMore,
      onSearchTeams,
      onDropdownShow,
      onTeamSelect,
      onTeamClear,
      onDropdownClick,
      onInputFocus,
      closeDropdownAndBlur,
      handlePanelScroll,
      refreshResults,
      apiBaseUrl
    };
  }
};
</script>
