<template>
  <div class="field">
    <AutoComplete
      :ref="
        (el) => {
          if (el) leagueRef = el;
        }
      "
      id="league"
      v-model="selectedLeague"
      :suggestions="leagueSearchResults"
      @complete="searchLeagues"
      @focus="onLeagueDropdownShow"
      @click="onLeagueDropdownShow"
      @item-select="onLeagueSelect"
      @clear="onLeagueClear"
      @dropdown-click="onDropdownClick"
      optionLabel="name"
      :placeholder="
        selectedLeague && selectedLeague.length > 0 ? '' : 'Ligue...'
      "
      :loading="loading"
      dropdown
      dropdownMode="blank"
      forceSelection
      multiple
      display="chip"
      class="w-full"
      :class="{ 'p-invalid': hasError }"
      :disabled="!sportId"
      aria-label="Rechercher et sélectionner une ligue"
      role="combobox"
      aria-expanded="false"
      aria-autocomplete="list"
    >
      <!-- Template pour afficher la ligue sélectionnée avec son logo -->
      <template #chip="slotProps">
        <div class="flex items-center gap-2">
          <!-- Logo de la ligue sélectionnée -->
          <img
            v-if="slotProps.value && slotProps.value.id"
            :src="`${apiBaseUrl}/storage/league_logos/${slotProps.value.id}${isDarkTheme ? '-dark' : ''}.png`"
            :alt="slotProps.value.name"
            class="w-4 h-4 rounded object-cover flex-shrink-0"
            @error="$event.target.style.display = 'none'"
          />
          <!-- Nom de la ligue sélectionnée -->
          <span>{{ slotProps.value ? slotProps.value.name : "" }}</span>
        </div>
      </template>

      <!-- Template pour les options du dropdown -->
      <template #option="slotProps">
        <div
          class="flex items-center gap-2 truncate max-w-full"
          :title="slotProps.option.name"
        >
          <img
            v-if="slotProps.option.id"
            :src="`${apiBaseUrl}/storage/country_flags/${slotProps.option.country.id}.png`"
            class="w-4 h-4 rounded object-cover flex-shrink-0"
          />
          <img
            v-if="slotProps.option.id"
            :src="`${apiBaseUrl}/storage/league_logos/${slotProps.option.id}${isDarkTheme ? '-dark' : ''}.png`"
            :alt="slotProps.option.name"
            class="w-4 h-4 rounded object-cover flex-shrink-0"
            @error="$event.target.style.display = 'none'"
          />
          <span>{{ slotProps.option.name }}</span>
        </div>
      </template>
    </AutoComplete>
    <small v-if="hasError" class="p-error">{{ errorMessage }}</small>
  </div>
</template>

<script>
import { ref, watch, computed, nextTick } from "vue";
import AutoComplete from "primevue/autocomplete";
import { SportService } from "@/service/SportService";
import { useToast } from "primevue/usetoast";
import { useLayout } from "@/layout/composables/layout";

export default {
  name: "LeagueField",
  components: {
    AutoComplete,
  },
  props: {
    /**
     * ID du sport sélectionné
     */
    sportId: {
      type: [Number, String],
      default: null,
    },
    /**
     * ID du pays sélectionné
     */
    countryId: {
      type: [Number, String],
      default: null,
    },
    /**
     * Valeur de la ligue sélectionnée
     */
    modelValue: {
      type: Array,
      default: () => [],
    },
    /**
     * Indique si le champ est en erreur
     */
    hasError: {
      type: Boolean,
      default: false,
    },
    /**
     * Message d'erreur à afficher
     */
    errorMessage: {
      type: String,
      default: "",
    },
  },
  emits: ["update:modelValue", "league-select", "league-clear"],
  setup(props, { emit }) {
    const toast = useToast();
    const { isDarkTheme } = useLayout();
    const apiBaseUrl =
      import.meta.env.VITE_API_BASE_URL || "http://localhost:8000";

    // État local
    const selectedLeague = ref([]);
    const leagueSearchResults = ref([]);
    const loading = ref(false);
    const leagueRef = ref(null);
    const dropdownOpeningInProgress = ref(false);

    // Watcher pour synchroniser avec modelValue
    watch(
      () => props.modelValue,
      (newVal) => {
        selectedLeague.value = newVal || [];
        // S'assurer que les ligues sélectionnées sont dans leagueSearchResults
        if (newVal && newVal.length > 0) {
          newVal.forEach((league) => {
            if (!leagueSearchResults.value.find((l) => l.id === league.id)) {
              leagueSearchResults.value.push(league);
            }
          });
        }
      },
      { deep: true, immediate: true }
    );

    /**
     * Rechercher des ligues par sport et pays
     * @param {Object} event - Événement de recherche contenant la query
     */
    const searchLeagues = async (event) => {
      if (!props.sportId) {
        console.log("❌ searchLeagues: Aucun sport sélectionné");
        return;
      }

      const query = event.query || "";

      try {
        loading.value = true;

        const response = await SportService.searchLeaguesBySport(
          props.sportId,
          query,
          1,
          30,
          props.countryId // Filtrer par pays si sélectionné
        );

        leagueSearchResults.value = response.data;

        console.log("✅ Ligues trouvées:", {
          sportId: props.sportId,
          countryId: props.countryId,
          query,
          count: response.data.length,
        });
      } catch (error) {
        console.error("❌ Erreur lors de la recherche des ligues:", error);
        toast.add({
          severity: "error",
          summary: "Erreur",
          detail: "Impossible de rechercher les ligues",
          life: 3000,
        });
      } finally {
        loading.value = false;
      }
    };

    /**
     * Gérer l'affichage du dropdown des ligues
     */
    const onLeagueDropdownShow = () => {
      // Vérifier si l'ouverture est déjà en cours
      if (dropdownOpeningInProgress.value) {
        return;
      }

      // Marquer l'ouverture comme en cours
      dropdownOpeningInProgress.value = true;

      console.log("🔽 Dropdown ligue ouvert");

      // Si aucun sport sélectionné, ne rien faire
      if (!props.sportId) {
        // Réinitialiser le drapeau après un court délai
        setTimeout(() => {
          dropdownOpeningInProgress.value = false;
        }, 300);
        return;
      }

      // Charger les ligues si nécessaire
      if (leagueSearchResults.value.length === 0) {
        searchLeagues({ query: "" });
      }

      // Forcer l'ouverture du dropdown
      nextTick(() => {
        if (leagueRef.value && typeof leagueRef.value.show === "function") {
          leagueRef.value.show();
          console.log("✅ Dropdown ligue forcé à s'ouvrir");
        }

        // Réinitialiser le drapeau
        setTimeout(() => {
          dropdownOpeningInProgress.value = false;
        }, 300);
      });
    };

    /**
     * Fermer le dropdown et retirer le focus
     */
    const closeDropdownAndBlur = () => {
      if (leagueRef.value) {
        // Fermer le dropdown
        if (leagueRef.value.hide) {
          leagueRef.value.hide();
        }

        // Retirer le focus du champ de saisie
        const inputElement =
          leagueRef.value.$el?.querySelector("input") ||
          leagueRef.value.$el?.querySelector(".p-inputtext");
        if (inputElement) {
          inputElement.blur();
          console.log("✅ Focus retiré du champ ligue après sélection");
        }
      }
    };

    /**
     * Gérer la sélection d'une ligue
     * @param {Object} event - Événement de sélection contenant la ligue
     */
    const onLeagueSelect = (event) => {
      if (event.value) {
        // Remplacer l'élément existant par la nouvelle ligue sélectionnée
        selectedLeague.value = [event.value];

        // Émettre la mise à jour du modèle
        emit("update:modelValue", selectedLeague.value);
        emit("league-select", event.value);

        console.log("✅ Ligue sélectionnée:", {
          id: event.value.id,
          name: event.value.name,
        });

        // Fermer le dropdown après sélection
        nextTick(() => {
          closeDropdownAndBlur();
        });
      } else {
        selectedLeague.value = [];
        emit("update:modelValue", selectedLeague.value);
        emit("league-select", null);
      }
    };

    /**
     * Gérer l'effacement de la ligue
     */
    const onLeagueClear = () => {
      selectedLeague.value = [];
      emit("update:modelValue", selectedLeague.value);
      emit("league-clear");

      console.log("🗑️ Ligue effacée");
    };

    /**
     * Gérer le clic sur le dropdown
     */
    const onDropdownClick = () => {
      if (!props.sportId) {
        toast.add({
          severity: "warn",
          summary: "Attention",
          detail: "Veuillez d'abord sélectionner un sport",
          life: 3000,
        });
        return;
      }

      // Charger les ligues si pas encore chargées
      if (leagueSearchResults.value.length === 0) {
        searchLeagues({ query: "" });
      }
    };

    // Watcher pour réinitialiser les ligues quand le sport ou le pays change
    watch(
      [() => props.sportId, () => props.countryId],
      ([newSportId, newCountryId], [oldSportId, oldCountryId]) => {
        if (newSportId !== oldSportId || newCountryId !== oldCountryId) {
          // Réinitialiser la sélection
          selectedLeague.value = [];
          leagueSearchResults.value = [];
          emit("update:modelValue", null);
          emit("league-clear");
        }
      }
    );

    // Watcher pour synchroniser la valeur externe avec la sélection locale
    watch(
      () => props.modelValue,
      (newValue) => {
        if (!newValue) {
          selectedLeague.value = [];
        }
      }
    );

    return {
      selectedLeague,
      leagueSearchResults,
      loading,
      leagueRef,
      searchLeagues,
      onLeagueDropdownShow,
      onLeagueSelect,
      onLeagueClear,
      onDropdownClick,
      closeDropdownAndBlur,
      apiBaseUrl,
      isDarkTheme,
    };
  },
};
</script>

<style scoped>
.field {
  margin-bottom: 1rem;
}

.p-autocomplete {
  width: 100%;
}

.p-invalid {
  border-color: #e24c4c;
}

.p-error {
  color: #e24c4c;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}
</style>
