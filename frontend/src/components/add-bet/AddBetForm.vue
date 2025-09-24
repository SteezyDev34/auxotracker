<template>
  <div class="container mx-auto p-6 max-w-4xl">
    <h1 class="text-2xl font-bold mb-6">
      {{ events.length > 1 ? 'Ajouter un pari combiné' : 'Ajouter un pari simple' }}
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
      <div v-for="(eventData, eventIndex) in eventCards" :key="eventData.id" class="border-surface-200 dark:border-surface-600 border rounded-lg p-4 mb-4">
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
          :ref="(el) => { if (el) sportAutoCompleteRefs[eventIndex] = el }"
        />

        <!-- Champs conditionnels selon le sport -->
        <div v-if="eventData.sport_id" class="space-y-4 mb-4">
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
            @league-select="(league) => onLeagueSelect({ value: league }, eventIndex)"
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
          <small v-if="errors[`event_description-${eventIndex}`]" class="text-red-500 block mt-1">{{ errors[`event_description-${eventIndex}`] }}</small>
        </div>

            <!-- Champs spécifiques à l'événement pour les paris combinés -->
            <div v-if="events.length > 0" class="space-y-4">
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
                <small v-if="errors[`event_result-${eventIndex}`]" class="text-red-500 block mt-1">{{ errors[`event_result-${eventIndex}`] }}</small>
              </div>

              <!-- Cote de l'événement -->
              <div class="flex flex-col gap-2">
                <InputText 
                  :id="`event_odds_${eventIndex}`" 
                  :ref="`eventOddsInput_${eventIndex}`"
                  v-model="eventData.odds" 
                  placeholder="Cote de l'événement *"
                  class="w-full"
                  :class="{ 'p-invalid': errors[`event_odds-${eventIndex}`] }"
                  type="text"
                  @input="(e) => handleEventOddsInput(e, eventIndex)"
                  @keypress="handleEventOddsKeypress"
                />
                <small v-if="errors[`event_odds-${eventIndex}`]" class="text-red-500 block mt-1">{{ errors[`event_odds-${eventIndex}`] }}</small>
              </div>
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
        <h3 class="text-lg font-semibold mb-4 text-blue-800">Événements du pari combiné ({{ events.length }})</h3>
        
        <div class="space-y-3">
          <div v-for="(event, index) in events" :key="event.id" class="bg-white p-3 rounded border">
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
                  <span v-if="event.result" class="font-medium" :class="getResultClass(event.result)">
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
      <div class="grid grid-cols-3 sm:grid-cols-4 gap-1 overflow-hidden">
        <!-- Cote -->
        <div class="flex flex-col justify-center min-w-0 w-full">
          <div class="w-full">
            <InputText 
              id="global_odds" 
              v-model="formData.global_odds" 
              type="text"
              placeholder="Cote"
              class="w-full text-xs"
              :class="{ 'p-invalid': errors.global_odds }"
              @input="handleOddsInput"
              @keypress="handleOddsKeypress"
            />
          </div>
          <small v-if="errors.global_odds" class="text-red-500 text-xs truncate">{{ errors.global_odds }}</small>
        </div>
        
        <!-- Mise -->
        <div class="flex flex-col justify-center min-w-0 w-full">
          <div class="w-full">
            <InputText 
              id="stake" 
              v-model="formData.stake" 
              type="text"
              :placeholder="betTypeValue === 'currency' ? 'Mise en €' : betTypeValue === 'percentage' ? 'Mise en %' : 'Mise'"
              class="w-full text-xs"
              :class="{ 'p-invalid': errors.stake }"
              @input="handleStakeInput"
              @keypress="handleStakeKeypress"
            />
          </div>
          <small v-if="errors.stake" class="text-red-500 text-xs truncate">{{ errors.stake }}</small>
        </div>

        <!-- Type de mise -->
        <div class="flex flex-col justify-center min-w-0 w-full">
          <div class="w-full flex items-center">
            <SelectButton 
              v-model="betTypeValue" 
              :options="betTypeOptions" 
              optionLabel="symbol" 
              optionValue="value"
              class="h-8 text-xs w-full"
            />
          </div>
        </div>

       
      </div>
      <!-- Section détaillée du gain potentiel (mode pourcentage uniquement) -->
      <div v-if="betTypeValue === 'percentage'" class="flex flex-col gap-2 mb-4 mt-4">
        <div class="p-4 bg-gray-50 rounded border">
          <h4 class="text-sm font-semibold text-gray-800 mb-3">Détails du gain potentiel</h4>
          <!-- Capital actuel -->
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm text-gray-600">Capital actuel :</span>
            <span class="text-sm font-medium">
              <i v-if="capitalLoading" class="pi pi-spin pi-spinner text-xs"></i>
              <span v-else>{{ currentCapital.toFixed(2) }} €</span>
            </span>
          </div>
          
          <!-- Mise calculée -->
          <div v-if="calculatedStake > 0" class="flex justify-between items-center mb-2">
            <span class="text-sm text-gray-600">Mise calculée ({{ formData.stake }}%) :</span>
            <span class="text-sm font-medium text-blue-600">{{ calculatedStake.toFixed(2) }} €</span>
          </div>
          
          <!-- Cote -->
          <div v-if="formData.global_odds" class="flex justify-between items-center mb-2">
            <span class="text-sm text-gray-600">Cote :</span>
            <span class="text-sm font-medium">{{ parseFloat(formData.global_odds).toFixed(2) }}</span>
          </div>
          
          <!-- Gain potentiel -->
          <div class="flex justify-between items-center pt-2 border-t border-gray-200">
            <span class="text-sm font-semibold text-gray-800">Gain potentiel :</span>
            <span class="text-lg font-bold text-green-600">{{ potentialWin.toFixed(2) }} €</span>
          </div>
        </div>
      </div>
      <!-- Gain potentiel simple (mode devise uniquement) -->
      <div v-if="betTypeValue === 'currency'" class="flex flex-col gap-2 mt-4 mb-4">
        <div class="p-3 bg-gray-50 rounded border text-lg font-semibold text-green-600 text-center">
          Gain potentiel : {{ potentialWin.toFixed(2) }} €
        </div>
      </div>
      <!-- Résultat (optionnel) -->
      <div class="flex flex-col sm:flex-row sm:items-center gap-2">
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
        label="Ajouter le pari" 
        icon="pi pi-check" 
        @click="submitForm" 
        :loading="loading"
        :disabled="!isFormValid"
      />
    </div>
  </div>
</template>
<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue';
// Dialog import supprimé car ce n'est plus un Dialog
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import AutoComplete from 'primevue/autocomplete';
import SportField from './fields/SportField.vue';
import CountryField from './fields/CountryField.vue';
import LeagueField from './fields/LeagueField.vue';
import TeamField from './fields/TeamField.vue';
import TypePariField from './fields/TypePariField.vue';
import DatePickerField from '@/components/DatePickerField.vue';
import { BetService } from '@/service/BetService';
import { SportService } from '@/service/SportService';
import { CountryService } from '@/service/CountryService';
import { useToast } from 'primevue/usetoast';
import { useLayout } from '@/layout/composables/layout';
import { useBetResults } from '@/composables/useBetResults';

// Props
// Props supprimés car ce n'est plus un Dialog
// Emits
const emit = defineEmits(['bet-created']);
// Composables
const toast = useToast();
const { isDarkTheme: layoutDarkTheme } = useLayout(); // Indique si le thème sombre est actif
const { resultOptions, resultValues, getResultLabel, getResultClass } = useBetResults(); // Options de résultats globales

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
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
// Variables pour la recherche de pays
const countrySearchQuery = ref('');
const countrySearchResults = ref([]);
const countryLoading = ref(false);
const countryCurrentPage = ref(1);
const countryHasMore = ref(false);
const selectedCountry = ref([]);

// Variables pour la recherche de sports
const sportSearchQuery = ref('');
const sportSearchResults = ref([]);
const sportLoading = ref(false);
const selectedSport = ref([]);

// Références pour les composants AutoComplete
const sportAutoCompleteRefs = ref({});

// Variables pour le type de mise
const betTypeValue = ref('currency');
const betTypeOptions = ref([
  { symbol: '€', value: 'currency' },
  { symbol: '%', value: 'percentage' }
]);

// Variables pour le capital actuel
const currentCapital = ref(0);
const calculatedStake = ref(0);
const capitalLoading = ref(false);

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
    description: '',
    result: null,
    odds: null,
    selectedSport: [],
    selectedCountry: [],
    selectedLeague: [],
    selectedTeam1: [],
    selectedTeam2: [],
    sportSearchResults: [],
    sportLoading: false
  }
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
  bet_code: '',
  description: '',
  result: null,
  odds: null
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
  result: resultValues.PENDING
});



// Options pour le résultat maintenant fournies par le composable useBetResults

// Computed
// Variable visible supprimée car ce n'est plus un Dialog

const potentialWin = computed(() => {
  let stake = 0;
  
  if (betTypeValue.value === 'percentage' && calculatedStake.value > 0) {
    // Utiliser la mise calculée en pourcentage
    stake = calculatedStake.value;
  } else if (betTypeValue.value === 'currency' && formData.value.stake) {
    // Utiliser la mise en devise
    stake = parseFloat(formData.value.stake);
  }
  
  if (stake > 0 && formData.value.global_odds) {
    return stake * parseFloat(formData.value.global_odds);
  }
  return 0;
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
  console.log('🔽 Dropdown type de paris ouvert pour événement', eventIndex);
  // Pas de logique spéciale nécessaire, le Select gère automatiquement les options
}

/**
 * Gérer la sélection d'un type de pari
 * @param {Object} betType - Type de pari sélectionné
 * @param {number} eventIndex - Index de l'événement
 */
function onBetTypeSelect(betType, eventIndex) {
  console.log('✅ Type de pari sélectionné pour événement', eventIndex, ':', betType);
  // Logique additionnelle si nécessaire (validation, calculs, etc.)
}

const isFormValid = computed(() => {
  // Seuls les champs essentiels sont obligatoires
  return formData.value.bet_date &&
         formData.value.global_odds &&
         formData.value.stake;
});



// Méthodes

/**
 * Charger la liste des pays disponibles
 */
async function loadCountries() {
  try {
    const countryData = await CountryService.getCountries();
    // Utiliser les vrais IDs des pays depuis l'API
    const formattedCountries = countryData.map(country => ({
      id: country.id, // Utiliser le vrai ID du pays
      name: country.name,
      code: country.code
    }));
    
    countries.value = formattedCountries;
    allCountries.value = formattedCountries;
  } catch (error) {
    console.error('Erreur lors du chargement des pays:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger les pays',
      life: 3000
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
    console.error('Erreur lors du chargement des équipes par sport:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger les équipes',
      life: 3000
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
    console.error('Erreur lors du chargement des équipes par ligue:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger les équipes de la ligue',
      life: 3000
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
    console.error('❌ Erreur lors du chargement des sports:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger les sports',
      life: 3000
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
  console.log(`🧹 [${timestamp}] Champ sport vidé pour événement ${eventIndex}`);
  
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
  
  console.log(`✅ [${timestamp}] Tous les champs liés au sport ont été réinitialisés`);
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
    console.log('✅ Sport sélectionné pour événement', eventIndex, ':', event.value.name);
  } else {
    eventData.selectedSport = [];
    eventData.sport_id = null;
    console.log('✅ Sport désélectionné pour événement', eventIndex);
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
    console.log('🔄 Pays sélectionné:', event.value.name);
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
  
  console.log('🗑️ Ligue effacée pour événement', eventIndex);
}

/**
 * Gérer la sélection d'une équipe depuis TeamField
 * @param {Object} team - Équipe sélectionnée
 * @param {string} teamType - Type d'équipe ('team1' ou 'team2')
 * @param {number} eventIndex - Index de l'événement
 */
function onTeamSelect(team, teamType, eventIndex) {
  const eventData = eventCards.value[eventIndex];
  
  if (teamType === 'team1') {
    eventData.team1 = team.id;
    eventData.selectedTeam1 = [team];
    console.log('✅ Équipe 1 sélectionnée:', team.name);
  } else if (teamType === 'team2') {
    eventData.team2 = team.id;
    eventData.selectedTeam2 = [team];
    console.log('✅ Équipe 2 sélectionnée:', team.name);
  }
}

/**
 * Gérer l'effacement d'une équipe depuis TeamField
 * @param {string} teamType - Type d'équipe ('team1' ou 'team2')
 * @param {number} eventIndex - Index de l'événement
 */
function onTeamClear(teamType, eventIndex) {
  const eventData = eventCards.value[eventIndex];
  
  if (teamType === 'team1') {
    eventData.team1 = null;
    eventData.selectedTeam1 = [];
    console.log('🗑️ Équipe 1 effacée');
  } else if (teamType === 'team2') {
    eventData.team2 = null;
    eventData.selectedTeam2 = [];
    console.log('🗑️ Équipe 2 effacée');
  }
}

/**
 * Gérer le rafraîchissement de la recherche depuis TeamField
 * @param {string} teamType - Type d'équipe ('team1' ou 'team2')
 * @param {number} eventIndex - Index de l'événement
 */
function onTeamSearchRefresh(teamType, eventIndex) {
  console.log(`🔄 Rafraîchissement de la recherche ${teamType} pour événement ${eventIndex}`);
  // Le composant TeamField gère le rafraîchissement en interne
}



/**
 * Gérer la saisie de la cote pour remplacer immédiatement les virgules par des points
 * @param {Event} event - Événement d'input
 */
function handleOddsInput(event) {
  let inputValue = event.target.value;
  console.log('handleOddsInput - Valeur tapée:', inputValue);
  
  // Remplacer immédiatement toutes les virgules par des points
  const normalizedValue = inputValue.replace(/,/g, '.');
  console.log('handleOddsInput - Valeur normalisée:', normalizedValue);
  
  // Si une virgule a été détectée, forcer le remplacement immédiat
  if (inputValue !== normalizedValue) {
    console.log('handleOddsInput - Virgule détectée, remplacement en cours...');
    // Sauvegarder la position du curseur
    const cursorPosition = event.target.selectionStart;
    
    // Mettre à jour immédiatement la valeur de l'input
    event.target.value = normalizedValue;
    
    // Restaurer la position du curseur
    event.target.setSelectionRange(cursorPosition, cursorPosition);
    
    // Mettre à jour le v-model
    formData.value.global_odds = normalizedValue;
    console.log('handleOddsInput - Remplacement terminé, nouvelle valeur:', event.target.value);
    return;
  }
  
  // Vérifier que la valeur est un nombre réel valide
  if (normalizedValue === '' || normalizedValue === '.') {
    formData.value.global_odds = null;
    return;
  }
  
  // Validation du format nombre réel
  const numericValue = parseFloat(normalizedValue);
  if (!isNaN(numericValue) && isFinite(numericValue) && numericValue > 0) {
    formData.value.global_odds = numericValue;
  } else {
    // Si la valeur n'est pas valide, on garde la dernière valeur valide
    console.warn('Valeur de cote invalide:', normalizedValue);
  }
}

/**
 * Gérer la saisie de la mise pour accepter les virgules et les points comme séparateurs décimaux
 * @param {Event} event - Événement d'input
 */
function handleStakeInput(event) {
  let inputValue = event.target.value;
  console.log('handleStakeInput - Valeur tapée:', inputValue);
  
  // Remplacer immédiatement toutes les virgules par des points
  const normalizedValue = inputValue.replace(/,/g, '.');
  console.log('handleStakeInput - Valeur normalisée:', normalizedValue);
  
  // Si une virgule a été détectée, forcer le remplacement immédiat
  if (inputValue !== normalizedValue) {
    console.log('handleStakeInput - Virgule détectée, remplacement en cours...');
    // Sauvegarder la position du curseur
    const cursorPosition = event.target.selectionStart;
    
    // Mettre à jour immédiatement la valeur de l'input
    event.target.value = normalizedValue;
    
    // Restaurer la position du curseur
    event.target.setSelectionRange(cursorPosition, cursorPosition);
    
    // Mettre à jour le v-model
    formData.value.stake = normalizedValue;
    console.log('handleStakeInput - Remplacement terminé, nouvelle valeur:', event.target.value);
    return;
  }
  
  // Vérifier que la valeur est un nombre réel valide
  if (normalizedValue === '' || normalizedValue === '.') {
    formData.value.stake = null;
    return;
  }
  
  // Validation du format nombre réel (la mise peut être 0)
  const numericValue = parseFloat(normalizedValue);
  if (!isNaN(numericValue) && isFinite(numericValue) && numericValue >= 0) {
    formData.value.stake = numericValue;
  } else {
    // Si la valeur n'est pas valide, on garde la dernière valeur valide
    console.warn('Valeur de mise invalide:', normalizedValue);
  }
}

/**
 * Gérer la saisie de la cote d'événement pour remplacer immédiatement les virgules par des points
 * @param {Event} event - Événement d'input
 * @param {number} eventIndex - Index de l'événement
 */
function handleEventOddsInput(event, eventIndex) {
  let inputValue = event.target.value;
  console.log('handleEventOddsInput - Valeur tapée:', inputValue, 'pour événement', eventIndex);
  
  const eventData = eventCards.value[eventIndex];
  
  // Remplacer immédiatement toutes les virgules par des points
  const normalizedValue = inputValue.replace(/,/g, '.');
  console.log('handleEventOddsInput - Valeur normalisée:', normalizedValue);
  
  // Si une virgule a été détectée, forcer le remplacement immédiat
  if (inputValue !== normalizedValue) {
    console.log('handleEventOddsInput - Virgule détectée, remplacement en cours...');
    // Sauvegarder la position du curseur
    const cursorPosition = event.target.selectionStart;
    
    // Mettre à jour immédiatement la valeur de l'input
    event.target.value = normalizedValue;
    
    // Restaurer la position du curseur
    event.target.setSelectionRange(cursorPosition, cursorPosition);
    
    // Mettre à jour le v-model
    eventData.odds = normalizedValue;
    console.log('handleEventOddsInput - Remplacement terminé, nouvelle valeur:', event.target.value);
    return;
  }
  
  // Vérifier que la valeur est un nombre réel valide
  if (normalizedValue === '' || normalizedValue === '.') {
    eventData.odds = null;
    // Recalculer la cote globale même avec une valeur vide
    calculateGlobalOdds();
    return;
  }
  
  // Validation du format nombre réel
  const numericValue = parseFloat(normalizedValue);
  if (!isNaN(numericValue) && isFinite(numericValue) && numericValue > 0) {
    eventData.odds = numericValue;
  } else {
    // Si la valeur n'est pas valide, on garde la dernière valeur valide
    console.warn('Valeur de cote d\'événement invalide:', normalizedValue);
  }
  
  // Recalculer la cote globale
  calculateGlobalOdds();
}

/**
 * Gérer les touches pressées pour la cote globale (permettre point et virgule)
 * @param {KeyboardEvent} event - Événement de frappe
 */
function handleOddsKeypress(event) {
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
 * Gérer les touches pressées pour la mise (permettre point et virgule)
 * @param {KeyboardEvent} event - Événement de frappe
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
 * Gérer les touches pressées pour la cote d'événement (permettre point et virgule)
 * @param {KeyboardEvent} event - Événement de frappe
 */
function handleEventOddsKeypress(event) {
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
 * Valider le formulaire
 */
function validateForm() {
  console.log('🔍 validateForm appelée');
  errors.value = {};
  
  if (!formData.value.bet_date) {
    errors.value.bet_date = 'La date du pari est requise';
  }
  
  // Validation optionnelle des équipes (seulement si les deux sont remplies)
  if (formData.value.team1 && formData.value.team2 && formData.value.team1 === formData.value.team2) {
    errors.value.team1 = 'Les deux équipes doivent être différentes';
    errors.value.team2 = 'Les deux équipes doivent être différentes';
  }
  

  
  if (!formData.value.global_odds || formData.value.global_odds < 1) {
    errors.value.global_odds = 'La cote doit être supérieure ou égale à 1';
  }
  
  if (!formData.value.stake || formData.value.stake <= 0) {
    errors.value.stake = 'La mise doit être supérieure à 0';
  }
  
  const isValid = Object.keys(errors.value).length === 0;
  console.log('📊 Erreurs de validation:', errors.value);
  console.log('✅ Formulaire valide:', isValid);
  return isValid;
}

/**
 * Soumettre le formulaire
 */
async function submitForm() {
  console.log('🔄 submitForm appelée');
  console.log('📋 Données du formulaire:', formData.value);
  console.log('✅ isFormValid:', isFormValid.value);
  
  if (!validateForm()) {
    console.log('❌ Validation échouée');
    return;
  }
  
  console.log('✅ Validation réussie, début de l\'envoi');
  loading.value = true;
  
  try {
    // Préparer les données pour l'API
    const betData = {
      bet_date: formData.value.bet_date.toISOString().split('T')[0], // Format YYYY-MM-DD
      bet_code: events.value.length > 0 ? `Pari combiné (${events.value.length} événements)` : (currentEvent.value.description || formData.value.description || 'Pari libre'),
      global_odds: parseFloat(formData.value.global_odds),
      stake: parseFloat(formData.value.stake),
      stake_type: betTypeValue.value, // Type de mise: 'currency' ou 'percentage'
      result: formData.value.result || 'pending',
      events: eventCards.value.map(eventData => ({
        id: eventData.id,
        sport_id: eventData.sport_id,
        country_id: eventData.country_id,
        league_id: eventData.league,
        team1_id: eventData.team1,
        team2_id: eventData.team2,
        description: eventData.description,
        result: eventData.result,
        odds: eventData.odds
      })) // Array d'événements basé sur eventCards
    };
    
    console.log('📤 Données envoyées à l\'API:', betData);
    
    const response = await BetService.createBet(betData);
    
    console.log('📥 Réponse reçue de l\'API:', response);
    
    if (response.success) {
      toast.add({
        severity: 'success',
        summary: 'Succès',
        detail: 'Pari ajouté avec succès - Données reçues: ' + JSON.stringify(response.data),
        life: 5000
      });
      
      // Émettre l'événement pour informer le parent
      emit('bet-created', response.data);
      
      // Fermer la dialog
      closeDialog();
    } else {
      throw new Error('Erreur lors de la création du pari');
    }
  } catch (error) {
    console.error('❌ Erreur lors de la création du pari:', error);
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de créer le pari: ' + error.message,
      life: 5000
    });
  } finally {
    loading.value = false;
  }
}

/**
 * Fermer la dialog
 */
function closeDialog() {
  emit('closeDialog');
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
    result: 'pending'
  };
  errors.value = {};
  availableLeagues.value = [];
  availableTeams.value = [];
  
  // Réinitialiser les événements et l'événement actuel
  events.value = [];
  currentEvent.value = {
    sport_id: null,
    country_id: null,
    league: null,
    team1: null,
    team2: null,
    bet_code: '',
    description: '',
    result: null,
    odds: null
  };
  
  // Réinitialiser les variables de recherche de pays
  selectedCountry.value = null;
  countrySearchResults.value = [];
  countrySearchQuery.value = '';
  countryCurrentPage.value = 1;
  countryHasMore.value = false;
  countryLoading.value = false;
  

  
  // Réinitialiser les variables de recherche d'équipes
  selectedTeam1.value = [];
  selectedTeam2.value = [];
  teamSearchResults.value = [];
  teamSearchQuery.value = '';
  teamCurrentPage.value = 1;
  teamHasMore.value = false;
  teamLoading.value = false;
}





/**
 * Supprimer l'équipe 1 sélectionnée
 */
function clearTeam1() {
  selectedTeam1.value = [];
  formData.value.team1 = null;
}

/**
 * Supprimer l'équipe 2 sélectionnée
 */
function clearTeam2() {
  selectedTeam2.value = [];
  formData.value.team2 = null;
}

/**
 * Ajouter un pari combiné
 */
function addEvent() {
  // Valider que tous les champs requis sont remplis
  if (!formData.value.sport_id || !formData.value.league || !formData.value.team1 || !formData.value.team2 || !currentEvent.value.description) {
    toast.add({
      severity: 'warn',
      summary: 'Champs manquants',
      detail: 'Veuillez remplir tous les champs de l\'événement avant d\'ajouter un nouvel événement.',
      life: 3000
    });
    return;
  }

  // Créer un nouvel événement avec les données actuelles
  const newEvent = {
    id: Date.now(), // ID temporaire
    sport_id: formData.value.sport_id,
    country_id: formData.value.country_id,
    league: formData.value.league,
    team1: selectedTeam1.value,
    team2: selectedTeam2.value,
    bet_code: currentEvent.value.description,
    description: currentEvent.value.description,
    result: currentEvent.value.result,
    odds: currentEvent.value.odds
  };

  // Ajouter l'événement à la liste
  events.value.push(newEvent);

  // Réinitialiser les champs pour le prochain événement
  resetEventFields();

  console.log('✅ Événement ajouté:', newEvent);
   console.log('📋 Liste des événements:', events.value);
   
   // Recalculer la cote globale
   calculateGlobalOdds();
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
    description: '',
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
    team2Loading: false
  };
  
  eventCards.value.push(newEventCard);
  console.log('✅ Nouvelle card d\'événement ajoutée:', {
    cardId: newEventCard.id
  });
}

/**
 * Supprimer une card d'événement
 * @param {number} index - Index de la card à supprimer
 */
function removeEventCard(index) {
  if (eventCards.value.length > 1) {
    eventCards.value.splice(index, 1);
    console.log('🗑️ Card d\'événement supprimée à l\'index:', index);
  }
}
 
 /**
  * Supprimer un événement de la liste
  * @param {number} index - Index de l'événement à supprimer
  */
 function removeEvent(index) {
   events.value.splice(index, 1);
   
   // Recalculer la cote globale après suppression
   calculateGlobalOdds();
   
   console.log('🗑️ Événement supprimé à l\'index:', index);
   console.log('📋 Liste des événements mise à jour:', events.value);
 }

/**
 * Réinitialiser les champs de l'événement actuel
 */
function resetEventFields() {
  // Réinitialiser les champs de l'événement
  formData.value.sport_id = null;
  formData.value.country_id = null;
  formData.value.league = null;
  formData.value.team1 = null;
  formData.value.team2 = null;
  
  // Réinitialiser l'événement actuel
  currentEvent.value.description = '';
  currentEvent.value.result = null;
  currentEvent.value.odds = null;
  
  // Réinitialiser les variables de sélection
  selectedCountry.value = [];
  selectedTeam1.value = [];
  selectedTeam2.value = [];
  
  // Réinitialiser l'événement actuel
  currentEvent.value = {
    sport_id: null,
    country_id: null,
    league: null,
    team1: null,
    team2: null,
    bet_code: '',
    result: null,
    odds: null
  };
}

/**
 * Calculer la cote globale en multipliant toutes les cotes des événements
 */
function calculateGlobalOdds() {
  if (events.value.length === 0) {
    return;
  }
  
  let globalOdds = 1;
  let hasValidOdds = true;
  
  // Inclure la cote de l'événement actuel s'il y en a une
  if (currentEvent.value.odds && currentEvent.value.odds > 0) {
    globalOdds *= parseFloat(currentEvent.value.odds);
  }
  
  // Multiplier par toutes les cotes des événements ajoutés
  events.value.forEach(event => {
    if (event.odds && event.odds > 0) {
      globalOdds *= parseFloat(event.odds);
    } else {
      hasValidOdds = false;
    }
  });
  
  // Mettre à jour la cote globale seulement si toutes les cotes sont valides
  if (hasValidOdds && globalOdds > 1) {
    formData.value.global_odds = parseFloat(globalOdds.toFixed(2));
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
  const allResults = [...events.value.map(e => e.result)];
  if (currentEvent.value.result) {
    allResults.push(currentEvent.value.result);
  }
  
  allResults.forEach(result => {
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
      case 'pending':
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
    formData.value.result = 'pending';
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

/**
 * Récupérer le capital actuel de l'utilisateur
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
  } finally {
    capitalLoading.value = false;
  }
}

/**
 * Calculer la mise en pourcentage du capital
 */
function calculatePercentageStake() {
  if (betTypeValue.value === 'percentage' && formData.value.stake && currentCapital.value > 0) {
    const percentage = parseFloat(formData.value.stake);
    if (!isNaN(percentage) && percentage > 0) {
      calculatedStake.value = (currentCapital.value * percentage) / 100;
      return;
    }
  }
  calculatedStake.value = 0;
}

// Watchers
// Surveiller le changement de type de mise pour récupérer le capital
watch(betTypeValue, async (newValue) => {
  if (newValue === 'percentage') {
    await fetchCurrentCapital();
  }
  calculatePercentageStake();
});

// Surveiller les changements de la mise pour recalculer en mode pourcentage
watch(() => formData.value.stake, () => {
  calculatePercentageStake();
});

// Surveiller les changements dans les résultats des événements
watch(
  () => [events.value.map(e => e.result), currentEvent.value.result],
  () => {
    calculateGlobalResult();
  },
  { deep: true }
);

// Surveiller les changements de sport pour réinitialiser le type de pari
watch(
  () => eventCards.value.map(event => event.sport_id),
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

/**
 * Charger les sports au moment de l'ouverture de la modal
 * Cette méthode est appelée par AddBetDialog lors de l'ouverture
 */
async function loadSportsOnModalOpen() {
  console.log('🚀 Chargement des sports au clic sur la modal');
  
  // Charger les sports si pas encore chargés
  if (!availableSports.value || availableSports.value.length === 0) {
    await loadSports();
  } else {
    console.log('📋 Sports déjà chargés (', availableSports.value.length, 'sports)');
  }
}

// Exposer la méthode pour qu'elle soit accessible depuis le parent
defineExpose({
  loadSportsOnModalOpen
});

// Fonction d'initialisation asynchrone
async function initializeComponent() {
  // Charger les sports et les pays au montage
  await loadSports();
  await loadCountries();
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

