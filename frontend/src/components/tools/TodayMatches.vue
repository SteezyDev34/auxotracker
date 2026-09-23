<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import MatchService from '@/service/MatchService';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import Tooltip from 'primevue/tooltip';
import Checkbox from 'primevue/checkbox';
import Popover from 'primevue/popover';

const vTooltip = Tooltip;

// Un seul Popover réutilisé pour tous les "?" — le contenu affiché dépend de
// la clé du stat dont le "?" a été cliqué en dernier.
const statsHelpPopover = ref(null);
const activeStatHelpKey = ref(null);
const STAT_HELP = {
    score: {
        title: '🔥 Score match serré (/100)',
        text: "Indice global basé sur : proximité de classement/UTR, cotes proches, % de service similaire, activité de breaks, conversion des balles de break, historique H2H, fréquence de tie-breaks, forme récente. Plus haut = match potentiellement plus disputé.",
    },
    probs: {
        title: '15A / 30A / 40A',
        text: "Probabilité qu'un jeu donné du 1er set atteigne 15-15 / 30-30 / 40-40 (deuce). Calculée depuis le vrai historique point-by-point des 2 joueurs si l'échantillon est suffisant (≥15 jeux), sinon un modèle théorique basé sur leur % de points gagnés au service.",
    },
    edge: {
        title: '💰 Value bet / Edge',
        text: "Écart entre la proba calculée et le seuil de rentabilité des cotes de référence (15A: 1.85, 30A: 2.40, 40A: 3.00). Vert = edge > 5 points ET échantillon fiable (n≥15) — un simple edge > 0 capte trop de faux positifs statistiques.",
    },
    sample: {
        title: 'n=XX (fiabilité)',
        text: "Taille de l'échantillon (jeux ou matchs) derrière les probas, le plus petit des 2 joueurs. 🔵 ≥30 fiable · 🟡 15-29 moyen · 🔴 <15 peu fiable.",
    },
    patterns: {
        title: '30-0 / G:40-0 / G:40-15 / G:40-30',
        text: "Probas de \"patterns\" de jeu (moyenne des 2 joueurs) : mener 30-0 (2-0), jeu terminé 40-0/40-15/40-30 (sans deuce). Cotes de référence : 30-0 = 2.20, jeu 40-0/40-15/40-30 = 3.00 chacun. Vert = edge positif.",
    },
    leads: {
        title: '🥇Nom 15-0: X%',
        text: "Probabilité que CE joueur précis gagne le tout premier point du jeu (mène 15-0). Spécifique à chaque joueur (pas une moyenne) — un joueur faible face au n°1 mondial mènera rarement. Cote de référence : 1.40. Vert = edge positif.",
    },
    atLeastOneServe: {
        title: '≥1pt srv',
        text: "Probabilité moyenne (2 joueurs) de gagner au moins 1 point sur son propre jeu de service — complémentaire de le perdre 0-40. Purement informatif, pas de cote de référence associée.",
    },
    lostServe: {
        title: '🎾 Nom perd 1er pt srv: X%',
        text: "Probabilité que CE joueur précis perde le tout premier point QUAND IL SERT (dénominateur = ses jeux servis uniquement, différent de \"mène 15-0\" qui mélange service/retour). Utile pour repérer les joueurs à éviter en martingale sur leur service. Cote de référence : 2.00. Vert = edge positif.",
    },
};
function toggleStatHelp(event, key) {
    activeStatHelpKey.value = key;
    statsHelpPopover.value.toggle(event);
}

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL;
const PER_PAGE = 50;

const matches = ref([]);
const loading = ref(false);       // premier chargement (page 1)
const loadingMore = ref(false);   // chargement d'une page suivante
const error = ref(null);
const selectedDate = ref(new Date());
const selectedSport = ref({ label: 'Tennis', value: 2 });

const sortOptions = [
    { label: 'Par défaut (ligue / heure)', value: null },
    { label: 'Heure ↑', value: 'datetime_asc' },
    { label: 'Heure ↓', value: 'datetime_desc' },
    { label: '🔥 Score match serré ↓', value: 'score_desc' },
    { label: 'Proba 15A (1er set) ↓', value: 'prob_15a_desc' },
    { label: 'Proba 30A (1er set) ↓', value: 'prob_30a_desc' },
    { label: 'Proba 40A (1er set) ↓', value: 'prob_40a_desc' },
    { label: '💰 Edge 15A ↓', value: 'edge_15a_desc' },
    { label: '💰 Edge 30A ↓', value: 'edge_30a_desc' },
    { label: '💰 Edge 40A ↓', value: 'edge_40a_desc' },
    { label: 'Proba 30-0 (2-0) ↓', value: 'prob_30love_desc' },
    { label: 'Proba jeu 40-0 (love) ↓', value: 'prob_g40_0_desc' },
    { label: 'Proba jeu 40-15 ↓', value: 'prob_g40_15_desc' },
    { label: 'Proba jeu 40-30 ↓', value: 'prob_g40_30_desc' },
    { label: 'Service perdu à 0 (0-40) ↓', value: 'server_loss_desc' },
    { label: '🥇 Meilleur mène 15-0 ↓', value: 'leads_15_0_desc' },
    { label: '🎾 Perd 1er pt sur son service ↓', value: 'lost_serve_desc' },
];
const selectedSort = ref(sortOptions[0]);
const valueBetsOnly = ref(false);

const currentPage = ref(1);
const hasMore = ref(false);
const totalCount = ref(0);
const sentinel = ref(null);
let observer = null;

const sports = [
    { label: 'Tous les sports', value: null },
    { label: 'Tennis', value: 2 },
    { label: 'Football', value: 3 },
    { label: 'Basketball', value: 4 },
    { label: 'Handball', value: 8 },
    { label: 'Volleyball', value: 13 },
    { label: 'Hockey sur glace', value: 9 },
    { label: 'Rugby', value: 5 },
];

const matchesByLeague = computed(() => {
    // Un tri explicite (score/proba/heure) traverse les ligues : on affiche une
    // liste plate dans l'ordre renvoyé par l'API plutôt que de regrouper par
    // ligue, sinon le tri ne serait visible qu'à l'intérieur de chaque groupe.
    if (selectedSort.value?.value) {
        return [{
            group_key: 'flat',
            league_name: null,
            league_img: null,
            country_id: null,
            country_name: null,
            sport_name: null,
            sport_img: null,
            matches: matches.value,
        }];
    }

    // Map (pas un objet littéral) : préserve l'ordre d'insertion même quand les
    // clés sont numériques (league_sofascore_id). Un objet JS réordonne les clés
    // numériques par ordre croissant à chaque recalcul, ce qui faisait "remonter"
    // des compétitions en tête de liste à chaque page chargée par le lazy loading.
    const groups = new Map();
    for (const m of matches.value) {
        const key = m.league_sofascore_id || m.league_name || 'unknown';
        if (!groups.has(key)) {
            groups.set(key, {
                group_key: key,
                league_name: m.league_name,
                league_img: m.league_img,
                country_id: m.country_id,
                country_name: m.country_name,
                sport_name: m.sport_name,
                sport_img: m.sport_img,
                matches: [],
            });
        }
        groups.get(key).matches.push(m);
    }
    return Array.from(groups.values());
});

function teamLogoUrl(img) {
    if (!img) return null;
    return `${apiBaseUrl}/storage/${img}`;
}

function leagueLogoUrl(img) {
    if (!img) return null;
    return `${apiBaseUrl}/storage/${img}`;
}

// Même convention que CountryField.vue/LeagueField.vue/BetsHistoryWidget.vue :
// le fichier est nommé par l'id interne de la country, pas par le champ `img`
// (qui n'est d'ailleurs pas exposé par l'API pour cette vue).
function countryLogoUrl(countryId) {
    if (!countryId) return null;
    return `${apiBaseUrl}/storage/country_flags/${countryId}.png`;
}

function formatTime(time) {
    if (!time) return '--:--';
    return time.substring(0, 5);
}

function sofascoreLink(match) {
    return match.sofascore_link || null;
}

function tightnessScore(match) {
    return match.tightness_score ?? null;
}

function tightnessLevel(score) {
    if (score === null) return null;
    if (score >= 75) return 'high';
    if (score >= 50) return 'medium';
    return 'low';
}

function set1Probabilities(match) {
    const probs = [
        { key: '15A', value: match.prob_15a_set1, edge: match.edge_15a },
        { key: '30A', value: match.prob_30a_set1, edge: match.edge_30a },
        { key: '40A', value: match.prob_40a_set1, edge: match.edge_40a },
    ].filter((p) => p.value !== null && p.value !== undefined);
    return probs;
}

// Seuil minimum d'edge pour compter comme "value bet" affiché en vert : un
// simple edge > 0 capte trop de faux positifs statistiques quand la proba
// moyenne du marché est juste collée au seuil de rentabilité de la cote de
// référence (ex : 30-0, où ~46% des matchs dépassent la cote par pur bruit
// de variance plutôt que par un vrai signal). On exige aussi un échantillon
// fiable (sample_size >= 15) quand le contexte du match est fourni.
const MIN_VALUE_BET_EDGE = 5;
function isValueBet(p, match) {
    if (p.edge === null || p.edge === undefined || p.edge <= MIN_VALUE_BET_EDGE) return false;
    if (match) {
        const rel = sampleReliability(match);
        if (rel && rel.level === 'low') return false;
    }
    return true;
}

// Probas de "patterns" de jeu — désormais avec edge (cotes de référence :
// 30-0=2.20, jeu 40-0/40-15/40-30=3.00 chacun).
function gamePatternProbabilities(match) {
    const probs = [
        { key: '30-0', value: match.prob_30love_set1, edge: match.edge_30love, title: "Proba qu'un des deux joueurs mène 30-0 (2 points à 0) à un moment du jeu" },
        { key: 'G:40-0', value: match.prob_game_40_0, edge: match.edge_g40_0, title: 'Proba qu\'un jeu se termine 40-0 (à zéro, sans deuce)' },
        { key: 'G:40-15', value: match.prob_game_40_15, edge: match.edge_g40_15, title: "Proba qu'un jeu se termine 40-15 (sans deuce)" },
        { key: 'G:40-30', value: match.prob_game_40_30, edge: match.edge_g40_30, title: "Proba qu'un jeu se termine 40-30 (sans deuce)" },
    ].filter((p) => p.value !== null && p.value !== undefined);
    return probs;
}

// Reformulation positive du "Break❤️" : proba qu'un joueur gagne AU MOINS UN
// point sur son propre service (complémentaire de perdre 0-40) — purement
// informatif, pas de cote de référence pour cette formulation.
function atLeastOnePointOnServeProbabilities(match) {
    if (match.prob_server_loss_to_love === null || match.prob_server_loss_to_love === undefined) return null;
    return Math.round((100 - match.prob_server_loss_to_love) * 100) / 100;
}

// Qui mène 15-0 (asymétrique par nature : chaque joueur a son propre taux,
// pas de moyenne fusionnée — un faible face au n°1 mondial ne mènera que
// rarement 15-0, même si l'un des deux le fait forcément après le 1er point).
// Cote de référence : 1.40.
function leads15_0Probabilities(match) {
    const probs = [];
    if (match.prob_team1_leads_15_0 !== null && match.prob_team1_leads_15_0 !== undefined) {
        probs.push({ name: match.team1_name, value: match.prob_team1_leads_15_0, edge: match.edge_leads1 });
    }
    if (match.prob_team2_leads_15_0 !== null && match.prob_team2_leads_15_0 !== undefined) {
        probs.push({ name: match.team2_name, value: match.prob_team2_leads_15_0, edge: match.edge_leads2 });
    }
    return probs;
}

// Perd le 1er point QUAND IL SERT (spécifique au service, pas mélangé au
// retour comme "mène 15-0") — cote de référence : 2.00.
function lostFirstPointOnServeProbabilities(match) {
    const probs = [];
    if (match.prob_lost_serve1 !== null && match.prob_lost_serve1 !== undefined) {
        probs.push({ name: match.team1_name, value: match.prob_lost_serve1, edge: match.edge_lost_serve1 });
    }
    if (match.prob_lost_serve2 !== null && match.prob_lost_serve2 !== undefined) {
        probs.push({ name: match.team2_name, value: match.prob_lost_serve2, edge: match.edge_lost_serve2 });
    }
    return probs;
}

// Fiabilité de l'échantillon derrière les probas 15A/30A/40A (nb de jeux ou
// de matchs, le plus petit des deux joueurs) — un edge élevé sur un petit
// échantillon est moins fiable qu'un edge modéré sur un gros échantillon.
function sampleReliability(match) {
    const n = match.sample_size;
    if (n === null || n === undefined) return null;
    if (n >= 30) return { level: 'high', label: `n=${n}` };
    if (n >= 15) return { level: 'medium', label: `n=${n}` };
    return { level: 'low', label: `n=${n}` };
}

function tightnessBreakdown(match) {
    if (!match.tightness_breakdown) return [];
    try {
        const parsed = typeof match.tightness_breakdown === 'string'
            ? JSON.parse(match.tightness_breakdown)
            : match.tightness_breakdown;
        return Object.entries(parsed).map(([key, detail]) => ({ key, detail }));
    } catch (e) {
        return [];
    }
}

function dateStr() {
    const d = selectedDate.value;
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// Chargement initial (ou changement de filtre) : reset pagination
async function load() {
    loading.value = true;
    error.value = null;
    currentPage.value = 1;
    hasMore.value = false;
    matches.value = [];
    try {
        const res = await MatchService.getTodayMatches(selectedSport.value?.value ?? null, dateStr(), 1, PER_PAGE, selectedSort.value?.value ?? null, valueBetsOnly.value);
        matches.value = res.matches || [];
        totalCount.value = res.total ?? matches.value.length;
        hasMore.value = !!res.has_more;
    } catch (e) {
        error.value = 'Erreur lors du chargement des matchs.';
        console.error(e);
    } finally {
        loading.value = false;
    }
}

// Lazy loading : charge la page suivante et l'ajoute à la liste existante
async function loadMore() {
    if (loadingMore.value || loading.value || !hasMore.value) return;
    loadingMore.value = true;
    try {
        const nextPage = currentPage.value + 1;
        const res = await MatchService.getTodayMatches(selectedSport.value?.value ?? null, dateStr(), nextPage, PER_PAGE, selectedSort.value?.value ?? null, valueBetsOnly.value);
        matches.value = [...matches.value, ...(res.matches || [])];
        currentPage.value = nextPage;
        hasMore.value = !!res.has_more;
    } catch (e) {
        console.error(e);
    } finally {
        loadingMore.value = false;
    }
}

// Observe un élément sentinelle en bas de liste : dès qu'il devient visible
// (utilisateur proche du bas), on charge la page suivante automatiquement.
function setupObserver() {
    if (observer) observer.disconnect();
    observer = new IntersectionObserver((entries) => {
        if (entries[0]?.isIntersecting) {
            loadMore();
        }
    }, { rootMargin: '200px' });
    if (sentinel.value) observer.observe(sentinel.value);
}

watch(sentinel, (el) => {
    if (el) setupObserver();
});

onBeforeUnmount(() => {
    if (observer) observer.disconnect();
});

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Filtres -->
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex flex-col gap-1">
                <label class="text-sm text-surface-600 dark:text-surface-400">Date</label>
                <DatePicker v-model="selectedDate" dateFormat="dd/mm/yy" showIcon class="w-44" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm text-surface-600 dark:text-surface-400">Sport</label>
                <Select v-model="selectedSport" :options="sports" optionLabel="label" class="w-48" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm text-surface-600 dark:text-surface-400">Tri</label>
                <Select v-model="selectedSort" :options="sortOptions" optionLabel="label" class="w-56" />
            </div>
            <div class="flex items-center gap-2 pb-2">
                <Checkbox v-model="valueBetsOnly" :binary="true" inputId="valueBetsOnly" @change="load" />
                <label for="valueBetsOnly" class="text-sm text-surface-600 dark:text-surface-400 cursor-pointer">
                    💰 Value bets uniquement (edge {{ '>' }} 0)
                </label>
            </div>
            <Button label="Actualiser" icon="pi pi-refresh" @click="load" :loading="loading" />
        </div>

        <!-- Légende : un "?" par type de proba, ouvre l'explication correspondante -->
        <div class="flex flex-wrap gap-3 items-center text-xs text-surface-500 dark:text-surface-400 -mt-1">
            <span v-for="(help, key) in STAT_HELP" :key="key" class="flex items-center gap-1">
                {{ help.title }}
                <button type="button" @click="toggleStatHelp($event, key)"
                        class="pi pi-question-circle cursor-pointer hover:text-primary-500 text-[13px]"
                        :aria-label="`Explication : ${help.title}`"></button>
            </span>
        </div>

        <Popover ref="statsHelpPopover">
            <div v-if="activeStatHelpKey" class="text-sm max-w-sm p-1">
                <span class="font-bold">{{ STAT_HELP[activeStatHelpKey].title }}</span>
                <p class="text-surface-600 dark:text-surface-400 mt-0.5">
                    {{ STAT_HELP[activeStatHelpKey].text }}
                </p>
            </div>
        </Popover>

        <!-- Chargement initial : skeleton imitant la mise en page réelle -->
        <div v-if="loading" class="flex flex-col gap-4">
            <div v-for="n in 3" :key="n" class="border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden">
                <div class="flex items-center gap-3 px-4 py-3 bg-surface-100 dark:bg-surface-800">
                    <Skeleton shape="circle" size="1.5rem" />
                    <Skeleton width="8rem" height="1rem" />
                    <Skeleton width="6rem" height="0.75rem" class="ml-auto" />
                </div>
                <div class="divide-y divide-surface-100 dark:divide-surface-800">
                    <div v-for="i in 3" :key="i" class="flex items-center gap-4 px-4 py-3">
                        <Skeleton width="2.5rem" height="1rem" />
                        <Skeleton width="6rem" height="1rem" class="ml-auto" />
                        <Skeleton shape="circle" size="2rem" />
                        <Skeleton width="1.5rem" height="0.75rem" />
                        <Skeleton shape="circle" size="2rem" />
                        <Skeleton width="6rem" height="1rem" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Erreur -->
        <div v-else-if="error" class="text-red-500 text-center py-8">{{ error }}</div>

        <!-- Aucun match -->
        <div v-else-if="matches.length === 0" class="text-center py-12 text-surface-500 dark:text-surface-400">
            <i class="pi pi-calendar-times text-4xl mb-3 block opacity-40"></i>
            Aucun match trouvé pour cette date.
        </div>

        <!-- Matchs groupés par ligue -->
        <div v-else class="flex flex-col gap-4">
            <div v-for="group in matchesByLeague" :key="group.group_key"
                 class="border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden">

                <!-- Header ligue (masqué en mode liste plate triée) -->
                <div v-if="group.group_key !== 'flat'" class="flex items-center gap-3 px-4 py-3 bg-surface-100 dark:bg-surface-800">
                    <img v-if="leagueLogoUrl(group.league_img)"
                         :src="leagueLogoUrl(group.league_img)"
                         :alt="group.league_name"
                         class="w-6 h-6 object-contain" />
                    <i v-else class="pi pi-trophy text-surface-400"></i>
                    <span class="font-semibold text-sm">{{ group.league_name }}</span>
                    <span class="text-xs text-surface-500 dark:text-surface-400 ml-auto flex items-center gap-1">
                        <img v-if="countryLogoUrl(group.country_id)"
                             :src="countryLogoUrl(group.country_id)"
                             :alt="group.country_name"
                             class="w-4 h-4 object-contain"
                             @error="$event.target.style.display = 'none'" />
                        {{ group.country_name }} · {{ group.sport_name }}
                    </span>
                </div>

                <!-- Liste des matchs -->
                <div class="divide-y divide-surface-100 dark:divide-surface-800">
                    <a v-for="match in group.matches" :key="match.id"
                       :href="sofascoreLink(match)"
                       :target="sofascoreLink(match) ? '_blank' : null"
                       :class="['flex items-center gap-4 px-4 py-3 transition-colors',
                                sofascoreLink(match) ? 'hover:bg-surface-50 dark:hover:bg-surface-800/50 cursor-pointer' : 'cursor-default']">

                        <!-- Heure -->
                        <div class="text-sm font-mono font-semibold text-primary-500 w-12 shrink-0">
                            {{ formatTime(match.match_start_time) }}
                        </div>

                        <!-- Équipe 1 -->
                        <div class="flex items-center gap-2 flex-1 justify-end">
                            <span class="text-sm font-medium text-right">{{ match.team1_name }}</span>
                            <div class="w-8 h-8 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center shrink-0 overflow-hidden">
                                <img v-if="teamLogoUrl(match.team1_img)"
                                     :src="teamLogoUrl(match.team1_img)"
                                     :alt="match.team1_name"
                                     class="w-7 h-7 object-cover rounded-full"
                                     @error="$event.target.style.display='none'" />
                                <i v-else class="pi pi-user text-xs text-surface-400"></i>
                            </div>
                        </div>

                        <!-- VS -->
                        <div class="text-xs text-surface-400 font-bold shrink-0">VS</div>

                        <!-- Équipe 2 -->
                        <div class="flex items-center gap-2 flex-1">
                            <div class="w-8 h-8 rounded-full bg-surface-100 dark:bg-surface-800 flex items-center justify-center shrink-0 overflow-hidden">
                                <img v-if="teamLogoUrl(match.team2_img)"
                                     :src="teamLogoUrl(match.team2_img)"
                                     :alt="match.team2_name"
                                     class="w-7 h-7 object-cover rounded-full"
                                     @error="$event.target.style.display='none'" />
                                <i v-else class="pi pi-user text-xs text-surface-400"></i>
                            </div>
                            <span class="text-sm font-medium">{{ match.team2_name }}</span>
                        </div>

                        <!-- Score "match serré" (tennis uniquement, quand calculé) -->
                        <div v-if="tightnessScore(match) !== null"
                             class="shrink-0"
                             v-tooltip.left="{
                                 value: tightnessBreakdown(match).map(b => b.detail).join('<br>'),
                                 escape: false,
                             }">
                            <span :class="['text-xs font-bold px-2 py-1 rounded-full whitespace-nowrap',
                                           tightnessLevel(tightnessScore(match)) === 'high' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' :
                                           tightnessLevel(tightnessScore(match)) === 'medium' ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300' :
                                           'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400']">
                                🔥 {{ tightnessScore(match) }}/100
                            </span>
                        </div>

                        <!-- Probas 15A/30A/40A par jeu (quand calculées) — vert = value bet (edge > 0 vs cote de référence) -->
                        <div v-if="set1Probabilities(match).length" class="shrink-0 flex flex-wrap gap-1 max-w-[220px] justify-end">
                            <span v-for="p in set1Probabilities(match)" :key="p.key"
                                  :class="['text-[10px] font-semibold px-1.5 py-0.5 rounded whitespace-nowrap',
                                           isValueBet(p, match) ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' :
                                           'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300']"
                                  :title="`Proba ${p.key} par jeu : ${p.value}%` + (p.edge !== null && p.edge !== undefined ? ` (edge ${p.edge > 0 ? '+' : ''}${p.edge} pts vs cote de référence)` : '')">
                                {{ isValueBet(p, match) ? '💰 ' : '' }}{{ p.key }} {{ Math.round(p.value) }}%
                            </span>
                            <span v-if="sampleReliability(match)"
                                  :class="['text-[10px] font-semibold px-1.5 py-0.5 rounded whitespace-nowrap',
                                           sampleReliability(match).level === 'high' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' :
                                           sampleReliability(match).level === 'medium' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300' :
                                           'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300']"
                                  title="Taille de l'échantillon (jeux/matchs, le plus petit des 2 joueurs) derrière ces probas — plus c'est grand, plus c'est fiable">
                                {{ sampleReliability(match).label }}
                            </span>
                            <span v-for="p in gamePatternProbabilities(match)" :key="p.key"
                                  :class="['text-[10px] font-semibold px-1.5 py-0.5 rounded whitespace-nowrap',
                                           isValueBet(p, match) ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' :
                                           'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300']"
                                  :title="p.title + ` : ${p.value}%` + (p.edge !== null && p.edge !== undefined ? ` (edge ${p.edge > 0 ? '+' : ''}${p.edge} pts)` : '')">
                                {{ isValueBet(p, match) ? '💰 ' : '' }}{{ p.key }} {{ Math.round(p.value) }}%
                            </span>
                            <span v-if="atLeastOnePointOnServeProbabilities(match) !== null"
                                  class="text-[10px] font-semibold px-1.5 py-0.5 rounded whitespace-nowrap bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300"
                                  title="Proba (moyenne des 2 joueurs) de gagner au moins 1 point sur son propre jeu de service (complémentaire de le perdre à 0-40)">
                                ≥1pt srv: {{ Math.round(atLeastOnePointOnServeProbabilities(match)) }}%
                            </span>
                            <span v-for="p in leads15_0Probabilities(match)" :key="'leads-' + p.name"
                                  :class="['text-[10px] font-semibold px-1.5 py-0.5 rounded whitespace-nowrap',
                                           isValueBet(p, match) ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' :
                                           'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300']"
                                  :title="`Proba que ${p.name} mène 15-0 (gagne le 1er point du jeu) : ${p.value}%`+ (p.edge !== null && p.edge !== undefined ? ` (edge ${p.edge > 0 ? '+' : ''}${p.edge} pts)` : '')">
                                {{ isValueBet(p, match) ? '💰 ' : '🥇' }}{{ p.name }} 15-0: {{ Math.round(p.value) }}%
                            </span>
                            <span v-for="p in lostFirstPointOnServeProbabilities(match)" :key="'lostserve-' + p.name"
                                  :class="['text-[10px] font-semibold px-1.5 py-0.5 rounded whitespace-nowrap',
                                           isValueBet(p, match) ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' :
                                           'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300']"
                                  :title="`Proba que ${p.name} perde le 1er point sur SON service : ${p.value}%`+ (p.edge !== null && p.edge !== undefined ? ` (edge ${p.edge > 0 ? '+' : ''}${p.edge} pts)` : '')">
                                {{ isValueBet(p, match) ? '💰 ' : '🎾' }}{{ p.name }} perd 1er pt srv: {{ Math.round(p.value) }}%
                            </span>
                        </div>

                        <!-- Lien Sofascore -->
                        <div class="shrink-0 ml-auto">
                            <i v-if="sofascoreLink(match)"
                               class="pi pi-external-link text-xs text-surface-400 hover:text-primary-500"></i>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Sentinelle : déclenche loadMore() dès qu'elle entre dans le viewport -->
            <div ref="sentinel" class="h-1"></div>

            <!-- Indicateur de chargement de la page suivante -->
            <div v-if="loadingMore" class="flex items-center justify-center gap-2 py-4 text-sm text-surface-500 dark:text-surface-400">
                <i class="pi pi-spin pi-spinner"></i>
                Chargement des matchs suivants...
            </div>
        </div>

        <!-- Footer count -->
        <div v-if="matches.length > 0" class="text-xs text-surface-400 text-right">
            {{ matches.length }} / {{ totalCount }} match{{ totalCount > 1 ? 's' : '' }} affiché{{ matches.length > 1 ? 's' : '' }}
        </div>
    </div>
</template>
