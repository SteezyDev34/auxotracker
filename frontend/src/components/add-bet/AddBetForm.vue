<template>
  <div class="container mx-auto p-6 max-w-4xl">
    <h1 class="text-2xl font-bold mb-6">
      {{ formTitle }}
    </h1>

    <form @submit.prevent="submitForm" class="space-y-4 mb-4">
      <!-- Date du pari -->
      <DatePickerField
        v-model="formData.bet_date"
        fieldId="bet_date"
        placeholder="Date du pari"
        :required="true"
        dateFormat="dd/mm/yy"
        :showIcon="true"
        fieldClass="w-full"
        :error="!!errors.bet_date"
        :errorMessage="errors.bet_date"
      />

      <!-- Cards Événements -->
      <div
        v-for="(eventData, eventIndex) in eventCards"
        :key="eventData.id"
        class="border-surface-200 dark:border-surface-600 border rounded-lg p-4 mb-4"
      >
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold">Événement {{ eventIndex + 1 }}</h3>
          <Button
            v-if="eventCards.length > 1"
            icon="pi pi-times"
            class="p-button-text p-button-sm text-red-500"
            @click="removeEventCard(eventIndex)"
            aria-label="Supprimer cet événement"
          />
        </div>

        <!-- Sport -->
        <SportField
          :event-index="eventIndex"
          v-model="eventData.selectedSport"
          :error="errors[`sport_id_${eventIndex}`]"
          @sport-select="onSportSelect"
          @sport-clear="onSportClear"
          :ref="
            (el) => {
              if (el) sportAutoCompleteRefs[eventIndex] = el;
            }
          "
        />

        <!-- Champs conditionnels selon le sport (direct ou inféré de la ligue) -->
        <div
          v-if="getEffectiveSportId(eventData)"
          class="space-y-4 mb-4"
        >
          <!-- Pays -->
          <CountryField
            v-model="eventData.selectedCountry"
            :event-index="eventIndex"
            :sport-id="eventData.sport_id"
            :error="errors.country_id"
            @country-select="onCountrySelect"
            @country-change="onCountryChange"
          />

          <!-- Ligue -->
          <LeagueField
            v-model="eventData.selectedLeague"
            :sport-id="eventData.sport_id"
            :country-id="eventData.country_id"
            :has-error="!!errors[`league-${eventIndex}`]"
            :error-message="errors[`league-${eventIndex}`]"
            @league-select="
              (league) => onLeagueSelect({ value: league }, eventIndex)
            "
            @league-clear="() => onLeagueClear(eventIndex)"
          />

          <!-- Équipes -->
          <div class="space-y-4">
            <!-- Équipe 1 -->
            <TeamField
              team-type="team1"
              :event-index="eventIndex"
              :sport-id="eventData.sport_id"
              :country-id="eventData.country_id"
              :league-id="eventData.league"
              v-model="eventData.selectedTeam1"
              :excluded-team-id="eventData.selectedTeam2?.[0]?.id"
              :has-error="!!errors[`team1-${eventIndex}`]"
              :error-message="errors[`team1-${eventIndex}`]"
              placeholder="Équipe 1"
              @team-select="(team) => onTeamSelect(team, 'team1', eventIndex)"
              @team-clear="() => onTeamClear('team1', eventIndex)"
              @search-refresh="() => {}"
            />

            <!-- Équipe 2 -->
            <TeamField
              team-type="team2"
              :event-index="eventIndex"
              :sport-id="eventData.sport_id"
              :country-id="eventData.country_id"
              :league-id="eventData.league"
              v-model="eventData.selectedTeam2"
              :excluded-team-id="eventData.selectedTeam1?.[0]?.id"
              :has-error="!!errors[`team2-${eventIndex}`]"
              :error-message="errors[`team2-${eventIndex}`]"
              placeholder="Équipe 2"
              @team-select="(team) => onTeamSelect(team, 'team2', eventIndex)"
              @team-clear="() => onTeamClear('team2', eventIndex)"
              @search-refresh="() => {}"
            />
          </div>
          <!-- Type de pari -->
          <TypePariField
            v-model="eventData.bet_type"
            :event-index="eventIndex"
            :sport-id="eventData.sport_id"
            :available-sports="availableSports"
            :error="errors[`bet_type-${eventIndex}`]"
            @bet-type-select="(betType) => onBetTypeSelect(betType, eventIndex)"
            @dropdown-show="(index) => onBetTypeDropdownShow(index)"
          />
        </div>

        <!-- Description de l'événement -->
        <div class="flex flex-col gap-2 mb-4">
          <InputText
            :id="`event_description_${eventIndex}`"
            v-model="eventData.description"
            placeholder="Description de l'événement *"
            class="w-full"
            :class="{ 'p-invalid': errors[`event_description-${eventIndex}`] }"
          />
          <small
            v-if="errors[`event_description-${eventIndex}`]"
            class="text-red-500 block mt-1"
            >{{ errors[`event_description-${eventIndex}`] }}</small
          >
        </div>

        <!-- Champs spécifiques à l'événement pour les paris combinés -->
        <div v-if="eventCards.length > 1" class="space-y-4">
          <!-- Résultat de l'événement -->
          <div class="flex flex-col gap-2">
            <Select
              :id="`event_result_${eventIndex}`"
              v-model="eventData.result"
              :options="resultOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Résultat de l'événement *"
              class="w-full select-custom"
              :class="{ 'p-invalid': errors[`event_result-${eventIndex}`] }"
              panelClass="select-panel-custom"
              aria-label="Sélectionner le résultat de l'événement"
            />
            <small
              v-if="errors[`event_result-${eventIndex}`]"
              class="text-red-500 block mt-1"
              >{{ errors[`event_result-${eventIndex}`] }}</small
            >
          </div>

          <!-- Cote de l'événement -->
          <EventOddsField
            v-model="eventData.odds"
            :event-index="eventIndex"
            :error="errors[`event_odds-${eventIndex}`]"
            @odds-changed="onEventOddsChanged"
            @error="(message) => handleEventOddsError(eventIndex, message)"
            @valid="(isValid) => handleEventOddsValid(eventIndex, isValid)"
          />
        </div>
      </div>
      <!-- Bouton Ajouter un pari combiné -->
      <div class="flex justify-center mt-4 mb-4">
        <Button
          type="button"
          label="Ajouter un pari combiné"
          icon="pi pi-plus"
          class="p-button-outlined p-button-sm"
          @click="addEventCard"
        />
      </div>
    </form>
    <!-- Liste des événements ajoutés -->
    <div v-if="events.length > 0" class="border rounded-lg p-4 bg-blue-50">
      <h3 class="text-lg font-semibold mb-4 text-blue-800">
        Événements du pari combiné ({{ events.length }})
      </h3>

      <div class="space-y-3">
        <div
          v-for="(event, index) in events"
          :key="event.id"
          class="bg-white p-3 rounded border"
        >
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="text-sm font-medium text-gray-800 mb-1">
                Événement {{ index + 1 }}
              </div>
              <div class="text-sm text-gray-600 mb-2">
                {{ event.team1?.name }} vs {{ event.team2?.name }}
              </div>
              <div class="text-xs text-gray-500 mb-1">
                {{ event.league?.name }}
              </div>
              <div class="text-sm text-gray-700 mb-1">
                {{ event.description }}
              </div>
              <div class="flex gap-4 text-xs">
                <span v-if="event.odds" class="text-green-600 font-medium">
                  Cote: {{ event.odds }}
                </span>
                <span
                  v-if="event.result"
                  class="font-medium"
                  :class="getResultClass(event.result)"
                >
                  Résultat: {{ getResultLabel(event.result) }}
                </span>
              </div>
            </div>
            <Button
              icon="pi pi-times"
              class="p-button-text p-button-sm text-red-500"
              @click="removeEvent(index)"
              aria-label="Supprimer cet événement"
            />
          </div>
        </div>
      </div>
    </div>
    <!-- Cote, Mise et Type -->
    <div class="grid grid-cols-2 sm:grid-cols-2 gap-1 overflow-hidden">
      <!-- Cote -->
      <GlobalOddsField
        v-model="formData.global_odds"
        :stake="
          calculatedStake > 0
            ? calculatedStake
            : parseFloat(formData.stake) || 0
        "
        :show-potential-win="false"
        :error="errors.global_odds"
        @error="(message) => handleGlobalOddsError(message)"
        @valid="(isValid) => handleGlobalOddsValid(isValid)"
      />

      <!-- Champ de mise avec composant dédié -->
      <StakeField
        v-model="formData.stake"
        v-model:stake-type="betTypeValue"
        :global-odds="formData.global_odds"
        :show-potential-win="false"
        :error="errors.stake"
        field-id="stake"
        @stake-changed="onStakeChanged"
        @error="handleStakeError"
        @valid="handleStakeValid"
      />
    </div>

    <!-- Affichage du gain potentiel en pleine largeur -->
    <PotentialWinDisplay
      :stake-value="formData.stake"
      :stake-type="betTypeValue"
      :global-odds="formData.global_odds"
      :current-capital="currentCapital"
      :calculated-stake="calculatedStake"
      :show-potential-win="true"
    />
    <!-- Résultat (optionnel) -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-2 mt-3">
      <div class="flex-1">
        <Select
          id="result"
          v-model="formData.result"
          :options="resultOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Sélectionner un résultat"
          class="w-full"
        />
      </div>
    </div>
    <div class="flex justify-end gap-2 mt-4">
      <Button
        label="Annuler"
        icon="pi pi-times"
        @click="closeDialog"
        class="p-button-text"
      />
      <Button
        :label="props.editingBet ? 'Modifier le pari' : 'Ajouter le pari'"
        icon="pi pi-check"
        @click="submitForm"
        :loading="loading"
        :disabled="!isFormValid"
      />
    </div>
  </div>

  <!-- Composant utilitaire pour le calcul automatique de la cote globale -->
  <OddsCalculator
    :event-cards="eventCards"
    :global-odds="formData.global_odds"
    :auto-calculate="true"
    @global-odds-calculated="onGlobalOddsCalculated"
    @calculation-cleared="onGlobalOddsCleared"
    @calculation-failed="onGlobalOddsCalculationFailed"
  />
</template>
<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from "vue";
// Dialog import supprimé car ce n'est plus un Dialog
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Select from "primevue/select";
import SelectButton from "primevue/selectbutton";
import AutoComplete from "primevue/autocomplete";
import SportField from "./fields/SportField.vue";
import CountryField from "./fields/CountryField.vue";
import LeagueField from "./fields/LeagueField.vue";
import TeamField from "./fields/TeamField.vue";
import TypePariField from "./fields/TypePariField.vue";
import GlobalOddsField from "./fields/GlobalOddsField.vue";
import EventOddsField from "./fields/EventOddsField.vue";
import OddsCalculator from "./fields/OddsCalculator.vue";
import DatePickerField from "@/components/DatePickerField.vue";
import StakeField from "./fields/StakeField.vue";
import PotentialWinDisplay from "./fields/PotentialWinDisplay.vue";
import { SportService } from "@/service/SportService";
import { CountryService } from "@/service/CountryService";
import { BetService } from "@/service/BetService";
import { useToast } from "primevue/usetoast";
import { useLayout } from "@/layout/composables/layout";
import { useBetResults } from "@/composables/useBetResults";

// Props
const props = defineProps({
  editingBet: {
    type: Object,
    default: null,
  },
});

// Emits
const emit = defineEmits(["bet-created", "closeDialog"]);
// Composables
const toast = useToast();
const { isDarkTheme: layoutDarkTheme } = useLayout(); // Indique si le thème sombre est actif
const { resultOptions, resultValues, getResultLabel, getResultClass } =
  useBetResults(); // Options de résultats globales

// Computed local pour s'assurer de la réactivité
const isDarkTheme = computed(() => layoutDarkTheme.value);
// Variables réactives
const loading = ref(false);
const availableSports = ref([]); // Liste des sports disponibles
const sportsLoading = ref(false); // État de chargement des sports
const countries = ref([]);
const allCountries = ref([]);
const errors = ref({});
// Cache pour les pays par sport

const eventOddsInput = ref(null);
const availableTeams = ref([]);
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || "http://localhost:8000";
// Variables pour la recherche de pays
const countrySearchQuery = ref("");
const countrySearchResults = ref([]);
const countryLoading = ref(false);
const countryCurrentPage = ref(1);
const countryHasMore = ref(false);
const selectedCountry = ref([]);

// Variables pour la recherche de sports
const sportSearchQuery = ref("");
const sportSearchResults = ref([]);
const sportLoading = ref(false);
const selectedSport = ref([]);

// Références pour les composants AutoComplete
const sportAutoCompleteRefs = ref({});

// Variables pour le type de mise (maintenant gérées par StakeField)
const betTypeValue = ref("currency");

// Variables pour l'affichage du gain potentiel
const currentCapital = ref(0);
const calculatedStake = ref(0);

// Variables pour les cards d'événements multiples
const eventCards = ref([
  {
    id: 1,
    sport_id: null,
    country_id: null,
    league: null,
    team1: null,
    team2: null,
    bet_type: null,
    description: "",
    result: null,
    odds: null,
    selectedSport: [],
    selectedCountry: [],
    selectedLeague: [],
    selectedTeam1: [],
    selectedTeam2: [],
    sportSearchResults: [],
    sportLoading: false,
  },
]);

// Variables pour les événements multiples (paris combinés)
const events = ref([]);
const currentEvent = ref({
  sport_id: null,
  country_id: null,
  league: null,
  team1: null,
  team2: null,
  bet_type: null,
  bet_code: "",
  description: "",
  result: null,
  odds: null,
});

// Données du formulaire
const formData = ref({
  bet_date: new Date(),
  sport_id: null,
  country_id: null,
  league: null,
  team1: null,
  team2: null,
  bet_type: null,
  global_odds: null,
  stake: null,
  result: resultValues.PENDING,
});

// Options pour le résultat maintenant fournies par le composable useBetResults

// Computed
const formTitle = computed(() => {
  if (props.editingBet) {
    return "Modifier le pari";
  }
  return events.value.length > 1
    ? "Ajouter un pari combiné"
    : "Ajouter un pari simple";
});

// Afficher les champs sport conditionnels
const showSportFields = computed(() => {
  return formData.value.sport_id !== null;
});

/**
 * Gérer l'affichage du dropdown des types de paris
 * @param {number} eventIndex - Index de l'événement
 */
function onBetTypeDropdownShow(eventIndex) {
  console.log("🔽 Dropdown type de paris ouvert pour événement", eventIndex);
  // Pas de logique spéciale nécessaire, le Select gère automatiquement les options
}

/**
 * Gérer la sélection d'un type de pari
 * @param {Object} betType - Type de pari sélectionné
 * @param {number} eventIndex - Index de l'événement
 */
function onBetTypeSelect(betType, eventIndex) {
  console.log(
    "✅ Type de pari sélectionné pour événement",
    eventIndex,
    ":",
    betType
  );
  // Logique additionnelle si nécessaire (validation, calculs, etc.)
}

const isFormValid = computed(() => {
  // Seuls les champs essentiels sont obligatoires
  return (
    formData.value.bet_date &&
    formData.value.global_odds &&
    formData.value.stake
  );
});

// Méthodes

/**
 * Charger la liste des pays disponibles
 */
async function loadCountries() {
  try {
    const countryData = await CountryService.getCountries();
    // Utiliser les vrais IDs des pays depuis l'API
    const formattedCountries = countryData.map((country) => ({
      id: country.id, // Utiliser le vrai ID du pays
      name: country.name,
      code: country.code,
    }));

    countries.value = formattedCountries;
    allCountries.value = formattedCountries;
  } catch (error) {
    console.error("Erreur lors du chargement des pays:", error);
    toast.add({
      severity: "error",
      summary: "Erreur",
      detail: "Impossible de charger les pays",
      life: 3000,
    });
    countries.value = [];
    allCountries.value = [];
  }
}

/**
 * Charger les équipes d'un sport spécifique
 */
async function loadTeamsBySport(sportId) {
  try {
    availableTeams.value = await SportService.getTeamsBySport(sportId);
  } catch (error) {
    console.error("Erreur lors du chargement des équipes par sport:", error);
    toast.add({
      severity: "error",
      summary: "Erreur",
      detail: "Impossible de charger les équipes",
      life: 3000,
    });
    availableTeams.value = [];
  }
}

/**
 * Charger les équipes d'une ligue spécifique
 */
async function loadTeamsByLeague(leagueId) {
  try {
    availableTeams.value = await SportService.getTeamsByLeague(leagueId);
  } catch (error) {
    console.error("Erreur lors du chargement des équipes par ligue:", error);
    toast.add({
      severity: "error",
      summary: "Erreur",
      detail: "Impossible de charger les équipes de la ligue",
      life: 3000,
    });
    availableTeams.value = [];
  }
}

/**
 * Charger tous les sports disponibles
 */
async function loadSports() {
  try {
    sportsLoading.value = true;

    const sportsData = await SportService.getSports();
    availableSports.value = sportsData;
  } catch (error) {
    console.error("❌ Erreur lors du chargement des sports:", error);
    toast.add({
      severity: "error",
      summary: "Erreur",
      detail: "Impossible de charger les sports",
      life: 3000,
    });
    availableSports.value = [];
  } finally {
    sportsLoading.value = false;
  }
}

// Drapeaux pour éviter les appels multiples sur les dropdowns

/**
 * Gérer le cas où le champ sport est vidé
 * @param {number} eventIndex - Index de l'événement
 */
function onSportClear(eventIndex) {
  const timestamp = new Date().toISOString();
  console.log(
    `🧹 [${timestamp}] Champ sport vidé pour événement ${eventIndex}`
  );

  const eventData = eventCards.value[eventIndex];

  // Réinitialiser les données liées au sport
  eventData.sport_id = null;
  eventData.selectedSport = [];

  // Réinitialiser les champs liés au sport
  eventData.country_id = null;
  eventData.selectedCountry = [];
  eventData.league = null;
  eventData.team1 = null;
  eventData.selectedTeam1 = [];
  eventData.team2 = null;
  eventData.selectedTeam2 = [];

  // Réinitialiser les résultats de recherche
  eventData.countryFilteredResults = [];

  console.log(
    `✅ [${timestamp}] Tous les champs liés au sport ont été réinitialisés`
  );
}

/**
 * Gérer la sélection d'un sport
 * @param {Object} event - Événement de sélection
 * @param {number} eventIndex - Index de l'événement
 */
async function onSportSelect(event, eventIndex) {
  const eventData = eventCards.value[eventIndex];

  // Gérer la sélection d'un sport unique (en mode multiple mais limité à 1)
  if (event.value) {
    // En mode multiple, on garde un tableau d'un seul élément
    eventData.selectedSport = [event.value];
    eventData.sport_id = event.value.id;
    console.log(
      "✅ Sport sélectionné pour événement",
      eventIndex,
      ":",
      event.value.name
    );
  } else {
    eventData.selectedSport = [];
    eventData.sport_id = null;
    console.log("✅ Sport désélectionné pour événement", eventIndex);
  }

  // Réinitialiser les champs liés au sport pour cette card
  eventData.country_id = null;
  eventData.league = null;
  eventData.selectedLeague = []; // Synchroniser avec le tableau pour le v-model
  eventData.team1 = null;
  eventData.team2 = null;

  // Réinitialiser la recherche de pays pour cette card
  eventData.selectedCountry = [];

  // Réinitialiser la sélection d'équipes pour cette card
  eventData.selectedTeam1 = [];
  eventData.selectedTeam2 = [];
}

/**
 * Gérer la sélection d'un pays depuis CountryField
 * @param {Object} event - Événement de sélection
 * @param {number} eventIndex - Index de l'événement
 */
function onCountrySelect(event, eventIndex) {
  const eventData = eventCards.value[eventIndex];

  // Mettre à jour les données de l'événement
  if (event.value) {
    eventData.country_id = event.value.id;
    console.log("🔄 Pays sélectionné:", event.value.name);
  } else {
    eventData.country_id = null;
  }
}

/**
 * Gérer le changement de pays
 * @param {number} eventIndex - Index de l'événement
 */
async function onCountryChange(eventIndex) {
  const eventData = eventCards.value[eventIndex];

  // Réinitialiser les champs liés aux ligues et équipes pour cette card
  eventData.league = null;
  eventData.selectedLeague = []; // Synchroniser avec le tableau pour le v-model
  eventData.team1 = null;
  eventData.team2 = null;

  // Réinitialiser la sélection d'équipes pour cette card
  eventData.selectedTeam1 = [];
  eventData.selectedTeam2 = [];

  // Les composants LeagueField et TeamField se mettront à jour automatiquement
  // grâce aux watchers sur sportId et countryId
}

/**
 * Gérer la sélection d'une ligue
 * @param {Object} event - Événement de sélection contenant la ligue
 * @param {number} eventIndex - Index de l'événement
 */
async function onLeagueSelect(event, eventIndex) {
  const eventData = eventCards.value[eventIndex];

  // Mettre à jour la ligue sélectionnée
  if (event.value) {
    eventData.league = event.value.id;
    eventData.selectedLeague = [event.value]; // Synchroniser avec le tableau pour le v-model
  } else {
    eventData.league = null;
    eventData.selectedLeague = [];
  }

  // Réinitialiser les équipes sélectionnées
  eventData.team1 = null;
  eventData.team2 = null;
  eventData.selectedTeam1 = [];
  eventData.selectedTeam2 = [];

  // Les composants TeamField se mettront à jour automatiquement
  // grâce aux watchers sur leagueId
}

/**
 * Gérer l'effacement de la ligue
 * @param {number} eventIndex - Index de l'événement
 */
async function onLeagueClear(eventIndex) {
  const eventData = eventCards.value[eventIndex];

  // Réinitialiser la ligue
  eventData.league = null;
  eventData.selectedLeague = []; // Synchroniser avec le tableau pour le v-model

  // Réinitialiser les équipes sélectionnées
  eventData.team1 = null;
  eventData.team2 = null;
  eventData.selectedTeam1 = [];
  eventData.selectedTeam2 = [];

  // Les composants TeamField se mettront à jour automatiquement

  console.log("🗑️ Ligue effacée pour événement", eventIndex);
}

/**
 * Gérer la sélection d'une équipe depuis TeamField
 * @param {Object} team - Équipe sélectionnée
 * @param {string} teamType - Type d'équipe ('team1' ou 'team2')
 * @param {number} eventIndex - Index de l'événement
 */
function onTeamSelect(team, teamType, eventIndex) {
  const eventData = eventCards.value[eventIndex];

  if (teamType === "team1") {
    eventData.team1 = team.id;
    eventData.selectedTeam1 = [team];
    console.log("✅ Équipe 1 sélectionnée:", team.name);
  } else if (teamType === "team2") {
    eventData.team2 = team.id;
    eventData.selectedTeam2 = [team];
    console.log("✅ Équipe 2 sélectionnée:", team.name);
  }
}

/**
 * Gérer l'effacement d'une équipe depuis TeamField
 * @param {string} teamType - Type d'équipe ('team1' ou 'team2')
 * @param {number} eventIndex - Index de l'événement
 */
function onTeamClear(teamType, eventIndex) {
  const eventData = eventCards.value[eventIndex];

  if (teamType === "team1") {
    eventData.team1 = null;
    eventData.selectedTeam1 = [];
    console.log("🗑️ Équipe 1 effacée");
  } else if (teamType === "team2") {
    eventData.team2 = null;
    eventData.selectedTeam2 = [];
    console.log("🗑️ Équipe 2 effacée");
  }
}

/**
 * Gérer le rafraîchissement de la recherche depuis TeamField
 * @param {string} teamType - Type d'équipe ('team1' ou 'team2')
 * @param {number} eventIndex - Index de l'événement
 */
function onTeamSearchRefresh(teamType, eventIndex) {
  console.log(
    `🔄 Rafraîchissement de la recherche ${teamType} pour événement ${eventIndex}`
  );
  // Le composant TeamField gère le rafraîchissement en interne
}

// ===== GESTIONNAIRES D'ÉVÉNEMENTS STAKEFIELD =====

/**
 * Gestionnaire de changement de mise du composant StakeField
 * @param {Object} stakeData - Données complètes de la mise
 */
function onStakeChanged(stakeData) {
  console.log("StakeField - Mise modifiée:", stakeData);

  // Mettre à jour les variables pour PotentialWinDisplay
  if (stakeData.currentCapital !== undefined) {
    currentCapital.value = stakeData.currentCapital;
  }
  if (stakeData.calculatedStake !== undefined) {
    calculatedStake.value = stakeData.calculatedStake;
  }
}

/**
 * Gestionnaire d'erreur du composant StakeField
 * @param {String} message - Message d'erreur
 */
function handleStakeError(message) {
  if (message) {
    errors.value.stake = message;
  } else {
    delete errors.value.stake;
  }
}

/**
 * Gestionnaire de validation du composant StakeField
 * @param {Boolean} isValid - État de validation
 */
function handleStakeValid(isValid) {
  if (isValid) {
    delete errors.value.stake;
  }
}

/**
 * Valider le formulaire
 */
function validateForm() {
  console.log("🔍 validateForm appelée");
  errors.value = {};

  if (!formData.value.bet_date) {
    errors.value.bet_date = "La date du pari est requise";
  }

  // Validation optionnelle des équipes (seulement si les deux sont remplies)
  if (
    formData.value.team1 &&
    formData.value.team2 &&
    formData.value.team1 === formData.value.team2
  ) {
    errors.value.team1 = "Les deux équipes doivent être différentes";
    errors.value.team2 = "Les deux équipes doivent être différentes";
  }

  if (!formData.value.global_odds || formData.value.global_odds < 1) {
    errors.value.global_odds = "La cote doit être supérieure ou égale à 1";
  }

  if (!formData.value.stake || formData.value.stake <= 0) {
    errors.value.stake = "La mise doit être supérieure à 0";
  }

  const isValid = Object.keys(errors.value).length === 0;
  console.log("📊 Erreurs de validation:", errors.value);
  console.log("✅ Formulaire valide:", isValid);
  return isValid;
}

/**
 * Soumettre le formulaire
 */
async function submitForm() {
  console.log("🔄 submitForm appelée");
  console.log("📋 Données du formulaire:", formData.value);
  console.log("✅ isFormValid:", isFormValid.value);

  if (!validateForm()) {
    console.log("❌ Validation échouée");
    return;
  }

  console.log("✅ Validation réussie, début de l'envoi");
  loading.value = true;

  try {
    // Préparer les données pour l'API
    const betData = {
      bet_date: formData.value.bet_date.toISOString().split("T")[0], // Format YYYY-MM-DD
      bet_code:
        events.value.length > 0
          ? `Pari combiné (${events.value.length} événements)`
          : currentEvent.value.description ||
            formData.value.description ||
            "Pari libre",
      global_odds: parseFloat(formData.value.global_odds),
      stake: parseFloat(formData.value.stake),
      stake_type: betTypeValue.value, // Type de mise: 'currency' ou 'percentage'
      result: formData.value.result || "pending",
      events: eventCards.value.map((eventData) => ({
        id: eventData.id,
        sport_id: eventData.sport_id,
        country_id: eventData.country_id,
        league_id: eventData.league,
        team1_id: eventData.team1,
        team2_id: eventData.team2,
        description: eventData.description,
        result: eventData.result,
        odds: eventData.odds,
      })), // Array d'événements basé sur eventCards
    };

    console.log("📤 Données envoyées à l'API:", betData);

    let response;
    if (props.editingBet) {
      // Mode édition - mettre à jour le pari existant
      response = await BetService.updateBet(props.editingBet.id, betData);
    } else {
      // Mode création - créer un nouveau pari
      response = await BetService.createBet(betData);
    }

    console.log("📥 Réponse reçue de l'API:", response);

    if (response.success) {
      toast.add({
        severity: "success",
        summary: "Succès",
        detail: props.editingBet
          ? "Pari modifié avec succès"
          : "Pari ajouté avec succès",
        life: 3000,
      });

      // Émettre l'événement pour informer le parent
      emit("bet-created", response.data);

      // Fermer la dialog
      closeDialog();
    } else {
      throw new Error(
        props.editingBet
          ? "Erreur lors de la modification du pari"
          : "Erreur lors de la création du pari"
      );
    }
  } catch (error) {
    console.error("❌ Erreur lors de la sauvegarde du pari:", error);
    toast.add({
      severity: "error",
      summary: "Erreur",
      detail: props.editingBet
        ? "Impossible de modifier le pari: " + error.message
        : "Impossible de créer le pari: " + error.message,
      life: 5000,
    });
  } finally {
    loading.value = false;
  }
}

/**
 * Fermer la dialog
 */
function closeDialog() {
  emit("closeDialog");
}

/**
 * Réinitialiser le formulaire
 */
function resetForm() {
  formData.value = {
    bet_date: new Date(),
    sport_id: null,
    country_id: null,
    league: null,
    team1: null,
    team2: null,
    global_odds: null,
    stake: null,
    result: "pending",
  };
  errors.value = {};
  availableTeams.value = [];

  // Réinitialiser les événements et l'événement actuel
  events.value = [];
  currentEvent.value = {
    sport_id: null,
    country_id: null,
    league: null,
    team1: null,
    team2: null,
    bet_code: "",
    description: "",
    result: null,
    odds: null,
  };

  // Réinitialiser les event cards à leur état initial
  eventCards.value = [
    {
      id: 1,
      sport_id: null,
      country_id: null,
      league: null,
      team1: null,
      team2: null,
      bet_type: null,
      description: "",
      result: null,
      odds: null,
      selectedSport: [],
      selectedCountry: [],
      selectedLeague: [],
      selectedTeam1: [],
      selectedTeam2: [],
      sportSearchResults: [],
      sportLoading: false,
    },
  ];
}

/**
 * Ajouter une nouvelle card d'événement (optimisé)
 */
function addEventCard() {
  const newEventCard = {
    id: Date.now(),
    sport_id: null,
    country_id: null,
    league: null,
    team1: null,
    team2: null,
    description: "",
    result: null,
    odds: null,
    selectedSport: [],
    selectedCountry: [],
    selectedTeam1: [],
    selectedTeam2: [],
    sportSearchResults: [],
    sportLoading: false,
    countryFilteredResults: [],
    countryLoading: false,
    team1SearchResults: [],
    team1Loading: false,
    team2SearchResults: [],
    team2Loading: false,
  };

  eventCards.value.push(newEventCard);
  console.log("✅ Nouvelle card d'événement ajoutée:", {
    cardId: newEventCard.id,
  });
}

/**
 * Supprimer une card d'événement
 * @param {number} index - Index de la card à supprimer
 */
function removeEventCard(index) {
  if (eventCards.value.length > 1) {
    eventCards.value.splice(index, 1);
    console.log("🗑️ Card d'événement supprimée à l'index:", index);
  }
}

/**
 * Calculer le résultat global basé sur tous les résultats des événements
 */
function calculateGlobalResult() {
  if (events.value.length === 0) {
    return;
  }

  let hasAllResults = true;
  let hasWin = true;
  let hasLost = false;
  let hasVoid = false;
  let hasPending = false;

  // Inclure le résultat de l'événement actuel
  const allResults = [...events.value.map((e) => e.result)];
  if (currentEvent.value.result) {
    allResults.push(currentEvent.value.result);
  }

  allResults.forEach((result) => {
    if (!result) {
      hasAllResults = false;
      return;
    }

    switch (result) {
      case resultValues.LOST:
        hasLost = true;
        hasWin = false;
        break;
      case resultValues.VOID:
        hasVoid = true;
        break;
      case "pending":
        hasPending = true;
        hasWin = false;
        break;
      case resultValues.WIN:
        // Continue à vérifier les autres
        break;
      default:
        hasWin = false;
    }
  });

  // Déterminer le résultat global
  if (!hasAllResults || hasPending) {
    formData.value.result = "pending";
  } else if (hasLost) {
    formData.value.result = resultValues.LOST;
  } else if (hasVoid && hasWin) {
    formData.value.result = resultValues.WIN; // Si certains sont void mais les autres gagnés
  } else if (hasVoid) {
    formData.value.result = resultValues.VOID;
  } else if (hasWin) {
    formData.value.result = resultValues.WIN;
  }
}

// Computed pour obtenir le sportId effectif (direct ou inféré de la ligue)
const getEffectiveSportId = (eventData) => {
  if (eventData.sport_id) {
    return eventData.sport_id;
  }
  // Tenter d'inférer le sport à partir de la ligue
  if (eventData.selectedLeague && eventData.selectedLeague.length > 0) {
    return eventData.selectedLeague[0].sport_id;
  }
  return null;
};

// Watchers

// Surveiller les changements dans les résultats des événements
watch(
  () => [events.value.map((e) => e.result), currentEvent.value.result],
  () => {
    calculateGlobalResult();
  },
  { deep: true }
);

// Surveiller les changements de sport pour réinitialiser le type de pari
watch(
  () => eventCards.value.map((event) => event.sport_id),
  (newSportIds, oldSportIds) => {
    // Réinitialiser le type de pari si le sport a changé
    newSportIds.forEach((newSportId, index) => {
      if (oldSportIds && oldSportIds[index] !== newSportId) {
        eventCards.value[index].bet_type = null;
      }
    });
  },
  { deep: true }
);

// Surveiller les changements de sport dans formData pour réinitialiser le type de pari
watch(
  () => formData.value.sport_id,
  (newSportId, oldSportId) => {
    if (oldSportId !== newSportId) {
      formData.value.bet_type = null;
    }
  }
);

// Surveiller les changements de la prop editingBet
watch(
  () => props.editingBet,
  async (newEditingBet) => {
    if (newEditingBet) {
      await initializeFormWithBet(newEditingBet);
    } else {
      resetForm();
    }
  },
  { immediate: false, deep: true }
);

/**
 * Charger les sports au moment de l'ouverture de la modal
 * Cette méthode est appelée par AddBetDialog lors de l'ouverture
 */
async function loadSportsOnModalOpen() {
  console.log("🚀 Chargement des sports au clic sur la modal");

  // Charger les sports si pas encore chargés
  if (!availableSports.value || availableSports.value.length === 0) {
    await loadSports();
  } else {
    console.log(
      "📋 Sports déjà chargés (",
      availableSports.value.length,
      "sports)"
    );
  }
}

// Exposer la méthode pour qu'elle soit accessible depuis le parent
defineExpose({
  loadSportsOnModalOpen,
});

// Fonction d'initialisation asynchrone
async function initializeComponent() {
  // Charger les sports et les pays au montage
  await loadSports();
  await loadCountries();

  // Initialiser le formulaire avec les données du pari à éditer si disponible
  if (props.editingBet) {
    await initializeFormWithBet(props.editingBet);
  }
}

/**
 * Initialiser le formulaire avec les données d'un pari existant
 * @param {Object} bet - Données du pari à éditer
 */
async function initializeFormWithBet(bet) {
  console.log("🔄 Initialisation du formulaire avec les données du pari:", bet);

  try {
    // Réinitialiser d'abord le formulaire
    resetForm();

    // Récupérer le pari complet avec tous ses événements depuis l'API
    console.log("📡 Récupération du pari complet avec ID:", bet.id);
    const fullBetResponse = await BetService.getBet(bet.id);

    if (!fullBetResponse.success) {
      throw new Error("Impossible de récupérer les données complètes du pari");
    }

    const fullBet = fullBetResponse.data;
    console.log("📦 Pari complet récupéré:", fullBet);

    // Remplir les données de base
    formData.value.bet_date = fullBet.bet_date
      ? new Date(fullBet.bet_date)
      : new Date();
    formData.value.global_odds = fullBet.global_odds;
    formData.value.stake = fullBet.stake;
    formData.value.result = fullBet.result || "pending";

    // Gérer le type de mise
    betTypeValue.value = fullBet.stake_type || "currency";

    // Si le pari a des événements, les charger
    if (fullBet.events && fullBet.events.length > 0) {
      console.log("📋 Événements trouvés:", fullBet.events);

      // Vider les event cards actuelles
      eventCards.value = [];

      // Créer une card pour chaque événement
      for (let i = 0; i < fullBet.events.length; i++) {
        const event = fullBet.events[i];
        console.log(`🔄 Traitement événement ${i + 1}:`, event);

        // Log détaillé des champs de description/market disponibles
        console.log(
          `📝 Champs description disponibles pour événement ${i + 1}:`,
          {
            description: event.description,
            bet_code: event.bet_code,
            market: event.market,
            type: event.type,
            market_name: event.market_name,
          }
        );

        const eventCard = {
          id: Date.now() + i,
          sport_id: event.sport_id || event.sport?.id,
          country_id:
            event.country_id || event.country?.id || event.league?.country?.id,
          league: event.league_id || event.league?.id,
          team1: event.team1_id || event.team1?.id,
          team2: event.team2_id || event.team2?.id,
          bet_type: event.bet_type,
          description:
            event.market ||
            event.type ||
            event.description ||
            event.bet_code ||
            "",
          result: event.result,
          odds: event.odds || event.odd,
          selectedSport: [],
          selectedCountry: [],
          selectedLeague: [],
          selectedTeam1: [],
          selectedTeam2: [],
          sportSearchResults: [],
          sportLoading: false,
        };

        console.log(
          `📝 Description finale assignée pour événement ${i + 1}:`,
          eventCard.description
        );

        // Charger les données des sélections pour chaque champ
        const sportId = event.sport_id || event.sport?.id;
        if (sportId) {
          const sport = availableSports.value.find((s) => s.id === sportId);
          if (sport) {
            eventCard.selectedSport = [sport];
            console.log(
              `✅ Sport trouvé pour événement ${i + 1}:`,
              sport.name,
              "Object:",
              sport
            );
          } else {
            console.log(
              `⚠️ Sport non trouvé pour ID ${sportId} dans événement ${i + 1}. availableSports:`,
              availableSports.value.length,
              "items"
            );
          }
        } else {
          console.log(`⚠️ Pas de sportId pour événement ${i + 1}`);
        }

        // Charger les données du pays si disponible
        const countryId =
          event.country_id || event.country?.id || event.league?.country?.id;
        let country = null;

        if (countryId) {
          country = allCountries.value.find((c) => c.id === countryId);
        }

        // Si pas trouvé par ID, essayer par la relation chargée
        if (!country && event.league?.country) {
          country = {
            id: event.league.country.id,
            name: event.league.country.name,
            code: event.league.country.code,
          };
        }

        if (country) {
          eventCard.selectedCountry = [country];
          console.log(`✅ Pays trouvé pour événement ${i + 1}:`, country.name);
        } else {
          console.log(`⚠️ Pays non trouvé pour événement ${i + 1}`, {
            countryId,
            event,
          });
        }

        // Charger les données des équipes si disponibles
        if (event.team1 && (event.team1.name || event.team1.id)) {
          eventCard.selectedTeam1 = [
            {
              id: event.team1.id,
              name: event.team1.name,
            },
          ];
          console.log(
            `✅ Équipe 1 chargée pour événement ${i + 1}:`,
            event.team1.name
          );
        } else if (event.team1_id || event.team1_name) {
          eventCard.selectedTeam1 = [
            {
              id: event.team1_id,
              name: event.team1_name,
            },
          ];
          console.log(
            `✅ Équipe 1 chargée (fallback) pour événement ${i + 1}:`,
            event.team1_name
          );
        }

        if (event.team2 && (event.team2.name || event.team2.id)) {
          eventCard.selectedTeam2 = [
            {
              id: event.team2.id,
              name: event.team2.name,
            },
          ];
          console.log(
            `✅ Équipe 2 chargée pour événement ${i + 1}:`,
            event.team2.name
          );
        } else if (event.team2_id || event.team2_name) {
          eventCard.selectedTeam2 = [
            {
              id: event.team2_id,
              name: event.team2_name,
            },
          ];
          console.log(
            `✅ Équipe 2 chargée (fallback) pour événement ${i + 1}:`,
            event.team2_name
          );
        }

        // Charger les données de la ligue si disponibles
        if (event.league && (event.league.name || event.league.id)) {
          eventCard.selectedLeague = [
            {
              id: event.league.id,
              name: event.league.name,
              sport_id: event.league.sport_id, // Inclure le sport_id de la ligue
            },
          ];
          // Si pas de sport_id direct, essayer de l'inférer de la ligue
          if (!eventCard.sport_id && event.league.sport_id) {
            eventCard.sport_id = event.league.sport_id;
            console.log(
              `✅ Sport ID inféré de la ligue pour événement ${i + 1}:`,
              event.league.sport_id
            );
          }
          console.log(
            `✅ Ligue chargée pour événement ${i + 1}:`,
            event.league.name
          );
        } else if (event.league_id || event.league_name) {
          eventCard.selectedLeague = [
            {
              id: event.league_id,
              name: event.league_name,
            },
          ];
          console.log(
            `✅ Ligue chargée (fallback) pour événement ${i + 1}:`,
            event.league_name
          );
        }

        // Ajouter la card
        eventCards.value.push(eventCard);
      }
    } else {
      // Pari simple - remplir directement les données dans la première card
      console.log("🔄 Pari simple détecté, remplissage de la première card");
      const eventCard = eventCards.value[0];

      if (fullBet.sport_id || fullBet.sport?.id) {
        const sportId = fullBet.sport_id || fullBet.sport?.id;
        eventCard.sport_id = sportId;
        const sport = availableSports.value.find((s) => s.id === sportId);
        if (sport) {
          eventCard.selectedSport = [sport];
          console.log("✅ Sport chargé pour pari simple:", sport.name);
        } else {
          console.log(
            `⚠️ Sport non trouvé pour ID ${sportId} dans pari simple`
          );
        }
      }

      if (fullBet.country_id || fullBet.country?.id) {
        const countryId = fullBet.country_id || fullBet.country?.id;
        eventCard.country_id = countryId;
        const country = allCountries.value.find((c) => c.id === countryId);
        if (country) {
          eventCard.selectedCountry = [country];
          console.log("✅ Pays chargé pour pari simple:", country.name);
        } else {
          console.log(
            `⚠️ Pays non trouvé pour ID ${countryId} dans pari simple`
          );
        }
      }

      if (fullBet.league_id || fullBet.league?.id) {
        const leagueId = fullBet.league_id || fullBet.league?.id;
        eventCard.league = leagueId;

        if (fullBet.league?.name || fullBet.league_name) {
          eventCard.selectedLeague = [
            {
              id: leagueId,
              name: fullBet.league?.name || fullBet.league_name,
              sport_id: fullBet.league?.sport_id, // Inclure le sport_id de la ligue
            },
          ];
          // Si pas de sport_id direct, essayer de l'inférer de la ligue
          if (!eventCard.sport_id && fullBet.league?.sport_id) {
            eventCard.sport_id = fullBet.league.sport_id;
            console.log(
              "✅ Sport ID inféré de la ligue pour pari simple:",
              fullBet.league.sport_id
            );
          }
          console.log(
            "✅ Ligue chargée pour pari simple:",
            eventCard.selectedLeague[0].name
          );
        }
      }

      if (fullBet.team1_id || fullBet.team1?.id) {
        const team1Id = fullBet.team1_id || fullBet.team1?.id;
        eventCard.team1 = team1Id;

        if (fullBet.team1?.name || fullBet.team1_name) {
          eventCard.selectedTeam1 = [
            {
              id: team1Id,
              name: fullBet.team1?.name || fullBet.team1_name,
            },
          ];
          console.log(
            "✅ Équipe 1 chargée pour pari simple:",
            eventCard.selectedTeam1[0].name
          );
        }
      }

      if (fullBet.team2_id || fullBet.team2?.id) {
        const team2Id = fullBet.team2_id || fullBet.team2?.id;
        eventCard.team2 = team2Id;

        if (fullBet.team2?.name || fullBet.team2_name) {
          eventCard.selectedTeam2 = [
            {
              id: team2Id,
              name: fullBet.team2?.name || fullBet.team2_name,
            },
          ];
          console.log(
            "✅ Équipe 2 chargée pour pari simple:",
            eventCard.selectedTeam2[0].name
          );
        }
      }

      // Pour les paris simples, la description pourrait être dans bet_code du pari principal
      // ou dans les événements associés (market/type)
      let description = "";

      // Essayer d'abord le bet_code du pari principal
      if (fullBet.bet_code) {
        description = fullBet.bet_code;
      }
      // Puis regarder dans les événements s'il y en a
      else if (fullBet.events && fullBet.events.length > 0) {
        const firstEvent = fullBet.events[0];
        description =
          firstEvent.market || firstEvent.type || firstEvent.description || "";
      }
      // Enfin, autres champs possibles
      else {
        description =
          fullBet.description || fullBet.market || fullBet.market_name || "";
      }

      if (description) {
        eventCard.description = description;
        console.log(
          "✅ Description chargée pour pari simple:",
          eventCard.description
        );
      }
    }

    console.log("✅ Formulaire initialisé avec les données du pari");
  } catch (error) {
    console.error("❌ Erreur lors de l'initialisation du formulaire:", error);
    toast.add({
      severity: "error",
      summary: "Erreur",
      detail: "Erreur lors du chargement des données du pari",
      life: 3000,
    });
  }
}

/**
 * Nouvelles fonctions de gestion des cotes avec les composants dédiés
 */

/**
 * Gérer les changements de cote d'événement
 * @param {Object} eventData - Données de l'événement avec la nouvelle cote
 */
function onEventOddsChanged(eventData) {
  console.log("AddBetForm - onEventOddsChanged:", eventData);
  // Le calcul automatique est géré par le composant OddsCalculator
}

/**
 * Gérer les erreurs de cote d'événement
 * @param {number} eventIndex - Index de l'événement
 * @param {string} message - Message d'erreur
 */
function handleEventOddsError(eventIndex, message) {
  if (message) {
    errors.value[`event_odds-${eventIndex}`] = message;
  } else {
    delete errors.value[`event_odds-${eventIndex}`];
  }
}

/**
 * Gérer la validation de cote d'événement
 * @param {number} eventIndex - Index de l'événement
 * @param {boolean} isValid - État de validation
 */
function handleEventOddsValid(eventIndex, isValid) {
  if (isValid) {
    delete errors.value[`event_odds-${eventIndex}`];
  }
}

/**
 * Gérer les erreurs de cote globale
 * @param {string} message - Message d'erreur
 */
function handleGlobalOddsError(message) {
  if (message) {
    errors.value.global_odds = message;
  } else {
    delete errors.value.global_odds;
  }
}

/**
 * Gérer la validation de cote globale
 * @param {boolean} isValid - État de validation
 */
function handleGlobalOddsValid(isValid) {
  if (isValid) {
    delete errors.value.global_odds;
  }
}

/**
 * Gérer le calcul automatique de la cote globale
 * @param {number} calculatedOdds - Cote globale calculée
 */
function onGlobalOddsCalculated(calculatedOdds) {
  console.log(
    "AddBetForm - Cote globale calculée automatiquement:",
    calculatedOdds
  );
  formData.value.global_odds = calculatedOdds;
  // Effacer les erreurs de cote globale si le calcul réussit
  delete errors.value.global_odds;
}

/**
 * Gérer l'effacement de la cote globale
 */
function onGlobalOddsCleared() {
  console.log("AddBetForm - Cote globale effacée");
  // Ne pas modifier la cote globale automatiquement lors de l'effacement
  // L'utilisateur peut toujours saisir manuellement
}

/**
 * Gérer l'échec du calcul de la cote globale
 * @param {string} errorMessage - Message d'erreur
 */
function onGlobalOddsCalculationFailed(errorMessage) {
  console.log("AddBetForm - Échec du calcul de la cote globale:", errorMessage);
  // Ne pas afficher d'erreur à l'utilisateur, juste logger
}

// Lifecycle
onMounted(() => {
  initializeComponent();
});
</script>

<style scoped>
.p-invalid {
  border-color: #ef4444;
}

/* Styles personnalisés pour les composants Select */
:deep(.select-custom .p-dropdown-panel) {
  max-width: 100% !important;
  width: auto !important;
}

:deep(.select-panel-custom) {
  max-width: calc(50vw - 4rem) !important;
  width: auto !important;
  max-height: 200px !important;
  overflow-y: auto !important;
  z-index: 9999 !important;
  position: absolute !important;
}

@media (max-width: 960px) {
  :deep(.select-panel-custom) {
    max-width: calc(90vw - 4rem) !important;
  }
}

:deep(.select-panel-custom .p-dropdown-items) {
  max-width: 100% !important;
  max-height: 180px !important;
  overflow-y: auto !important;
}

:deep(.select-panel-custom .p-dropdown-item) {
  max-width: 100% !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
  white-space: nowrap !important;
}

:deep(.select-custom .p-dropdown-label) {
  max-width: 100% !important;
  overflow: hidden !important;
  text-overflow: ellipsis !important;
  white-space: nowrap !important;
}

/* Contraindre la modal et ses éléments */
:deep(.p-dialog) {
  overflow: visible !important;
}

:deep(.p-dialog-content) {
  overflow: visible !important;
}
</style>

<style>
/* Contraindre l'overlay du sélecteur */
.p-select-overlay {
  max-width: calc(50vw - 4rem) !important;
  width: auto !important;
}

@media (max-width: 960px) {
  .p-select-overlay {
    max-width: calc(90vw - 4rem) !important;
  }
}
</style>
