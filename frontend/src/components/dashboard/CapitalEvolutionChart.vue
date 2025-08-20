<template>
    <div class="card gap-2">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold">Évolution du capital</h3>
            <div class="flex gap-2">
                <Button 
                    v-for="period in periods" 
                    :key="period.value"
                    :label="period.label"
                    :class="selectedPeriod === period.value ? 'p-button-primary' : 'p-button-outlined'"
                    @click="changePeriod(period.value)"
                    size="small"
                />
            </div>
        </div>

        <!-- Filtres avancés -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
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
            <div class="field">
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
            <div class="field">
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

        <!-- Indicateur de filtres actifs -->
        <div v-if="hasActiveFilters" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
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
                <div v-for="sport in selectedSports" :key="`sport-${sport}`" 
                     class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                    {{ getSportLabel(sport) }}
                </div>
                <div v-for="betType in selectedBetTypes" :key="`bet-${betType}`" 
                     class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">
                    {{ getBetTypeLabel(betType) }}
                </div>
                <div v-for="bookmaker in selectedBookmakers" :key="`book-${bookmaker}`" 
                     class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full">
                    {{ getBookmakerLabel(bookmaker) }}
                </div>
                <div v-for="tipster in selectedTipsters" :key="`tip-${tipster}`" 
                     class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                    {{ getTipsterLabel(tipster) }}
                </div>
            </div>
        </div>

        <!-- Graphique -->
        <div class="relative">
            <!-- Indicateur de chargement -->
            <div v-if="loading" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10">
                <div class="flex items-center gap-2">
                    <i class="pi pi-spin pi-spinner text-blue-600"></i>
                    <span class="text-blue-600">Chargement des données...</span>
                </div>
            </div>

            <!-- Message d'erreur -->
            <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center gap-2">
                    <i class="pi pi-exclamation-triangle text-red-600"></i>
                    <span class="text-red-800">{{ error }}</span>
                </div>
            </div>

            <Chart 
                type="line" 
                :data="chartData" 
                :options="chartOptions"
                class="w-full"
                style="height: 400px;"
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
                <div class="text-2xl font-bold text-blue-600">{{ formatCurrency(currentCapital) }}</div>
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
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import Chart from 'primevue/chart';
import Button from 'primevue/button';
import MultiSelect from 'primevue/multiselect';
import { BetService } from '@/service/BetService';

// États de chargement et d'erreur
const loading = ref(false);
const error = ref(null);

// Données réactives
const selectedPeriod = ref('all');
const selectedBetTypes = ref([]);
const selectedSports = ref([]);
const selectedBookmakers = ref([]);
const selectedTipsters = ref([]);

// Données des filtres depuis l'API
const betTypes = ref([]);
const sports = ref([]);
const bookmakers = ref([]);
const tipsters = ref([]);

// Périodes disponibles
const periods = [
    { label: '7j', value: '7j' },
    { label: '30j', value: '30j' },
    { label: '3m', value: '3m' },
    { label: '6m', value: '6m' },
    { label: '1an', value: '1an' },
    { label: 'Tout', value: 'all' }
];

// Données du graphique
const chartData = ref({
    labels: [],
    datasets: [{
        label: 'Capital cumulé',
        data: [],
        borderColor: '#3B82F6',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointRadius: 3,
        pointHoverRadius: 6
    }]
});

// Options du graphique
const chartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
                label: function(context) {
                    return `Capital: ${formatCurrency(context.parsed.y)}`;
                }
            }
        }
    },
    scales: {
        x: {
            display: true,
            title: {
                display: true,
                text: 'Date'
            },
            grid: {
                display: false
            }
        },
        y: {
            display: true,
            title: {
                display: true,
                text: 'Capital (€)'
            },
            grid: {
                color: 'rgba(0, 0, 0, 0.1)'
            },
            ticks: {
                callback: function(value) {
                    return formatCurrency(value);
                }
            }
        }
    },
    interaction: {
        mode: 'nearest',
        axis: 'x',
        intersect: false
    }
});

// Fonctions utilitaires
const formatCurrency = (value) => {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(value);
};

// Fonctions pour obtenir les labels des filtres
const getSportLabel = (value) => {
    const sport = sports.value.find(s => s.value === value);
    return sport ? sport.label : value;
};

const getBetTypeLabel = (value) => {
    const betType = betTypes.value.find(b => b.value === value);
    return betType ? betType.label : value;
};

const getBookmakerLabel = (value) => {
    const bookmaker = bookmakers.value.find(b => b.value === value);
    return bookmaker ? bookmaker.label : value;
};

const getTipsterLabel = (value) => {
    const tipster = tipsters.value.find(t => t.value === value);
    return tipster ? tipster.label : value;
};

// Computed properties pour les statistiques
const currentCapital = computed(() => {
    const data = chartData.value.datasets[0].data;
    return data.length > 0 ? data[data.length - 1] : 0;
});

const totalProfit = computed(() => {
    const data = chartData.value.datasets[0].data;
    if (data.length === 0) return 0;
    return data[data.length - 1] - 1000; // 1000 = capital initial
});

const profitPercentage = computed(() => {
    if (currentCapital.value === 0) return 0;
    return ((totalProfit.value / 1000) * 100).toFixed(1);
});

const totalBets = computed(() => {
    // Simulation du nombre de paris basé sur la période
    const periodDays = {
        '7j': 7,
        '30j': 30,
        '3m': 90,
        '6m': 180,
        '1an': 365,
        'all': 730
    };
    return Math.floor(periodDays[selectedPeriod.value] * 1.5); // 1.5 paris par jour en moyenne
});

// Computed property pour l'indicateur de filtres actifs
const hasActiveFilters = computed(() => {
    return selectedBetTypes.value.length > 0 ||
           selectedSports.value.length > 0 ||
           selectedBookmakers.value.length > 0 ||
           selectedTipsters.value.length > 0;
});

// Fonctions de contrôle
const changePeriod = (period) => {
    selectedPeriod.value = period;
    updateChartData();
};

const updateChartData = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        const filters = {
            period: selectedPeriod.value,
            sports: selectedSports.value,
            betTypes: selectedBetTypes.value,
            bookmakers: selectedBookmakers.value,
            tipsters: selectedTipsters.value
        };
        
        const response = await BetService.getCapitalEvolution(filters);

        console.log(response);
        
        // Mettre à jour les données du graphique
        chartData.value.labels = response.labels || [];
        chartData.value.datasets[0].data = response.data || [];
        
    } catch (err) {
        console.error('Erreur lors de la récupération des données:', err);
        error.value = 'Erreur lors du chargement des données';
        
        // Fallback vers les données simulées en cas d'erreur
        const { data, labels } = generateMockData(selectedPeriod.value, {
            sports: selectedSports.value,
            betTypes: selectedBetTypes.value,
            bookmakers: selectedBookmakers.value,
            tipsters: selectedTipsters.value
        });
        chartData.value.labels = labels;
        chartData.value.datasets[0].data = data;
    } finally {
        loading.value = false;
    }
};

// Fonction de fallback pour les données simulées
const generateMockData = (period, filters = {}) => {
    const data = [];
    const labels = [];
    let days = 30;

    switch (period) {
        case '7j': days = 7; break;
        case '3m': days = 90; break;
        case '6m': days = 180; break;
        case '1an': days = 365; break;
        case 'all': days = 730; break;
    }

    let capital = 1000;

    for (let i = days; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        
        let dailyChange = (Math.random() - 0.45) * 50;
        
        // Appliquer les filtres (simulation)
        if (filters.sports && filters.sports.length > 0) {
            let sportMultiplier = 1.0;
            if (filters.sports.includes('football')) sportMultiplier = 1.3;
            else if (filters.sports.includes('basketball')) sportMultiplier = 1.2;
            else if (filters.sports.includes('tennis')) sportMultiplier = 0.8;
            else if (filters.sports.includes('baseball')) sportMultiplier = 1.1;
            else if (filters.sports.includes('hockey')) sportMultiplier = 0.9;
            dailyChange *= sportMultiplier;
        }
        
        if (filters.betTypes && filters.betTypes.length > 0) {
            let betTypeMultiplier = 1.0;
            if (filters.betTypes.includes('multiple')) betTypeMultiplier = 1.4;
            else if (filters.betTypes.includes('simple')) betTypeMultiplier = 0.7;
            else if (filters.betTypes.includes('combined')) betTypeMultiplier = 1.2;
            else if (filters.betTypes.includes('live')) betTypeMultiplier = 0.9;
            dailyChange *= betTypeMultiplier;
        }
        
        if (filters.bookmakers && filters.bookmakers.length > 0) {
            let bookmakerMultiplier = 1.0;
            if (filters.bookmakers.includes('bet365')) bookmakerMultiplier = 1.3;
            else if (filters.bookmakers.includes('william-hill')) bookmakerMultiplier = 1.2;
            else if (filters.bookmakers.includes('unibet')) bookmakerMultiplier = 1.1;
            else if (filters.bookmakers.includes('bwin')) bookmakerMultiplier = 0.9;
            else if (filters.bookmakers.includes('pmu')) bookmakerMultiplier = 0.8;
            dailyChange *= bookmakerMultiplier;
        }
        
        if (filters.tipsters && filters.tipsters.length > 0) {
            let tipsterMultiplier = 1.0;
            if (filters.tipsters.includes('expert1')) tipsterMultiplier = 1.4;
            else if (filters.tipsters.includes('expert2')) tipsterMultiplier = 1.1;
            else if (filters.tipsters.includes('expert3')) tipsterMultiplier = 0.7;
            dailyChange *= tipsterMultiplier;
        }
        
        capital += dailyChange;
        
        data.push(Math.max(0, capital));
        labels.push(date.toLocaleDateString('fr-FR', { 
            day: '2-digit', 
            month: '2-digit' 
        }));
    }

    return { data, labels };
};

const zoomIn = () => {
    console.log('Zoom in');
};

const zoomOut = () => {
    console.log('Zoom out');
};

const resetZoom = () => {
    console.log('Reset zoom');
};

const clearAllFilters = () => {
    selectedBetTypes.value = [];
    selectedSports.value = [];
    selectedBookmakers.value = [];
    selectedTipsters.value = [];
    updateChartData();
};

// Charger les options de filtres depuis l'API
const loadFilterOptions = async () => {
    try {
        const options = await BetService.getFilterOptions();
        betTypes.value = options.data.betTypes || [];
        sports.value = options.data.sports || [];
        bookmakers.value = options.data.bookmakers || [];
        tipsters.value = options.data.tipsters || [];
    } catch (err) {
        console.error('Erreur lors du chargement des options de filtres:', err);
        // Fallback vers les options par défaut
        betTypes.value = [
            { label: 'Simple', value: 'simple' },
            { label: 'Multiple', value: 'multiple' },
            { label: 'Combiné', value: 'combined' },
            { label: 'Live', value: 'live' }
        ];
        sports.value = [
            { label: 'Football', value: 'football' },
            { label: 'Basketball', value: 'basketball' },
            { label: 'Tennis', value: 'tennis' },
            { label: 'Baseball', value: 'baseball' },
            { label: 'Hockey', value: 'hockey' }
        ];
        bookmakers.value = [
            { label: 'Bet365', value: 'bet365' },
            { label: 'William Hill', value: 'william-hill' },
            { label: 'Unibet', value: 'unibet' },
            { label: 'Bwin', value: 'bwin' },
            { label: 'PMU', value: 'pmu' }
        ];
        tipsters.value = [
            { label: 'Expert1', value: 'expert1' },
            { label: 'Expert2', value: 'expert2' },
            { label: 'Expert3', value: 'expert3' }
        ];
    }
};

// Watchers pour les filtres
watch([selectedBetTypes, selectedSports, selectedBookmakers, selectedTipsters], () => {
    console.log('Filtres mis à jour:', {
        betTypes: selectedBetTypes.value,
        sports: selectedSports.value,
        bookmakers: selectedBookmakers.value,
        tipsters: selectedTipsters.value
    });
    updateChartData();
}, { deep: true });

// Fonction d'initialisation asynchrone
async function initializeChart() {
    await loadFilterOptions();
    await updateChartData();
}

// Initialisation
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