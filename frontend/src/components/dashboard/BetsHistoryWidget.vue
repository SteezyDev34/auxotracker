<script setup>
import { ref, onMounted, computed } from 'vue';
import { BetService } from '@/service/BetService';
import { format, parseISO, startOfWeek, endOfWeek } from 'date-fns';
import { fr } from 'date-fns/locale';
import AddBetDialog from './AddBetDialog.vue';
import Button from 'primevue/button';

// Variables réactives
const bets = ref([]);
const loading = ref(false);
const error = ref('');
const expandedMonths = ref(new Set());
const showAddBetDialog = ref(false);


// Charger tous les paris
async function loadBets() {
  loading.value = true;
  error.value = '';
  try {
    const response = await BetService.getBets();
    if (response.success) {
      bets.value = response.data || [];
    } else {
      error.value = 'Erreur lors du chargement des paris';
    }
  } catch (e) {
    console.error('Erreur lors du chargement des paris:', e);
    error.value = 'Impossible de charger les paris.';
  } finally {
    loading.value = false;
  }
}

// Organiser les paris par mois
const betsByMonth = computed(() => {
  const grouped = {};
  
  bets.value.forEach(bet => {
    const betDate = parseISO(bet.bet_date);
    const monthKey = format(betDate, 'yyyy-MM');
    const monthLabel = format(betDate, 'MMMM yyyy', { locale: fr });
    
    if (!grouped[monthKey]) {
      grouped[monthKey] = {
        label: monthLabel,
        bets: [],
        totalProfit: 0,
        totalStake: 0
      };
    }
    
    grouped[monthKey].bets.push(bet);
    grouped[monthKey].totalStake += parseFloat(bet.stake || 0);
    
    // Calculer le profit/perte
    if (bet.result === 'won') {
      grouped[monthKey].totalProfit += (parseFloat(bet.stake) * parseFloat(bet.global_odds)) - parseFloat(bet.stake);
    } else if (bet.result === 'lost') {
      grouped[monthKey].totalProfit -= parseFloat(bet.stake);
    }
  });
  
  // Trier par mois décroissant
  return Object.entries(grouped)
    .sort(([a], [b]) => b.localeCompare(a))
    .map(([key, value]) => ({ key, ...value }));
});



// Organiser les paris d'un mois par semaine
function getBetsByWeek(monthBets) {
  const grouped = {};
  
  monthBets.forEach(bet => {
    const betDate = parseISO(bet.bet_date);
    const weekStart = startOfWeek(betDate, { weekStartsOn: 1 }); // Lundi
    const weekKey = format(weekStart, 'yyyy-MM-dd');
    const weekLabel = `Semaine ${format(weekStart, 'w', { locale: fr })}`;
    const dateRange = `${format(weekStart, 'dd MMM', { locale: fr })} - ${format(endOfWeek(weekStart, { weekStartsOn: 1 }), 'dd MMM', { locale: fr })}`;
    
    if (!grouped[weekKey]) {
      grouped[weekKey] = {
        label: weekLabel,
        dateRange: dateRange,
        bets: []
      };
    }
    
    grouped[weekKey].bets.push(bet);
  });
  
  return Object.entries(grouped)
    .sort(([a], [b]) => b.localeCompare(a))
    .map(([key, value]) => ({ key, ...value }));
}

// Organiser les paris d'une semaine par jour
function getBetsByDay(weekBets) {
  const grouped = {};
  
  weekBets.forEach(bet => {
    const betDate = parseISO(bet.bet_date);
    const dayKey = format(betDate, 'yyyy-MM-dd');
    const dayLabel = format(betDate, 'EEEE dd MMMM', { locale: fr });
    
    if (!grouped[dayKey]) {
      grouped[dayKey] = {
        label: dayLabel,
        bets: []
      };
    }
    
    grouped[dayKey].bets.push(bet);
  });
  
  return Object.entries(grouped)
    .sort(([a], [b]) => b.localeCompare(a))
    .map(([key, value]) => ({ key, ...value }));
}

// Basculer l'expansion d'un mois
function toggleMonth(monthKey) {
  if (expandedMonths.value.has(monthKey)) {
    expandedMonths.value.delete(monthKey);
  } else {
    expandedMonths.value.add(monthKey);
  }
}



// Obtenir la couleur de la barre pour le résultat du pari
function getResultBarColor(result) {
  switch (result) {
    case 'won': return 'bg-green-500';
    case 'lost': return 'bg-red-500';
    case 'pending': return 'bg-gray-300';
    case 'void': return 'bg-gray-500';
    case 'refunded': return 'bg-blue-500';
    default: return 'bg-gray-400';
  }
}

// Obtenir l'icône SVG pour le résultat du pari
function getResultIcon(result) {
  switch (result) {
    case 'won': 
      return `<svg viewBox="0 0 24 24" class="w-6 h-6 text-white" fill="currentColor">
        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
      </svg>`;
    case 'lost': 
      return `<svg viewBox="0 0 24 24" class="w-6 h-6 text-white" fill="currentColor">
        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
      </svg>`;
    case 'pending': 
      return `<svg viewBox="0 0 24 24" class="w-6 h-6 text-white" fill="currentColor">
        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,6A1,1 0 0,1 13,7V12.41L15.18,14.59A1,1 0 0,1 14.59,16L12,13.41V7A1,1 0 0,1 12,6Z"/>
      </svg>`;
    case 'void': 
       return `<svg viewBox="0 0 24 24" class="w-6 h-6 text-white" fill="currentColor">
         <path d="M4,11H20V13H4V11Z"/>
       </svg>`;
    case 'refunded': 
      return `<svg viewBox="0 0 24 24" class="w-6 h-6 text-white" fill="currentColor">
        <path d="M20,11V13H8L13.5,18.5L12.08,19.92L4.16,12L12.08,4.08L13.5,5.5L8,11H20Z"/>
      </svg>`;
    default: 
      return `<svg viewBox="0 0 24 24" class="w-6 h-6 text-white" fill="currentColor">
        <path d="M11,9H13V7H11M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M11,17H13V11H11V17Z"/>
      </svg>`;
  }
}

// Calculer le profit/perte d'un pari
function calculateProfitLoss(bet) {
  if (bet.result === 'won') {
    return (parseFloat(bet.stake) * parseFloat(bet.global_odds)) - parseFloat(bet.stake);
  } else if (bet.result === 'lost') {
    return -parseFloat(bet.stake);
  }
  return 0;
}

// Obtenir le nom du match avec les logos des équipes
function getMatchName(bet) {
  if (bet.events && bet.events.length > 0) {
    const event = bet.events[0]; // Prendre le premier événement
    if (event.team1 && event.team2) {
      // Utiliser l'URL de l'API pour accéder aux logos d'équipes
      const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'https://api.auxotracker.lan';
      const team1Logo = event.team1.img ? `<img src="${apiBaseUrl}/storage/${event.team1.img}" alt="${event.team1.name}" class="w-6 h-6 rounded-full object-cover" />` : '';
      const team2Logo = event.team2.img ? `<img src="${apiBaseUrl}/storage/${event.team2.img}" alt="${event.team2.name}" class="w-6 h-6 rounded-full object-cover" />` : '';
      
      return {
        html: `
          <div class="flex items-center gap-2">
            ${team1Logo}
             <span class="font-medium">${event.team1.name}</span>
             <span class="text-gray-500 mx-1">vs</span>
             <span class="font-medium">${event.team2.name}</span>
             ${team2Logo}
          </div>
        `,
        text: `${event.team1.name} vs ${event.team2.name}`
      };
    }
  }
  return {
    html: `<span>${bet.match_name || 'Match non spécifié'}</span>`,
    text: bet.match_name || 'Match non spécifié'
  };
}

// Obtenir le nom de la ligue
function getLeagueName(bet) {
  if (bet.events && bet.events.length > 0) {
    const event = bet.events[0];
    if (event.league) {
      return event.league.name;
    }
  }
  return 'Ligue non spécifiée';
}

// Obtenir les informations de la ligue
function getLeagueInfo(bet) {
  if (bet.events && bet.events.length > 0) {
    const event = bet.events[0];
    if (event.league) {
      const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'https://api.auxotracker.lan';
      
      // Logo de la ligue
      const leagueLogo = event.league.img ? `<img src="${apiBaseUrl}/storage/${event.league.img}" alt="${event.league.name}" class="w-4 h-4 rounded object-cover" />` : '';
      
      return {
        html: `
          <div class="flex items-center">
            ${leagueLogo}
            <span>${event.league.name}</span>
          </div>
        `,
        text: event.league.name
      };
    }
  }
  return {
    html: '<span>Ligue non spécifiée</span>',
    text: 'Ligue non spécifiée'
  };
}

// Obtenir les informations du pays avec icône de sport
function getCountryInfo(bet) {
  if (bet.events && bet.events.length > 0) {
    const event = bet.events[0];
    if (event.league) {
      const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'https://api.auxotracker.lan';
      
      // Icône de sport (football par défaut) - SVG intégré
      const sportIcon = `<svg viewBox="0 0 32 32" class="w-5 h-5" focusable="false" role="img">
        <path d="M16 0a16 16 0 100 32 16 16 0 000-32zm1 5l5-3 2 1 2 2 1 1 1 1v1l-1 4-5 2-5-4zM4 7l1-1 1-1 2-2 2-1 5 3v5l-5 4-5-2-1-4V7zm0 17l-1-1-1-2v-1l-1-1v-2l3-4 6 2 1 7-2 3zm16 7h-2-1a14 14 0 01-2 0h-1-1l-3-5 2-4h8l2 4zm11-12l-1 1v1l-1 2-1 1-5 1-2-3 1-7 6-2 3 4v2z"></path>
      </svg>`;
      
      // Vérifier différentes structures possibles pour le pays
      let countryId = null;
      let countryName = null;
      
      if (event.league.country && event.league.country.id) {
        countryId = event.league.country.id;
        countryName = event.league.country.name;
      } else if (event.league.country_id) {
        countryId = event.league.country_id;
        countryName = event.league.country_name || 'Pays';
      }
      
      // Logo de la ligue
      const leagueLogo = event.league.img ? `<img src="${apiBaseUrl}/storage/${event.league.img}" alt="${event.league.name}" class="w-4 h-4 rounded object-cover" />` : '';
      
      if (countryId && countryName) {
        const countryLogo = `<img src="${apiBaseUrl}/storage/country_flags/${countryId}.png" alt="${countryName}" class="w-5 h-5 rounded object-cover" onerror="this.style.display='none'" />`;
        
        return {
          html: `
            <div class="flex items-center gap-1">
              ${sportIcon}
              ${countryLogo}
              ${leagueLogo}
            </div>
          `,
          text: countryName
        };
      } else {
        // Si pas de pays, afficher seulement l'icône de sport et la ligue
        return {
          html: `
            <div class="flex items-center gap-1">
              ${sportIcon}
              ${leagueLogo}
            </div>
          `,
          text: 'Sport'
        };
      }
    }
  }
  return {
    html: '',
    text: ''
  };
}

// Obtenir les informations du marché depuis l'événement
function getMarketInfo(bet) {
  if (bet.events && bet.events.length > 0) {
    const event = bet.events[0];
    if (event.market) {
      return event.market;
    }
  }
  // Fallback vers bet_code si event.market n'est pas disponible
  return bet.bet_code || bet.bet_type || 'Marché non spécifié';
}

// Gérer l'ajout d'un nouveau pari
function openAddBetDialog() {
  showAddBetDialog.value = true;
}

function onBetCreated(newBet) {
  // Recharger la liste des paris après ajout
  loadBets();
}

onMounted(loadBets);
</script>

<template>
  <div class="card">
    <div class="card-header">
      <div class="flex justify-between items-center">
        <h5 class="text-xl font-semibold mb-0">Historique des Paris</h5>
        <!-- Bouton avec animation de survol -->
        <Button 
          @click="openAddBetDialog"
          class="animated-add-button"
          severity="success"
          rounded
          v-tooltip.top="'Ajouter un nouveau pari'"
          size="small"
        >
          <i class="pi pi-plus button-icon"></i>
          <span class="button-text">Ajouter un pari</span>
        </Button>
      </div>
    </div>
    
    <div v-if="loading" class="p-6 text-center">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
      <p class="mt-2 text-muted-color">Chargement des paris...</p>
    </div>
    
    <div v-else-if="error" class="p-6 text-center text-red-600">
      <i class="pi pi-exclamation-triangle text-2xl"></i>
      <p class="mt-2">{{ error }}</p>
    </div>
    
    <div v-else-if="betsByMonth.length === 0" class="p-6 text-center text-muted-color">
      <i class="pi pi-info-circle text-2xl"></i>
      <p class="mt-2">Aucun pari trouvé</p>
    </div>
    
    <div v-else class="p-6">
      <!-- Liste des mois -->
      <div v-for="month in betsByMonth" :key="month.key" class="mb-4">
        <!-- En-tête du mois -->
        <div 
          @click="toggleMonth(month.key)"
          class="flex items-center justify-between p-4 bg-surface-50 hover:bg-surface-100 rounded-lg cursor-pointer transition-colors border-2 border-surface-200 hover:border-surface-300"
        >
          <div class="flex items-center gap-3">
            <i :class="expandedMonths.has(month.key) ? 'pi pi-chevron-down' : 'pi pi-chevron-right'" class="text-sm"></i>
            <div>
              <h6 class="font-semibold text-lg capitalize">{{ month.label }}</h6>
            </div>
          </div>
          <div class="text-right">
            <div class="text-lg font-semibold" :class="month.totalProfit >= 0 ? 'text-green-600' : 'text-red-600'">
              {{ month.totalProfit >= 0 ? '+' : '' }}{{ month.totalProfit.toFixed(2) }}€
            </div>
          </div>
        </div>
        
        <!-- Contenu du mois (semaines) -->
        <div v-if="expandedMonths.has(month.key)" class="ml-2 mt-3 space-y-4">
          <div v-for="week in getBetsByWeek(month.bets)" :key="week.key">
            <!-- En-tête de la semaine (sans accordéon) -->
            <div class="p-3 bg-surface-50 rounded-lg mb-3">
              <div class="flex items-center gap-2">
                <i class="pi pi-calendar text-sm text-muted-color"></i>
                <div>
                  <h6 class="font-medium text-sm">{{ week.label }}</h6>
                </div>
              </div>
            </div>
            
            <!-- Contenu de la semaine (jours) -->
            <div class="ml-2 space-y-3">
              <div v-for="day in getBetsByDay(week.bets)" :key="day.key">
                <!-- En-tête du jour -->
                <div class="text-sm font-medium text-muted-color mb-2 capitalize">{{ day.label }}</div>
                
                <!-- Paris du jour -->
                <div class="space-y-2 mb-4">
                  <div 
                    v-for="bet in day.bets" 
                    :key="bet.id"
                    class="flex bg-white border border-surface-200 rounded-lg hover:shadow-sm transition-shadow"
                  >
                    <!-- Contenu principal -->
                    <div class="flex-1 p-3">
                      <!-- Bloc d'informations du match -->
                      <div class="mb-3 flex flex-col items-center justify-center text-center">
                        <div class="flex items-center justify-center text-xs text-muted-color mb-1">
                          <span v-html="getCountryInfo(bet).html"></span>
                        </div>
                        <div class="font-medium" v-html="getMatchName(bet).html"></div>
                        <div class="text-xs text-muted-color mt-1">
                          {{ getMarketInfo(bet) }}
                        </div>
                      </div>
                      
                      <!-- Informations financières en grille 2x2 sur mobile -->
                      <div class="grid grid-cols-2 gap-2 md:flex md:gap-3 md:justify-center">
                        <!-- Carte Cote -->
                        <div class="bg-gray-50 rounded-lg p-2 text-center">
                          <div class="text-sm font-medium text-gray-900">{{ parseFloat(bet.global_odds).toFixed(3) }}</div>
                          <div class="text-xs text-gray-500 mt-1">Cote</div>
                        </div>
                        
                        <!-- Carte Mise -->
                        <div class="bg-blue-50 rounded-lg p-2 text-center">
                          <div class="text-sm font-medium text-blue-900">{{ parseFloat(bet.stake).toFixed(2) }}€</div>
                          <div class="text-xs text-blue-600 mt-1">Mise</div>
                        </div>
                        
                        <!-- Carte Gain -->
                        <div class="bg-purple-50 rounded-lg p-2 text-center">
                          <div class="text-sm font-medium text-purple-900">{{ (parseFloat(bet.stake) * parseFloat(bet.global_odds)).toFixed(2) }}€</div>
                          <div class="text-xs text-purple-600 mt-1">Gain</div>
                        </div>
                        
                        <!-- Carte Bénéfice -->
                        <div class="rounded-lg p-2 text-center" :class="calculateProfitLoss(bet) >= 0 ? 'bg-green-50' : 'bg-red-50'">
                          <div 
                            class="text-sm font-medium"
                            :class="calculateProfitLoss(bet) >= 0 ? 'text-green-900' : 'text-red-900'"
                          >
                            {{ calculateProfitLoss(bet).toFixed(2) }}€
                          </div>
                          <div class="text-xs mt-1" :class="calculateProfitLoss(bet) >= 0 ? 'text-green-600' : 'text-red-600'">Bénéfice</div>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Barre verticale colorée avec icône de résultat qui prend toute la hauteur -->
                    <div class="flex items-stretch">
                      <div 
                        :class="getResultBarColor(bet.result)" 
                        class="w-4 min-h-full rounded-r-lg flex items-center justify-center relative"
                      >
                        <div 
                          class="absolute inset-0 flex items-center justify-center"
                          v-html="getResultIcon(bet.result)"
                        ></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Dialog d'ajout de pari -->
    <AddBetDialog 
      v-model:visible="showAddBetDialog" 
      @bet-created="onBetCreated"
    />
  </div>
</template>

<style scoped>
.card {
  @apply bg-white rounded-lg shadow-sm border border-surface-200;
}

.card-header {
  @apply p-3 border-b border-surface-200;
}

/* Styles pour le bouton animé */
.animated-add-button {
  position: relative;
  width: 2.5rem;
  height: 2.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  transition: all 0.3s ease;
  cursor: pointer;
  white-space: nowrap;
}

.animated-add-button:hover {
  width: auto;
  min-width: 9rem;
  padding-left: 0.75rem;
  padding-right: 0.75rem;
}

.button-icon {
  position: relative;
  z-index: 2;
  transition: all 0.3s ease;
  margin-right: 0;
}

.animated-add-button:hover .button-icon {
  margin-right: 0.5rem;
}

.button-text {
  opacity: 0;
  max-width: 0;
  overflow: hidden;
  transition: all 0.3s ease;
  font-size: 0.875rem;
  white-space: nowrap;
}

.animated-add-button:hover .button-text {
  opacity: 1;
  max-width: 8rem;
}

@media (max-width: 768px) {
  .p-6 {
    padding: 0.5rem;
  }
}
</style>