<template>
  <div class="flex flex-col gap-2 mb-4">
    <!-- DEBUG: Afficher l'état interne -->
    <div v-if="false" class="text-xs text-gray-500 bg-gray-100 p-2 rounded">
      selectedSport: {{ JSON.stringify(selectedSport) }} | suggestions:
      {{ sportSearchResults.length }}
    </div>
    <AutoComplete
      :ref="
        (el) => {
          if (el) sportRef = el;
        }
      "
      :id="`sport_${eventIndex}`"
      v-model="selectedSport"
      :suggestions="sportSearchResults || []"
      @complete="onSearchSports"
      @item-select="onSportSelect"
      @clear="onSportClear"
      @dropdown-click="onDropdownClick"
      @click="onInputFocus"
      optionLabel="name"
      :placeholder="selectedSport && selectedSport.length > 0 ? '' : 'Sport'"
      class="w-full max-w-full select-custom"
      :class="{ 'p-invalid': error }"
      :loading="isLoading"
      panelClass="select-panel-custom"
      @show="onDropdownShow"
      :minLength="0"
      dropdown
      dropdownMode="blank"
      multiple
      display="chip"
      :forceSelection="false"
      aria-label="Rechercher et sélectionner un sport"
      role="combobox"
      aria-expanded="false"
      aria-autocomplete="list"
    >
      <!-- Template pour afficher le sport sélectionné avec son icône -->
      <template #chip="slotProps">
        <div class="flex items-center gap-2">
          <!-- Icône du sport sélectionné -->
          <img
            v-if="slotProps.value && slotProps.value.slug"
            :src="`${apiBaseUrl}/storage/sport_icons/${slotProps.value.slug}${isDarkTheme ? '-dark' : ''}.svg`"
            :alt="slotProps.value.name"
            class="w-4 h-4 rounded object-cover flex-shrink-0"
            @error="$event.target.style.display = 'none'"
          />
          <!-- Nom du sport sélectionné -->
          <span>{{ slotProps.value ? slotProps.value.name : "" }}</span>
        </div>
      </template>

      <!-- Template pour les options du dropdown -->
      <template #option="slotProps">
        <div
          class="flex items-center gap-2 truncate max-w-full"
          :title="slotProps.option.name"
        >
          <!-- Icône du sport -->
          <img
            v-if="slotProps.option.img"
            :src="`${apiBaseUrl}/storage/sport_icons/${slotProps.option.slug}${isDarkTheme ? '-dark' : ''}.svg`"
            :alt="slotProps.option.name"
            class="w-5 h-5 object-contain"
            @error="$event.target.style.display = 'none'"
          />
          <div
            v-else
            class="w-5 h-5 bg-gray-300 rounded-full flex items-center justify-center text-xs text-gray-600 flex-shrink-0"
          >
            {{
              slotProps.option.name
                ? slotProps.option.name.charAt(0).toUpperCase()
                : "?"
            }}
          </div>
          <!-- Nom du sport -->
          <span class="truncate">{{ slotProps.option.name }}</span>
        </div>
      </template>
    </AutoComplete>
    <small v-if="error" class="text-red-500 block mt-1">{{ error }}</small>
  </div>
</template>

<script>
import AutoComplete from "primevue/autocomplete";
import { SportService } from "@/service/SportService";
import { useLayout } from "@/layout/composables/layout";

export default {
  name: "SportField",
  components: {
    AutoComplete,
  },
  props: {
    modelValue: {
      type: Object,
      default: () => null,
    },
    eventIndex: {
      type: Number,
      required: true,
    },
    error: {
      type: String,
      default: "",
    },
  },
  emits: ["update:modelValue", "sport-select", "sport-clear"],
  setup() {
    const { isDarkTheme } = useLayout();
    const apiBaseUrl =
      import.meta.env.VITE_API_BASE_URL || "http://localhost:8000";

    return {
      isDarkTheme,
      apiBaseUrl,
    };
  },
  data() {
    return {
      selectedSport: [],
      sportSearchResults: [],
      sportsData: [], // Stocke tous les sports chargés
      sportRef: null,
      isLoading: false, // État de chargement interne
    };
  },
  mounted() {
    // Charger les sports au démarrage sans ouvrir le dropdown
    this.$nextTick(() => {
      this.loadSports();
    });
  },
  watch: {
    modelValue: {
      handler(newVal) {
        console.log(
          `🎯 [SportField eventIndex=${this.eventIndex}] modelValue watcher déclenché:`,
          JSON.stringify(newVal),
          "Type:",
          typeof newVal,
          "Array.isArray:",
          Array.isArray(newVal),
          "Length:",
          newVal?.length
        );
        
        // Protection contre null/undefined - AutoComplete multiple attend un tableau
        this.selectedSport = Array.isArray(newVal) ? [...newVal] : [];
        
        console.log(
          `📝 [SportField eventIndex=${this.eventIndex}] selectedSport assigné:`,
          JSON.stringify(this.selectedSport)
        );
        
        // S'assurer que les sports sélectionnés sont dans sportSearchResults
        if (newVal && newVal.length > 0) {
          console.log(`📋 [SportField eventIndex=${this.eventIndex}] Ajout de ${newVal.length} sport(s) à sportSearchResults`);
          newVal.forEach((sport) => {
            console.log(`  - Sport:`, sport?.name, "ID:", sport?.id);
            if (!this.sportSearchResults.find((s) => s.id === sport.id)) {
              this.sportSearchResults.push(sport);
              console.log(`    ✅ Ajouté à sportSearchResults`);
            } else {
              console.log(`    ℹ️ Déjà présent dans sportSearchResults`);
            }
          });
        } else {
          console.log(`⚠️ [SportField eventIndex=${this.eventIndex}] newVal est vide ou null`);
        }
        
        console.log(
          `📊 [SportField eventIndex=${this.eventIndex}] sportSearchResults après update:`,
          this.sportSearchResults.length,
          "items"
        );
      },
      deep: true,
      immediate: true,
    },
  },
  methods: {
    // Charger directement les sports depuis le service
    async loadSports() {
      try {
        this.isLoading = true;
        const sportsData = await SportService.getSports();
        this.sportsData = sportsData;
        this.sportSearchResults = [...sportsData]; // Initialiser avec tous les sports
        console.log(
          "✅ Sports chargés directement dans SportField:",
          sportsData.length
        );
      } catch (error) {
        console.error(
          "❌ Erreur lors du chargement des sports dans SportField:",
          error
        );
      } finally {
        this.isLoading = false;
      }
    },
    onSearchSports(event) {
      const query = event.query || "";

      // Si aucun sport n'est chargé, charger directement les sports
      if (!this.sportsData || this.sportsData.length === 0) {
        this.loadSports();
        return;
      }

      // Filtrer les sports selon la requête
      if (query.trim() === "") {
        // Afficher tous les sports si la requête est vide
        this.sportSearchResults = [...this.sportsData];
      } else {
        // Filtrer les sports selon la requête
        this.sportSearchResults = this.sportsData.filter((sport) =>
          sport.name.toLowerCase().includes(query.toLowerCase())
        );
      }

      console.log(
        "🔍 Recherche sports avec query:",
        query,
        "Résultats:",
        this.sportSearchResults.length
      );
    },
    onSportSelect(event) {
      this.$emit("sport-select", event, this.eventIndex);
      // Mettre à jour la valeur sélectionnée (en mode multiple, toujours un tableau)
      this.selectedSport = event.value ? [event.value] : [];
      // Émettre la mise à jour du modèle
      this.$emit("update:modelValue", this.selectedSport);

      // Fermer le dropdown et retirer le focus après sélection
      this.$nextTick(() => {
        this.closeDropdownAndBlur();
      });
    },
    onSportClear() {
      this.$emit("sport-clear", this.eventIndex);
      // Réinitialiser la valeur sélectionnée
      this.selectedSport = [];
      // Émettre la mise à jour du modèle
      this.$emit("update:modelValue", this.selectedSport);
    },
    // Gestion du clic sur le bouton dropdown
    async onDropdownClick() {
      console.log(
        "🔽 Clic sur le bouton dropdown pour événement",
        this.eventIndex
      );

      // Charger les sports si nécessaire
      if (!this.sportsData || this.sportsData.length === 0) {
        await this.loadSports();
      }

      // Déclencher la recherche avec une chaîne vide pour afficher tous les sports
      this.onSearchSports({ query: "" });
    },

    // Gestion de l'ouverture du dropdown (événement @show)
    onDropdownShow() {
      console.log(
        "📋 Menu déroulant des sports affiché pour événement",
        this.eventIndex
      );

      // Charger les sports si nécessaire
      if (!this.sportSearchResults || this.sportSearchResults.length === 0) {
        this.loadSports();
      }
    },

    // Gestion du focus sur le champ de saisie
    async onInputFocus() {
      console.log(
        "🎯 Focus sur le champ de saisie sport pour événement",
        this.eventIndex
      );

      // Charger les sports si nécessaire
      if (!this.sportsData || this.sportsData.length === 0) {
        await this.loadSports();
      }

      // Déclencher la recherche avec une chaîne vide pour afficher tous les sports
      this.onSearchSports({ query: "" });

      // Forcer l'ouverture du menu déroulant après un court délai
      this.$nextTick(() => {
        if (this.sportRef && this.sportRef.show) {
          this.sportRef.show();
        }
      });
    },

    updateSearchResults(results) {
      this.sportSearchResults = results;
    },
    showDropdown() {
      if (this.sportRef) {
        this.sportRef.show();
      }
    },

    // Fermer le dropdown et retirer le focus
    closeDropdownAndBlur() {
      if (this.sportRef) {
        // Fermer le dropdown
        if (this.sportRef.hide) {
          this.sportRef.hide();
        }

        // Retirer le focus du champ de saisie
        const inputElement =
          this.sportRef.$el?.querySelector("input") ||
          this.sportRef.$el?.querySelector(".p-inputtext");
        if (inputElement) {
          inputElement.blur();
          console.log("✅ Focus retiré du champ sport après sélection");
        }
      }
    },
  },
};
</script>
