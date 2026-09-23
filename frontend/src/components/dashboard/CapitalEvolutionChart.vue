<template>
  <!-- Skeleton de chargement initial -->
  <div v-if="initialLoading" class="space-y-6">
    <!-- Skeleton du header -->
    <div class="card gap-2">
      <div class="flex justify-between items-center mb-4">
        <Skeleton width="12rem" height="2rem" />
        <div class="flex gap-2">
          <Skeleton width="2.5rem" height="2rem" class="rounded" v-for="n in 6" :key="n" />
        </div>
      </div>

      <!-- Skeleton des filtres -->
      <div class="grid grid-cols-1 gap-5 mb-4 md:grid-cols-5">
        <div class="field" v-for="n in 5" :key="n">
          <Skeleton width="6rem" height="1rem" class="mb-2" />
          <Skeleton width="100%" height="3rem" class="rounded" />
        </div>
      </div>

      <!-- Skeleton du graphique -->
      <div class="relative">
        <Skeleton width="100%" height="400px" class="rounded" />
      </div>
    </div>

    <!-- Skeleton des statistiques -->
    <div class="grid grid-cols-12 gap-2 lg:gap-8 text-center">
      <div class="card col-span-6 lg:col-span-6 xl:col-span-3" v-for="n in 4" :key="n">
        <Skeleton width="6rem" height="2rem" class="mb-2 mx-auto" />
        <Skeleton width="4rem" height="1rem" class="mx-auto" />
      </div>
    </div>
  </div>

  <!-- Contenu principal -->
  <div v-else class="space-y-6">
    <div class="card gap-2">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-semibold">Évolution du capital</h3>
        <div class="flex gap-2">
          <Button
            v-for="period in periods"
            :key="period.value"
            :label="period.label"
            :class="
              selectedPeriod === period.value
                ? 'p-button-primary'
                : 'p-button-outlined'
            "
            @click="changePeriod(period.value)"
            size="small"
          />
        </div>
      </div>

    <!-- Filtres avancés -->
    <div
      class="grid grid-cols-1 gap-5 mb-4"
      :class="isInvestor ? 'md:grid-cols-2' : 'md:grid-cols-5'"
    >
    <div class="field">
        <label class="block text-sm font-medium mb-2">Bankroll</label>
        <MultiSelect
          v-model="selectedBankrolls"
          :options="bankrollOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Toutes les bankrolls"
          class="w-full"
          :class="selectedBankrolls.length > 0 ? 'border-blue-500' : ''"
        />
      </div>
      <div class="field">
        <label class="block text-sm font-medium mb-2">Type de pari</label>
        <MultiSelect
          v-model="selectedBetTypes"
          :options="betTypes"
          optionLabel="label"
          optionValue="value"
          placeholder="Tous les types"
          class="w-full"
          :class="selectedBetTypes.length > 0 ? 'border-blue-500' : ''"
        />
      </div>
      <div class="field">
        <label class="block text-sm font-medium mb-2">Sport</label>
        <MultiSelect
          v-model="selectedSports"
          :options="sports"
          optionLabel="label"
          optionValue="value"
          placeholder="Tous les sports"
          class="w-full"
          :class="selectedSports.length > 0 ? 'border-blue-500' : ''"
        />
      </div>
      <div v-if="!isInvestor" class="field">
        <label class="block text-sm font-medium mb-2">Bookmaker</label>
        <MultiSelect
          v-model="selectedBookmakers"
          :options="bookmakers"
          optionLabel="label"
          optionValue="value"
          placeholder="Tous les bookmakers"
          class="w-full"
          :class="selectedBookmakers.length > 0 ? 'border-blue-500' : ''"
        />
      </div>
      <div v-if="!isInvestor" class="field">
        <label class="block text-sm font-medium mb-2">Tipster</label>
        <MultiSelect
          v-model="selectedTipsters"
          :options="tipsters"
          optionLabel="label"
          optionValue="value"
          placeholder="Tous les tipsters"
          class="w-full"
          :class="selectedTipsters.length > 0 ? 'border-blue-500' : ''"
        />
      </div>
    </div>

    <!-- Simulation de gain journalier (ligne de repère) -->
    <div class="flex flex-wrap items-end gap-4 mb-4 p-3 bg-surface-50 dark:bg-surface-800/50 rounded-lg">
      <div class="flex items-center gap-2">
        <ToggleSwitch v-model="simulationEnabled" inputId="simulation-toggle" />
        <label for="simulation-toggle" class="text-sm font-medium cursor-pointer">Simuler un gain journalier</label>
      </div>
      <div class="field" :class="{ 'opacity-50 pointer-events-none': !simulationEnabled }">
        <label class="block text-sm font-medium mb-2">Gain journalier (%)</label>
        <InputNumber
          v-model="simulationDailyRate"
          :min="-100"
          :max="100"
          :minFractionDigits="0"
          :maxFractionDigits="2"
          suffix=" %"
          class="w-40"
        />
      </div>
      <div class="field" :class="{ 'opacity-50 pointer-events-none': !simulationEnabled }">
        <label class="block text-sm font-medium mb-2">Date de fin</label>
        <DatePicker v-model="simulationEndDate" dateFormat="dd/mm/yy" showIcon class="w-44" />
      </div>
    </div>

    <!-- Indicateur de filtres actifs -->
    <div
      v-if="hasActiveFilters"
      class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg"
    >
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-2">
          <i class="pi pi-filter text-blue-600"></i>
          <span class="text-sm font-medium text-blue-800">Filtres actifs</span>
        </div>
        <Button
          label="Effacer tous les filtres"
          @click="clearAllFilters"
          size="small"
          class="p-button-text p-button-sm"
        />
      </div>

      <!-- Badges des filtres actifs -->
      <div class="flex flex-wrap gap-2">
        <div
          v-for="sport in selectedSports"
          :key="`sport-${sport}`"
          class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full"
        >
          {{ getSportLabel(sport) }}
        </div>
        <div
          v-for="betType in selectedBetTypes"
          :key="`bet-${betType}`"
          class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full"
        >
          {{ getBetTypeLabel(betType) }}
        </div>
        <div
          v-if="!isInvestor"
          v-for="bookmaker in selectedBookmakers"
          :key="`book-${bookmaker}`"
          class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full"
        >
          {{ getBookmakerLabel(bookmaker) }}
        </div>
        <div
          v-if="!isInvestor"
          v-for="tipster in selectedTipsters"
          :key="`tip-${tipster}`"
          class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full"
        >
          {{ getTipsterLabel(tipster) }}
        </div>
      </div>
    </div>

    <!-- Graphique -->
    <div class="relative">
      <!-- Indicateur de chargement -->
      <div
        v-if="loading"
        class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10"
      >
        <div class="flex items-center gap-2">
          <i class="pi pi-spin pi-spinner text-blue-600"></i>
          <span class="text-blue-600">Chargement des données...</span>
        </div>
      </div>

      <!-- Message d'erreur -->
      <div
        v-if="error"
        class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg"
      >
        <div class="flex items-center gap-2">
          <i class="pi pi-exclamation-triangle text-red-600"></i>
          <span class="text-red-800">{{ error }}</span>
        </div>
      </div>

      <Chart
        type="line"
        :data="displayChartData"
        :options="chartOptions"
        class="w-full"
        style="height: 400px"
      />

      <!-- Contrôles de zoom -->
      <div class="absolute top-2 right-2 flex gap-2">
        <Button
          icon="pi pi-plus"
          @click="zoomIn"
          size="small"
          class="p-button-rounded p-button-text"
          title="Zoom +"
        />
        <Button
          icon="pi pi-minus"
          @click="zoomOut"
          size="small"
          class="p-button-rounded p-button-text"
          title="Zoom -"
        />
        <Button
          icon="pi pi-refresh"
          @click="resetZoom"
          size="small"
          class="p-button-rounded p-button-text"
          title="Reset zoom"
        />
      </div>
      </div>
    </div>
    <!-- Statistiques -->
    <div class="grid grid-cols-12 gap-2 lg:gap-8 text-center">
    <div class="card col-span-6 lg:col-span-6 xl:col-span-3">
      <div class="text-2xl font-bold text-blue-600">
        {{ formatCurrency(currentCapital) }}
      </div>
      <div class="text-sm">Capital actuel</div>
    </div>
    <div class="card col-span-6 lg:col-span-3 xl:col-span-3">
      <div
        class="text-2xl font-bold"
        :class="totalProfit < 0 ? 'text-red-600' : 'text-green-600'"
      >
        {{ formatCurrency(totalProfit) }}
      </div>
      <div class="text-sm text-gray-600">Profit total</div>
    </div>
    <div class="card col-span-6 lg:col-span-3 xl:col-span-3">
      <div
        class="text-2xl font-bold"
        :class="profitPercentage < 0 ? 'text-red-600' : 'text-green-600'"
      >
        {{ profitPercentage }}%
      </div>
      <div class="text-sm text-gray-600">Rendement</div>
    </div>
    <div class="card col-span-6 lg:col-span-3 xl:col-span-3">
      <div class="text-2xl font-bold text-blue-600">{{ totalBets }}</div>
      <div class="text-sm text-gray-600">Total paris</div>
    </div>
    </div>
  </div>
</template>

<script setup>
// ===== IMPORTS =====
// Vue 3 Composition API
import { ref, computed, onMounted, watch } from "vue";

// PrimeVue Components
import Chart from "primevue/chart";
import Button from "primevue/button";
import MultiSelect from "primevue/multiselect";
import Skeleton from "primevue/skeleton";
import ToggleSwitch from "primevue/toggleswitch";
import InputNumber from "primevue/inputnumber";
import DatePicker from "primevue/datepicker";

// Services
import { BetService } from "@/service/BetService";
import { BankrollService } from "@/service/BankrollService";

// Composables
import { useAuth } from "@/composables/useAuth.js";

// ===== COMPOSABLES & AUTH =====
const { isInvestor } = useAuth();

// ===== ÉTAT LOCAL =====
// États de chargement et d'erreur
const loading = ref(false); // Chargement lors des mises à jour
const initialLoading = ref(true); // Chargement initial complet
const error = ref(null);

// États des filtres utilisateur
const selectedPeriod = ref("all");
const selectedBetTypes = ref([]);
const selectedSports = ref([]);
const selectedBankrolls = ref([]);
const selectedBookmakers = ref([]);
const selectedTipsters = ref([]);

// Données des options de filtres (chargées depuis l'API)
const betTypes = ref([]);
const sports = ref([]);
const bookmakers = ref([]);
const tipsters = ref([]);
const bankrollOptions = ref([]);

// ===== CONFIGURATION STATIQUE =====
// Périodes disponibles pour le filtrage temporel
const periods = [
  { label: "7j", value: "7j" },
  { label: "30j", value: "30j" },
  { label: "3m", value: "3m" },
  { label: "6m", value: "6m" },
  { label: "1an", value: "1an" },
  { label: "Tout", value: "all" },
];

// ===== DONNÉES DU GRAPHIQUE =====
// Configuration des données Chart.js
const chartData = ref({
  labels: [], // Dates pour l'axe X
  datasets: [
    {
      label: "Capital cumulé",
      data: [], // Valeurs du capital pour chaque date
      borderColor: "#3B82F6", // Couleur de la ligne
      backgroundColor: "rgba(59, 130, 246, 0.1)", // Couleur de remplissage
      borderWidth: 4,
      fill: true,
      tension: 0.4, // Courbe lissée
      pointRadius: 0, // Masquer les points par défaut
      pointHoverRadius: 6, // Taille des points au survol
    },
  ],
});

// Données brutes reçues de l'API
const apiData = ref({
  initial_capital: 0,
  current_capital: 0,
  total_profit_loss: 0,
  total_profit_loss_percentage: 0,
});

// Capital initial calculé depuis les bankrolls utilisateur
const userInitialCapital = ref(0);

// États de la simulation de gain journalier (ligne de repère)
const simulationEnabled = ref(false);
const simulationDailyRate = ref(1);
const simulationEndDate = ref(null);

// Configuration avancée du graphique Chart.js
const chartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      mode: "index",
      intersect: false,
      callbacks: {
        label: function (context) {
          return `Capital: ${formatCurrency(context.parsed.y)}`;
        },
      },
    },
  },
  scales: {
    x: {
      display: true,
      title: {
        display: true,
        text: "Date",
      },
      grid: {
        display: false,
      },
      ticks: {
        maxTicksLimit: 6,
        autoSkip: true,
        autoSkipPadding: 50,
      },
    },
    y: {
      display: true,
      title: {
        display: true,
        text: "Capital (€)",
      },
      grid: {
        color: "rgba(0, 0, 0, 0.1)",
      },
      ticks: {
        callback: function (value) {
          return formatCurrency(value);
        },
      },
    },
  },
  interaction: {
    mode: "nearest",
    axis: "x",
    intersect: false,
  },
});

// ===== FONCTIONS UTILITAIRES =====
/**
 * Formate une valeur numérique en devise EUR
 * @param {number} value - La valeur à formater
 * @returns {string} Valeur formatée en euros (ex: "1 234,56 €")
 */
const formatCurrency = (value) => {
  return new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(value);
};

/**
 * Fonctions pour obtenir les labels lisibles des filtres
 * Utilisées pour l'affichage des badges de filtres actifs
 */
const getSportLabel = (value) => {
  const sport = sports.value.find((s) => s.value === value);
  return sport ? sport.label : value;
};

const getBetTypeLabel = (value) => {
  const betType = betTypes.value.find((b) => b.value === value);
  return betType ? betType.label : value;
};

const getBookmakerLabel = (value) => {
  const bookmaker = bookmakers.value.find((b) => b.value === value);
  return bookmaker ? bookmaker.label : value;
};

const getTipsterLabel = (value) => {
  const tipster = tipsters.value.find((t) => t.value === value);
  return tipster ? tipster.label : value;
};

// ===== SIMULATION DE GAIN JOURNALIER =====
const MS_PER_DAY = 24 * 60 * 60 * 1000;

/**
 * Parse une date au format "dd/mm/yyyy" (labels normaux) ou "dd/mm" (fallback
 * backend quand l'utilisateur n'a encore aucun pari — cf. BetController::capitalEvolution).
 * Retombe sur l'année en cours quand elle est absente.
 */
const parseFrDate = (str) => {
  const parts = str.split("/").map(Number);
  const [d, m, y] = parts;
  return new Date(y ?? new Date().getFullYear(), m - 1, d);
};

/** Formate une date au format "dd/mm/yyyy" pour rester cohérent avec les labels existants */
const formatFrDate = (date) => {
  const d = String(date.getDate()).padStart(2, "0");
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const y = date.getFullYear();
  return `${d}/${m}/${y}`;
};

// Initialise une date de fin par défaut (90 jours après la dernière date réelle) dès l'activation
watch(simulationEnabled, (enabled) => {
  if (enabled && !simulationEndDate.value) {
    const labels = chartData.value.labels;
    const parsedLastReal = labels.length ? parseFrDate(labels[labels.length - 1]) : null;
    const lastReal = parsedLastReal && !isNaN(parsedLastReal.getTime()) ? parsedLastReal : new Date();
    const defaultEnd = new Date(lastReal);
    defaultEnd.setDate(defaultEnd.getDate() + 90);
    simulationEndDate.value = defaultEnd;
  }
});

/**
 * Données du graphique enrichies de la ligne de simulation, si activée.
 * Part de la même date de départ que les données réelles (capital initial),
 * applique une croissance composée journalière de X% jusqu'à la date de fin choisie.
 */
const displayChartData = computed(() => {
  const base = chartData.value;

  if (!simulationEnabled.value || !base.labels.length) {
    return base;
  }

  const realDates = base.labels.map(parseFrDate);
  const startDate = realDates[0];
  const startCapital = base.datasets[0].data[0] ?? userInitialCapital.value ?? 0;
  const realEndDate = realDates[realDates.length - 1];
  const simEndDate = simulationEndDate.value ? new Date(simulationEndDate.value) : realEndDate;
  const finalEndDate = simEndDate.getTime() > realEndDate.getTime() ? simEndDate : realEndDate;

  const allDates = [];
  for (let t = startDate.getTime(); t <= finalEndDate.getTime(); t += MS_PER_DAY) {
    allDates.push(new Date(t));
  }

  const realValuesByTime = new Map();
  realDates.forEach((d, i) => realValuesByTime.set(d.getTime(), base.datasets[0].data[i]));

  const realData = allDates.map((d) =>
    realValuesByTime.has(d.getTime()) ? realValuesByTime.get(d.getTime()) : null
  );

  const rate = (simulationDailyRate.value || 0) / 100;
  const simData = allDates.map((d) => {
    if (d.getTime() < startDate.getTime() || d.getTime() > simEndDate.getTime()) {
      return null;
    }
    const dayIndex = Math.round((d.getTime() - startDate.getTime()) / MS_PER_DAY);
    return startCapital * Math.pow(1 + rate, dayIndex);
  });

  return {
    labels: allDates.map(formatFrDate),
    datasets: [
      { ...base.datasets[0], data: realData },
      {
        label: `Simulation (${simulationDailyRate.value}%/jour)`,
        data: simData,
        borderColor: "#F59E0B",
        backgroundColor: "transparent",
        borderDash: [6, 4],
        borderWidth: 2,
        fill: false,
        tension: 0.4,
        pointRadius: 0,
        pointHoverRadius: 4,
      },
    ],
  };
});

// ===== COMPUTED PROPERTIES =====
/**
 * Statistiques calculées à partir des données API
 */
const currentCapital = computed(() => {
  return apiData.value.current_capital || 0;
});

const totalProfit = computed(() => {
  return apiData.value.total_profit_loss || 0;
});

const profitPercentage = computed(() => {
  return apiData.value.total_profit_loss_percentage
    ? Math.round(apiData.value.total_profit_loss_percentage * 10) / 10
    : 0;
});

const totalBets = computed(() => {
  // TODO: Remplacer par des données réelles depuis l'API
  // Simulation du nombre de paris basé sur la période sélectionnée
  const periodDays = {
    "7j": 7,
    "30j": 30,
    "3m": 90,
    "6m": 180,
    "1an": 365,
    all: 730,
  };
  return Math.floor(periodDays[selectedPeriod.value] * 1.5); // 1.5 paris par jour en moyenne
});

/**
 * Détermine si des filtres sont actuellement actifs
 * Utilisé pour afficher l'indicateur de filtres et le bouton d'effacement
 */
const hasActiveFilters = computed(() => {
  const baseFilters =
    selectedBetTypes.value.length > 0 || selectedSports.value.length > 0;
  const advancedFilters = isInvestor.value
    ? false
    : selectedBookmakers.value.length > 0 || selectedTipsters.value.length > 0;
  return baseFilters || advancedFilters;
});

// ===== MÉTHODES DE CONTRÔLE =====
/**
 * Modifie la période sélectionnée et met à jour le graphique
 * @param {string} period - Nouvelle période ('7j', '30j', '3m', '6m', '1an', 'all')
 */
const changePeriod = (period) => {
  selectedPeriod.value = period;
  updateChartData();
};

/**
 * Met à jour les données du graphique en fonction des filtres appliqués
 * Appelle l'API ou utilise des données simulées en cas d'erreur
 */
const updateChartData = async () => {
  loading.value = true;
  error.value = null;

  try {
    const filters = {
      period: selectedPeriod.value,
      sports: selectedSports.value,
      betTypes: selectedBetTypes.value,
      bankrolls: selectedBankrolls.value,
      bookmakers: selectedBookmakers.value,
      tipsters: selectedTipsters.value,
    };

    const response = await BetService.getCapitalEvolution(filters);

    console.log(response);

    // Mettre à jour les données du graphique
    chartData.value.labels = response.labels || [];
    chartData.value.datasets[0].data = response.data || [];

    // Mettre à jour les données de l'API pour les calculs
    apiData.value.initial_capital = response.initial_capital || 1000;
    apiData.value.current_capital = response.current_capital || 1000;
    apiData.value.total_profit_loss = response.total_profit_loss || 0;
    apiData.value.total_profit_loss_percentage =
      response.total_profit_loss_percentage || 0;
  } catch (err) {
    console.error("Erreur lors de la récupération des données:", err);
    error.value = "Erreur lors du chargement des données";

    // Fallback vers les données simulées en cas d'erreur
    const { data, labels } = generateMockData(selectedPeriod.value, {
      sports: selectedSports.value,
      betTypes: selectedBetTypes.value,
      bookmakers: selectedBookmakers.value,
      tipsters: selectedTipsters.value,
    });
    chartData.value.labels = labels;
    chartData.value.datasets[0].data = data;

    // Mettre à jour les données simulées pour les calculs
    const initialCapital = userInitialCapital.value || 1000;
    const currentCapital =
      data.length > 0 ? data[data.length - 1] : initialCapital;
    const totalProfit = currentCapital - initialCapital;
    const profitPercentage =
      initialCapital > 0 ? (totalProfit / initialCapital) * 100 : 0;

    apiData.value.initial_capital = initialCapital;
    apiData.value.current_capital = currentCapital;
    apiData.value.total_profit_loss = totalProfit;
    apiData.value.total_profit_loss_percentage = profitPercentage;
  } finally {
    loading.value = false;
    // Marquer le chargement initial comme terminé après la première requête
    
    if (initialLoading.value) {
      initialLoading.value = false;
    }
  }
};

/**
 * Génère des données de démonstration pour le graphique
 * Utilisé comme fallback en cas d'erreur API ou pour les tests
 * @param {string} period - Période sélectionnée
 * @param {Object} filters - Filtres appliqués (sports, betTypes, etc.)
 * @returns {Object} Objet contenant {data, labels} pour le graphique
 */
const generateMockData = (period, filters = {}) => {
  const data = [];
  const labels = [];
  let days = 30;

  switch (period) {
    case "7j":
      days = 7;
      break;
    case "3m":
      days = 90;
      break;
    case "6m":
      days = 180;
      break;
    case "1an":
      days = 365;
      break;
    case "all":
      days = 730;
      break;
  }

  let capital = userInitialCapital.value || 1000;

  // Génération des données jour par jour depuis 'days' jours en arrière
  for (let i = days; i >= 0; i--) {
    const date = new Date();
    date.setDate(date.getDate() - i);

    // Variation quotidienne aléatoire (légèrement positive en moyenne)
    let dailyChange = (Math.random() - 0.45) * 50;

    // Application des multiplicateurs selon les filtres (simulation réaliste)
    if (filters.sports && filters.sports.length > 0) {
      let sportMultiplier = 1.0;
      if (filters.sports.includes("football")) sportMultiplier = 1.3;
      else if (filters.sports.includes("basketball")) sportMultiplier = 1.2;
      else if (filters.sports.includes("tennis")) sportMultiplier = 0.8;
      else if (filters.sports.includes("baseball")) sportMultiplier = 1.1;
      else if (filters.sports.includes("hockey")) sportMultiplier = 0.9;
      dailyChange *= sportMultiplier;
    }

    if (filters.betTypes && filters.betTypes.length > 0) {
      let betTypeMultiplier = 1.0;
      if (filters.betTypes.includes("multiple")) betTypeMultiplier = 1.4;
      else if (filters.betTypes.includes("simple")) betTypeMultiplier = 0.7;
      else if (filters.betTypes.includes("combined")) betTypeMultiplier = 1.2;
      else if (filters.betTypes.includes("live")) betTypeMultiplier = 0.9;
      dailyChange *= betTypeMultiplier;
    }

    if (filters.bookmakers && filters.bookmakers.length > 0) {
      let bookmakerMultiplier = 1.0;
      if (filters.bookmakers.includes("bet365")) bookmakerMultiplier = 1.3;
      else if (filters.bookmakers.includes("william-hill"))
        bookmakerMultiplier = 1.2;
      else if (filters.bookmakers.includes("unibet")) bookmakerMultiplier = 1.1;
      else if (filters.bookmakers.includes("bwin")) bookmakerMultiplier = 0.9;
      else if (filters.bookmakers.includes("pmu")) bookmakerMultiplier = 0.8;
      dailyChange *= bookmakerMultiplier;
    }

    if (filters.tipsters && filters.tipsters.length > 0) {
      let tipsterMultiplier = 1.0;
      if (filters.tipsters.includes("expert1")) tipsterMultiplier = 1.4;
      else if (filters.tipsters.includes("expert2")) tipsterMultiplier = 1.1;
      else if (filters.tipsters.includes("expert3")) tipsterMultiplier = 0.7;
      dailyChange *= tipsterMultiplier;
    }

    // Appliquer un léger effet si un ou plusieurs bankrolls sont sélectionnés
    if (filters.bankrolls && filters.bankrolls.length > 0) {
      // Modulateur proportionnel au nombre de bankrolls sélectionnées
      const bankrollMultiplier = 1 + Math.min(filters.bankrolls.length, 5) * 0.03;
      dailyChange *= bankrollMultiplier;
    }

    capital += dailyChange;

    // S'assurer que le capital ne devient jamais négatif
    data.push(Math.max(0, capital));
    
    // Format de date français pour l'affichage
    labels.push(
      date.toLocaleDateString("fr-FR", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
      })
    );
  }

  return { data, labels };
};

// ===== CONTRÔLES DE ZOOM =====
// TODO: Implémenter la logique de zoom avec Chart.js
const zoomIn = () => {
  console.log("Zoom in - À implémenter");
};

const zoomOut = () => {
  console.log("Zoom out - À implémenter");
};

const resetZoom = () => {
  console.log("Reset zoom - À implémenter");
};

/**
 * Efface tous les filtres appliqués et recharge les données
 * Respecte les permissions utilisateur (investor vs admin)
 */
const clearAllFilters = () => {
  selectedBetTypes.value = [];
  selectedSports.value = [];
  
  // Les investisseurs n'ont pas accès aux filtres bookmaker/tipster
  if (!isInvestor.value) {
    selectedBookmakers.value = [];
    selectedTipsters.value = [];
  }
  
  updateChartData();
};

// ===== CHARGEMENT DES DONNÉES =====
/**
 * Récupère et calcule le capital initial total des bankrolls utilisateur
 * Utilisé comme référence pour les calculs de profit/perte
 */
const loadUserInitialCapital = async () => {
  try {
    const bankrolls = await BankrollService.getBankrolls();
    // Calculer la somme des capitals initiaux de toutes les bankrolls
    userInitialCapital.value = bankrolls.reduce((total, bankroll) => {
      return total + (parseFloat(bankroll.bankroll_start_amount) || 0);
    }, 0);

    // Préparer les options pour le filtre Bankroll
    bankrollOptions.value = (bankrolls || []).map((b) => ({
      label: b.bankroll_name || b.name || `Bankroll ${b.id}`,
      value: b.id,
    }));

    // Si aucune bankroll ou capital = 0, utiliser 1000 par défaut
    if (userInitialCapital.value <= 0) {
      userInitialCapital.value = 1000;
    }

    // Mettre à jour les données par défaut
    apiData.value.initial_capital = userInitialCapital.value;
    apiData.value.current_capital = userInitialCapital.value;
  } catch (err) {
    console.error("Erreur lors de la récupération du capital initial:", err);
    // Fallback vers 1000€ par défaut
    userInitialCapital.value = 1000;
    apiData.value.initial_capital = 1000;
    apiData.value.current_capital = 1000;
  }
};

/**
 * Charge les options disponibles pour les filtres depuis l'API
 * Avec fallback vers des données par défaut en cas d'erreur
 */
const loadFilterOptions = async () => {
  try {
    const options = await BetService.getFilterOptions();
    betTypes.value = options.data.betTypes || [];
    sports.value = options.data.sports || [];
    bookmakers.value = options.data.bookmakers || [];
    tipsters.value = options.data.tipsters || [];
  } catch (err) {
    console.error("Erreur lors du chargement des options de filtres:", err);
    // Fallback vers les options par défaut
    betTypes.value = [
      { label: "Simple", value: "simple" },
      { label: "Multiple", value: "multiple" },
      { label: "Combiné", value: "combined" },
      { label: "Live", value: "live" },
    ];
    sports.value = [
      { label: "Football", value: "football" },
      { label: "Basketball", value: "basketball" },
      { label: "Tennis", value: "tennis" },
      { label: "Baseball", value: "baseball" },
      { label: "Hockey", value: "hockey" },
    ];
    bookmakers.value = [
      { label: "Bet365", value: "bet365" },
      { label: "William Hill", value: "william-hill" },
      { label: "Unibet", value: "unibet" },
      { label: "Bwin", value: "bwin" },
      { label: "PMU", value: "pmu" },
    ];
    tipsters.value = [
      { label: "Expert1", value: "expert1" },
      { label: "Expert2", value: "expert2" },
      { label: "Expert3", value: "expert3" },
    ];
  }
};

// ===== WATCHERS & RÉACTIVITÉ =====
/**
 * Surveille les modifications des filtres et met à jour le graphique automatiquement
 * Utilise deep: true pour surveiller les changements dans les tableaux
 */
watch(
  [selectedBetTypes, selectedSports, selectedBankrolls, selectedBookmakers, selectedTipsters],
  () => {
    console.log("Filtres mis à jour:", {
      betTypes: selectedBetTypes.value,
      sports: selectedSports.value,
      bankrolls: selectedBankrolls.value,
      bookmakers: selectedBookmakers.value,
      tipsters: selectedTipsters.value,
    });
    updateChartData();
  },
  { deep: true }
);

// ===== LIFECYCLE & INITIALISATION =====
/**
 * Fonction d'initialisation asynchrone du composant
 * Charge les données dans l'ordre correct : capital initial → options → données graphique
 */
async function initializeChart() {
  try {
    initialLoading.value = true; // Activer le skeleton pendant l'initialisation
    // Simuler un chargement lent de 5 secondes pour le développement / démo
    await new Promise((resolve) => setTimeout(resolve, 2000));
    await loadUserInitialCapital(); // Priorité au capital initial pour les calculs
    await loadFilterOptions(); // Charger les options de filtres
    await updateChartData(); // Enfin, charger les données du graphique
  } catch (err) {
    console.error('Erreur lors de l\'initialisation:', err);
    error.value = 'Erreur lors de l\'initialisation du composant';
    initialLoading.value = false; // Désactiver le skeleton même en cas d'erreur
  }
}

/**
 * Hook de cycle de vie - Initialisation du composant
 */
onMounted(() => {
  initializeChart();
});
</script>

<style scoped>
.field label {
  color: #374151;
}

.p-button-outlined {
  border-color: #d1d5db;
  color: #6b7280;
}

.p-button-outlined:hover {
  background-color: #f3f4f6;
  border-color: #9ca3af;
}
</style>
