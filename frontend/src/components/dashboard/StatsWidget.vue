<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import Chart from 'primevue/chart';
import { BetService } from '@/service/BetService';
import Tooltip from 'primevue/tooltip';

// Enregistrement des directives
const vTooltip = Tooltip;

const props = defineProps({
  period: { type: String, default: 'all' },
  sports: { type: Array, default: () => [] },
  betTypes: { type: Array, default: () => [] },
  bookmakers: { type: Array, default: () => [] },
  tipsters: { type: Array, default: () => [] },
  initialCapital: { type: Number, default: 1000 }
});

const loading = ref(false);
const error = ref('');

const apiCapital = ref({
  labels: [],
  capitals: [],
  details: [],
  initial_capital: 0,
  current_capital: 0,
  total_profit_loss: 0,
  total_profit_loss_percentage: 0
});

const apiStats = ref({
  total_bets: 0,
  total_stake: 0,
  total_wins: 0,
  total_losses: 0,
  total_profit_loss: 0,
  average_odds: 0,
  win_bets: 0,
  lost_bets: 0,
  pending_bets: 0,
  win_rate: 0,
  roi: 0
});

// Variables pour contrôler l'affichage des popups d'information
const showTotalBetsInfo = ref(false);
const showWinBetsInfo = ref(false);
const showLostBetsInfo = ref(false);
const showVoidBetsInfo = ref(false);
const showPendingBetsInfo = ref(false);
const showCancelledBetsInfo = ref(false);
const showInitialCapitalInfo = ref(false);
const showCurrentCapitalInfo = ref(false);
const showProfitInfo = ref(false);
const showRoiInfo = ref(false);
const showProgressionInfo = ref(false);
const showWinRateInfo = ref(false);
const showDrawdownInfo = ref(false)
const showTotalStakeInfo = ref(false)
const showInPlayStakeInfo = ref(false)
const showDepositInfo = ref(false)
const showWithdrawInfo = ref(false);
const showMaxWinStreakInfo = ref(false);
const showWinStreakInfo = ref(false);
const showMaxLoseStreakInfo = ref(false);
const showAvgStakeInfo = ref(false);
const showMaxStakeInfo = ref(false);
const showMinStakeInfo = ref(false);
const showAvgOddsInfo = ref(false);
const showBiggestWinOddsInfo = ref(false);
const showSmallestWinOddsInfo = ref(false);
const showBiggestProfitInfo = ref(false);
const showBiggestLossInfo = ref(false);

// Liste de toutes les variables de popup
const allPopupRefs = [
  showTotalBetsInfo, showWinBetsInfo, showLostBetsInfo, showVoidBetsInfo,
  showPendingBetsInfo, showCancelledBetsInfo, showInitialCapitalInfo, showCurrentCapitalInfo,
  showProfitInfo, showRoiInfo, showProgressionInfo, showWinRateInfo, showDrawdownInfo,
  showTotalStakeInfo, showInPlayStakeInfo, showDepositInfo, showWithdrawInfo,
  showMaxWinStreakInfo, showWinStreakInfo, showMaxLoseStreakInfo, showAvgStakeInfo,
  showMaxStakeInfo,
  showMinStakeInfo,
  showAvgOddsInfo, showBiggestWinOddsInfo, showSmallestWinOddsInfo, showBiggestProfitInfo, showBiggestLossInfo
];

// Gestionnaire pour fermer les popups en cliquant en dehors
function handleClickOutside(event) {
  const hasOpenPopup = allPopupRefs.some(ref => ref.value);
  if (hasOpenPopup && !event.target.closest('.stats-card-container')) {
    allPopupRefs.forEach(ref => ref.value = false);
  }
}

// Ajouter/supprimer l'écouteur d'événement quand une popup s'ouvre
const hasAnyPopupOpen = computed(() => allPopupRefs.some(ref => ref.value));
watch(hasAnyPopupOpen, (newVal) => {
  if (newVal) {
    document.addEventListener('click', handleClickOutside);
  } else {
    document.removeEventListener('click', handleClickOutside);
  }
});

// Nettoyer l'écouteur lors de la destruction du composant
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

// Nouvelles statistiques détaillées
const apiDetailedStats = ref({
  in_play_stake: null,
  max_stake: null,
  biggest_win_odds: null,
  biggest_profit: null,
  biggest_loss: null,
  max_win_streak: null,
  max_lose_streak: null,
  current_win_streak: 0,
  current_lose_streak: 0
});

// Statistiques des transactions
const apiTransactionStats = ref({
  total_deposits: null,
  total_withdrawals: null,
  net_deposits: null
});

const lineData = ref({ labels: [], datasets: [] });
const lineOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: true },
    tooltip: {
      callbacks: {
        label: (ctx) => {
          const i = ctx.dataIndex;
          const d = apiCapital.value.details[i];
          const val = ctx.parsed?.y ?? 0;
          if (d) {
            return [
              `Capital: ${val.toFixed(2)} €`,
              `Journée: ${Number(d.daily_profit_loss).toFixed(2)} €`,
              `Cumul: ${Number(d.cumulative_profit_loss).toFixed(2)} €`
            ];
          }
          return `Capital: ${val.toFixed(2)} €`;
        }
      }
    }
  },
  scales: {
    x: { ticks: { autoSkip: true, maxTicksLimit: 12 } },
    y: { beginAtZero: false }
  }
});

const filters = computed(() => {
  // Si votre select sports renvoie des objets {label, value}, mappez vers value
  const sportsValues = Array.isArray(props.sports)
      ? props.sports.map(s => (typeof s === 'object' && s !== null ? s.value : s))
      : [];

  return {
    period: props.period,
    sports: sportsValues,
    betTypes: props.betTypes,
    bookmakers: props.bookmakers,
    tipsters: props.tipsters
    // initial_capital: props.initialCapital, // à activer si utilisé côté API
  };
});

async function loadCapital() {
  const res = await BetService.getCapitalEvolution(filters.value);

  if (!res || res.success !== true) {
    throw new Error('Réponse capital-evolution invalide');
  }

  const labels = Array.isArray(res.labels) ? res.labels : [];
  const capitals = Array.isArray(res.data) ? res.data : [];
  const details = Array.isArray(res.capital_evolution) ? res.capital_evolution : [];

  apiCapital.value = {
    labels,
    capitals,
    details,
    initial_capital: res.initial_capital ?? props.initialCapital,
    current_capital: res.current_capital ?? (capitals.at(-1) ?? props.initialCapital),
    total_profit_loss: res.total_profit_loss ?? 0,
    total_profit_loss_percentage: res.total_profit_loss_percentage ?? 0
  };

  lineData.value = {
    labels,
    datasets: [
      {
        label: 'Évolution du capital',
        data: capitals,
        borderColor: '#3B82F6',
        backgroundColor: 'rgba(59, 130, 246, 0.2)',
        fill: true,
        tension: 0.25,
        pointRadius: 2,
        pointHoverRadius: 4
      }
    ]
  };
}

async function loadStats() {
    try {
      const res = await BetService.getBetStats(filters.value);
      
      if (!res || res.success !== true) {
        throw new Error('Réponse stats invalide');
      }
      
      apiStats.value = {
        ...apiStats.value,
        ...res.data
      };
    } catch (error) {
      console.error('Erreur dans loadStats:', error);
      throw error;
    }
  }

// Charger les statistiques détaillées
async function loadDetailedStats() {
  const res = await BetService.getDetailedStats(filters.value);
  if (!res || res.success !== true) {
    throw new Error('Réponse statistiques détaillées invalide');
  }
  apiDetailedStats.value = {
    ...apiDetailedStats.value,
    ...res.data
  };
}

// Charger les statistiques des transactions
async function loadTransactionStats() {
  const res = await BetService.getTransactionStats(filters.value);
  if (!res || res.success !== true) {
    throw new Error('Réponse statistiques de transactions invalide');
  }
  apiTransactionStats.value = {
    ...apiTransactionStats.value,
    ...res.data
  };
}

function computeDrawdown(capitals) {
  if (!capitals || capitals.length === 0) return 0;
  let peak = capitals[0];
  let maxDD = 0; // en euros
  for (let i = 1; i < capitals.length; i++) {
    const v = capitals[i];
    if (v > peak) {
      peak = v;
    } else {
      const dd = peak - v;
      if (dd > maxDD) maxDD = dd;
    }
  }
  return Number(maxDD.toFixed(2));
}

const computedStats = computed(() => {
    const s = apiStats.value;
    const c = apiCapital.value;
    const d = apiDetailedStats.value;
    const t = apiTransactionStats.value;

    const totalBets = Number(s.total_bets) || 0;
    
    const win = s.win_bets || 0;
    const lost = s.lost_bets || 0;
  const pending = s.pending_bets || 0;
  const voided = Math.max(totalBets - win - lost - pending, 0);

  const initial = c.initial_capital || props.initialCapital;
  const current = c.current_capital || initial;
  const progressionPct = initial ? ((current - initial) / initial) * 100 : 0;

  const drawdown = computeDrawdown(c.capitals);

  const avgStake = totalBets ? (s.totalStake || s.total_stake || 0) / totalBets : 0;

  return {
    totalBets,
    profit: Number(c.total_profit_loss ?? s.total_profit_loss ?? 0),
    roi: Number(s.roi) || 0,
    progressionPct,
    winRate: Number(s.win_rate) || 0,
    drawdown,
    initialCapital: initial,
    currentCapital: current,
    winBets: win,
    lostBets: lost,
    voidBets: voided,
    pendingBets: pending,
    totalStake: Number(s.total_stake) || 0,
    // Maintenant disponibles via l'API détaillée:
    inPlayStake: Number(d.in_play_stake) || 0,
    deposit: Number(t.total_deposits) || 0,     // Dépôt depuis les statistiques de transactions
    withdraw: Number(t.total_withdrawals) || 0,    // Retrait depuis les statistiques de transactions
    maxWinStreak: Number(d.max_win_streak) || 0,
    maxLoseStreak: Number(d.max_lose_streak) || 0,
    avgStake,
    maxStake: Number(d.max_stake) || 0,
    minStake: Number(d.min_stake) || 0,
    avgOdds: Number(s.average_odds) || 0,
    biggestWinOdds: Number(d.biggest_win_odds) || 0,
    smallestWinOdds: Number(d.smallest_win_odds) || 0,
    biggestProfit: Number(d.biggest_profit) || 0,
    biggestLoss: Number(d.biggest_loss) || 0
  };
});

async function loadAll() {
  loading.value = true;
  error.value = '';
  try {
    await Promise.all([loadCapital(), loadStats(), loadDetailedStats(), loadTransactionStats()]);
  } catch (e) {
    console.error(e);
    error.value = 'Impossible de charger les données.';
  } finally {
    loading.value = false;
  }
}
onMounted(loadAll);
watch(filters, loadAll, { deep: true });
</script>

<template>
  <div class="grid grid-cols-12 gap-4 mt-6">
    <!-- Ligne 1 -->
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Paris Total
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showTotalBetsInfo = !showTotalBetsInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.totalBets }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showTotalBetsInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Paris Total</div>
        <div class="text-gray-600">
          Nombre total de paris placés sur la période sélectionnée, incluant les paris gagnés, perdus, remboursés, annulés et en cours.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <!-- Bouton de fermeture -->
        <button @click="showTotalBetsInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Paris gagnants
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showWinBetsInfo = !showWinBetsInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.winBets }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showWinBetsInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Paris gagnants</div>
        <div class="text-gray-600">
          Nombre de paris qui ont été gagnés sur la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showWinBetsInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Paris perdants
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showLostBetsInfo = !showLostBetsInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.lostBets }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showLostBetsInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Paris perdants</div>
        <div class="text-gray-600">
          Nombre de paris qui ont été perdus sur la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showLostBetsInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Paris remboursés
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showVoidBetsInfo = !showVoidBetsInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.voidBets }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showVoidBetsInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Paris remboursés</div>
        <div class="text-gray-600">
          Nombre de paris qui ont été annulés et remboursés par le bookmaker.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showVoidBetsInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Paris en attente
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showPendingBetsInfo = !showPendingBetsInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.pendingBets }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showPendingBetsInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Paris en attente</div>
        <div class="text-gray-600">
          Nombre de paris dont le résultat n'est pas encore connu ou en cours de traitement.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showPendingBetsInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Séries gagnantes
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showWinStreakInfo = !showWinStreakInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.maxWinStreak }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showWinStreakInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Séries gagnantes</div>
        <div class="text-gray-600">
          Nombre maximum de paris gagnés consécutivement sur la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showWinStreakInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>

    <!-- Ligne 2 -->
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Capital de départ
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showInitialCapitalInfo = !showInitialCapitalInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.initialCapital.toFixed(2) }}€</div>
      
      <!-- Popup d'information -->
      <div v-if="showInitialCapitalInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Capital de départ</div>
        <div class="text-gray-600">
          Montant initial avec lequel vous avez commencé vos paris.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showInitialCapitalInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Capital actuel
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showCurrentCapitalInfo = !showCurrentCapitalInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.currentCapital.toFixed(2) }}€</div>
      
      <!-- Popup d'information -->
      <div v-if="showCurrentCapitalInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Capital actuel</div>
        <div class="text-gray-600">
          Montant actuel de votre capital après tous les paris et transactions.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showCurrentCapitalInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Bénéfice
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showProfitInfo = !showProfitInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.profit.toFixed(2) }}€</div>
      
      <!-- Popup d'information -->
      <div v-if="showProfitInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Bénéfice</div>
        <div class="text-gray-600">
          Gain ou perte total réalisé sur la période sélectionnée (capital actuel - capital initial).
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showProfitInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        ROI
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showRoiInfo = !showRoiInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.roi.toFixed(2) }}%</div>
      
      <!-- Popup d'information -->
      <div v-if="showRoiInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">ROI (Return on Investment)</div>
        <div class="text-gray-600">
          Retour sur investissement calculé par rapport aux mises totales. Indique la rentabilité de vos paris.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showRoiInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Progression
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showProgressionInfo = !showProgressionInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.progressionPct.toFixed(2) }}%</div>
      
      <!-- Popup d'information -->
      <div v-if="showProgressionInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Progression</div>
        <div class="text-gray-600">
          Pourcentage d'évolution de votre capital par rapport au capital initial.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showProgressionInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Taux de réussite
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showWinRateInfo = !showWinRateInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.winRate.toFixed(2) }}%</div>
      
      <!-- Popup d'information -->
      <div v-if="showWinRateInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Taux de réussite</div>
        <div class="text-gray-600">
          Pourcentage de paris gagnés par rapport au nombre total de paris résolus.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showWinRateInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>

    <!-- Ligne 3 -->
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Drawdown
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showDrawdownInfo = !showDrawdownInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.drawdown.toFixed(2) }}€</div>
      
      <!-- Popup d'information -->
      <div v-if="showDrawdownInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Drawdown</div>
        <div class="text-gray-600">
          Perte maximale subie depuis le pic le plus élevé de votre capital. Indique le risque de vos stratégies.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showDrawdownInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Mises jouées
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showTotalStakeInfo = !showTotalStakeInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ (computedStats.totalStake == null || isNaN(computedStats.totalStake)) ? '—' : (computedStats.totalStake.toFixed(2) + '€') }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showTotalStakeInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Mises jouées</div>
        <div class="text-gray-600">
          Montant total des mises placées sur tous vos paris durant la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showTotalStakeInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Mises en cours
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showInPlayStakeInfo = !showInPlayStakeInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.inPlayStake == null ? '—' : (computedStats.inPlayStake.toFixed(2) + '€') }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showInPlayStakeInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Mises en cours</div>
        <div class="text-gray-600">
          Montant total des mises sur les paris qui sont encore en attente de résultat.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showInPlayStakeInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Mise moyenne
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showAvgStakeInfo = !showAvgStakeInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.avgStake.toFixed(2) }}€</div>
      
      <!-- Popup d'information -->
      <div v-if="showAvgStakeInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Mise moyenne</div>
        <div class="text-gray-600">
          Montant moyen des mises placées sur vos paris durant la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showAvgStakeInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Mise max
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showMaxStakeInfo = !showMaxStakeInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.maxStake == null ? '—' : (computedStats.maxStake.toFixed(2) + '€') }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showMaxStakeInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Mise max</div>
        <div class="text-gray-600">
          Montant le plus élevé que vous avez misé sur un seul pari durant la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showMaxStakeInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Mise min
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showMinStakeInfo = !showMinStakeInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.minStake == null ? '—' : (computedStats.minStake.toFixed(2) + '€') }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showMinStakeInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Mise min</div>
        <div class="text-gray-600">
          Montant le plus faible que vous avez misé sur un seul pari durant la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showMinStakeInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Dépôt
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showDepositInfo = !showDepositInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.deposit == null ? '—' : (computedStats.deposit.toFixed(2) + '€') }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showDepositInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Dépôt</div>
        <div class="text-gray-600">
          Montant total des dépôts effectués sur votre compte durant la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showDepositInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Retrait
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showWithdrawInfo = !showWithdrawInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.withdraw == null ? '—' : (computedStats.withdraw.toFixed(2) + '€') }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showWithdrawInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Retrait</div>
        <div class="text-gray-600">
          Montant total des retraits effectués de votre compte durant la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showWithdrawInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <!-- Ligne 4 -->
    

    <!-- Ligne 5 -->
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Série victoires max
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showMaxWinStreakInfo = !showMaxWinStreakInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.maxWinStreak ?? '—' }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showMaxWinStreakInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Série victoires max</div>
        <div class="text-gray-600">
          Nombre maximum de paris gagnants consécutifs que vous avez réalisé.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showMaxWinStreakInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Série défaites max
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showMaxLoseStreakInfo = !showMaxLoseStreakInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.maxLoseStreak ?? '—' }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showMaxLoseStreakInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Série défaites max</div>
        <div class="text-gray-600">
          Nombre maximum de paris perdants consécutifs que vous avez subi.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showMaxLoseStreakInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    
    

    <!-- Ligne 6 -->
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Cote moyenne
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showAvgOddsInfo = !showAvgOddsInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ Number(computedStats.avgOdds || 0).toFixed(3) }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showAvgOddsInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Cote moyenne</div>
        <div class="text-gray-600">
          Cote moyenne de tous vos paris durant la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showAvgOddsInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Plus grosse cote gagnée
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showBiggestWinOddsInfo = !showBiggestWinOddsInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.biggestWinOdds ?? '—' }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showBiggestWinOddsInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Plus grosse cote gagnée</div>
        <div class="text-gray-600">
          La cote la plus élevée sur laquelle vous avez gagné durant la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showBiggestWinOddsInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Plus petite cote gagnée
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showSmallestWinOddsInfo = !showSmallestWinOddsInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.smallestWinOdds ?? '—' }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showSmallestWinOddsInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Plus petite cote gagnée</div>
        <div class="text-gray-600">
          La cote la plus faible sur laquelle vous avez gagné durant la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showSmallestWinOddsInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Plus gros bénéfice
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showBiggestProfitInfo = !showBiggestProfitInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.biggestProfit == null ? '—' : (computedStats.biggestProfit.toFixed(2) + '€') }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showBiggestProfitInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Plus gros bénéfice</div>
        <div class="text-gray-600">
          Le gain le plus important réalisé sur un seul pari durant la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showBiggestProfitInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
    <div class="col-span-12 md:col-span-2 card p-4 relative stats-card-container">
      <div class="text-muted-color text-sm flex items-center gap-2">
        Plus grosse perte
        <i class="pi pi-info-circle text-xs cursor-pointer hover:text-primary" 
           @click="showBiggestLossInfo = !showBiggestLossInfo"
           v-tooltip.top="'Cliquez pour plus d\'informations'"></i>
      </div>
      <div class="text-2xl font-semibold">{{ computedStats.biggestLoss == null ? '—' : (computedStats.biggestLoss.toFixed(2) + '€') }}</div>
      
      <!-- Popup d'information -->
      <div v-if="showBiggestLossInfo" 
           class="absolute z-10 mt-2 p-3 bg-white border border-gray-200 rounded-lg shadow-lg text-sm max-w-xs"
           style="top: 100%; left: 0;"
           @click.stop>
        <div class="font-semibold mb-1">Plus grosse perte</div>
        <div class="text-gray-600">
          La perte la plus importante subie sur un seul pari durant la période sélectionnée.
        </div>
        <div class="absolute -top-2 left-4 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
        <button @click="showBiggestLossInfo = false" 
                class="absolute top-1 right-1 text-gray-400 hover:text-gray-600 text-xs">
          <i class="pi pi-times"></i>
        </button>
      </div>
    </div>
  </div>
</template>