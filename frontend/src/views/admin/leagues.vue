<script setup>
import { ref, onMounted, computed } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Checkbox from 'primevue/checkbox';
import Skeleton from 'primevue/skeleton';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Sortable from 'sortablejs';
import { LeagueService } from '@/service/LeagueService';

const toast = useToast();

// API base (pour images)
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL;
if (!apiBaseUrl) {
  throw new Error('VITE_API_BASE_URL must be set in environment (no fallback allowed).');
}

const loading = ref(false);
const saving = ref(false);
const leagues = ref([]);

// Pagination
const currentPage = ref(1);
const lastPage = ref(1);
const totalLeagues = ref(0);
const perPage = 200;

// Filters
const sports = ref([]);
const countries = ref([]);
const selectedSport = ref('');
const selectedCountry = ref('');
const searchQuery = ref('');

const favoriteContainer = ref(null);
let sortableInstance = null;

const editDialog = ref(false);
const editedLeague = ref(null);

// Deletion modal state
const deleteDialog = ref(false);
const deletingLeague = ref(null);
const deleteConfirmText = ref('');
const deleteLoading = ref(false);

const loadLeagues = async (params = {}) => {
  loading.value = true;
  try {
    params.page = currentPage.value;
    params.per_page = perPage;
    const res = await LeagueService.getAll(params);
    leagues.value = (res.data || []).map((l) => ({ ...l }));
    if (res.pagination) {
      currentPage.value = res.pagination.current_page;
      lastPage.value = res.pagination.last_page;
      totalLeagues.value = res.pagination.total;
    }
    updateFavoriteLeagues();
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les ligues' });
  } finally {
    loading.value = false;
    initializeSortable();
  }
};

const buildFilterParams = () => {
  const params = {};
  if (selectedSport.value) params.sport_id = selectedSport.value;
  if (selectedCountry.value) params.country_id = selectedCountry.value;
  if (searchQuery.value) params.search = searchQuery.value;
  return params;
};

const goToPage = (page) => {
  if (page < 1 || page > lastPage.value) return;
  currentPage.value = page;
  loadLeagues(buildFilterParams());
};

const favoriteLeagues = ref([]);

const updateFavoriteLeagues = () => {
  favoriteLeagues.value = leagues.value
    .filter((l) => l.priority && l.priority > 0)
    .sort((a, b) => b.priority - a.priority);
};

const availableLeagues = computed(() => leagues.value.filter((l) => !(l.priority && l.priority > 0)));

const initializeSortable = () => {
  if (sortableInstance) sortableInstance.destroy();
  if (!favoriteContainer.value || favoriteLeagues.value.length === 0) return;

  sortableInstance = Sortable.create(favoriteContainer.value, {
    animation: 150,
    ghostClass: 'sortable-ghost',
    chosenClass: 'sortable-chosen',
    dragClass: 'sortable-drag',
    onEnd: async (evt) => {
      const oldIndex = evt.oldIndex;
      const newIndex = evt.newIndex;
      if (oldIndex === newIndex) return;

      // Reorder favoriteLeagues locally
      const moved = favoriteLeagues.value.splice(oldIndex, 1)[0];
      favoriteLeagues.value.splice(newIndex, 0, moved);

      // Compute new priorities (descending: top = highest number)
      const priorities = favoriteLeagues.value.map((l, idx) => ({ id: l.id, priority: favoriteLeagues.value.length - idx }));

      try {
        saving.value = true;
        await LeagueService.updatePriorities(priorities);

        // Reflect priorities into leagues list
        priorities.forEach((p) => {
          const found = leagues.value.find((x) => x.id === p.id);
          if (found) found.priority = p.priority;
        });

        toast.add({ severity: 'success', summary: 'Succès', detail: 'Priorités mises à jour' });
      } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de mettre à jour les priorités' });
      } finally {
        saving.value = false;
      }
    }
  });
};

const toggleFavorite = async (league) => {
  const newPriority = league.priority && league.priority > 0 ? 0 : 1;
  try {
    saving.value = true;
    await LeagueService.update(league.id, { priority: newPriority });
    // Update local state
    const found = leagues.value.find((l) => l.id === league.id);
    if (found) found.priority = newPriority;
    updateFavoriteLeagues();
    initializeSortable();
    toast.add({ severity: 'success', summary: 'Succès', detail: newPriority > 0 ? 'Ligues ajoutée aux favoris' : 'Ligues retirée des favoris' });
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible de mettre à jour l'état d'favori" });
  } finally {
    saving.value = false;
  }
};

const openEdit = (league) => {
  editedLeague.value = { ...league };
  editDialog.value = true;
};

const openDeleteDialog = (league) => {
  deletingLeague.value = league;
  deleteConfirmText.value = '';
  deleteDialog.value = true;
};

const closeDeleteDialog = () => {
  deleteDialog.value = false;
  deletingLeague.value = null;
  deleteConfirmText.value = '';
  deleteLoading.value = false;
};

const confirmDelete = async () => {
  if (!deletingLeague.value) return;
  if (deleteConfirmText.value.trim().toUpperCase() !== 'SUPPRIMER LIGUE') return;
  try {
    deleteLoading.value = true;
    await LeagueService.delete(deletingLeague.value.id);
    toast.add({ severity: 'success', summary: 'Supprimé', detail: 'Ligue supprimée' });
    closeDeleteDialog();
    // Reload current page; if page becomes empty, try previous page
    await loadLeagues(buildFilterParams());
    if (leagues.value.length === 0 && currentPage.value > 1) {
      currentPage.value = Math.max(1, currentPage.value - 1);
      await loadLeagues(buildFilterParams());
    }
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message || 'Erreur lors de la suppression' });
  } finally {
    deleteLoading.value = false;
  }
};

const saveEdit = async () => {
  if (!editedLeague.value) return;
  try {
    saving.value = true;
    await LeagueService.update(editedLeague.value.id, { nickname: editedLeague.value.nickname });
    toast.add({ severity: 'success', summary: 'Succès', detail: 'Surnom mis à jour' });
    await loadLeagues();
    editDialog.value = false;
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de sauvegarder' });
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  loadFilters();
  applyFilters();
});

const loadFilters = async () => {
  try {
    const s = await LeagueService.getSports();
    sports.value = s.data || [];
    // load all countries by default
    const c = await LeagueService.getCountries();
    countries.value = c.data || [];
  } catch (e) {
    console.error(e);
  }
};

const applyFilters = async () => {
  currentPage.value = 1;
  await loadLeagues(buildFilterParams());
};

// When sport changes, fetch countries for that sport (if available)
const onSportChange = async () => {
  if (selectedSport.value) {
    try {
      const res = await LeagueService.getCountriesBySport(selectedSport.value);
      countries.value = res.data || [];
    } catch (e) {
      console.error(e);
    }
  } else {
    // reload all countries
    try {
      const res = await LeagueService.getCountries();
      countries.value = res.data || [];
    } catch (e) {
      console.error(e);
    }
  }
};
</script>

<template>
  <div class="sports-preferences p-4">
    <Toast />

    <div class="mb-6 flex justify-between items-start">
      <div>
        <h2 class="text-2xl font-bold">Gestion Ligues</h2>
        <p class="text-surface-600">Sélectionnez vos ligues favorites et triez-les par ordre de préférence</p>
      </div>
      <div class="flex items-center gap-2">
        <input v-model="searchQuery" placeholder="Recherche..." class="border rounded p-2" />
        <select v-model="selectedSport" @change="onSportChange" class="border rounded p-2">
          <option value="">Tous les sports</option>
          <option v-for="s in sports" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-model="selectedCountry" class="border rounded p-2">
          <option value="">Tous les pays</option>
          <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <Button label="Rechercher" icon="pi pi-search" @click="applyFilters" />
        <Button label="Rafraîchir" icon="pi pi-refresh" :loading="loading" @click="applyFilters" />
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Available leagues -->
      <Card class="h-fit">
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-list text-primary"></i>
            <span>Ligues disponibles</span>
          </div>
        </template>
        <template #content>
          <div v-if="loading" class="space-y-3">
            <div v-for="n in 6" :key="n" class="flex items-center gap-3">
              <Skeleton width="1.5rem" height="1.5rem" />
              <Skeleton width="3rem" height="3rem" class="rounded-lg" />
              <Skeleton width="8rem" height="1.5rem" />
            </div>
          </div>

          <div v-else-if="availableLeagues.length === 0" class="text-center py-8">
            <i class="pi pi-check-circle text-4xl text-green-500 mb-3"></i>
            <p class="text-surface-600">Toutes les ligues sont sélectionnées !</p>
          </div>

          <div v-else class="space-y-3">
            <div v-for="league in availableLeagues" :key="league.id" class="flex items-center gap-3 p-3 border rounded-lg hover:bg-surface-50 transition-colors cursor-pointer" @click="toggleFavorite(league)">
              <Checkbox :model-value="false" :binary="true" />
              <img v-if="league.img" :src="`${apiBaseUrl}/storage/league_logos/${league.img}`" :alt="league.name" class="w-5 h-5 object-contain rounded-lg" />
              <div class="flex-1">
                <h4 class="font-semibold">{{ league.name }}</h4>
                <p v-if="league.country?.name" class="text-sm text-surface-600">{{ league.country.name }}</p>
              </div>
              <Button icon="pi pi-trash" class="p-button-sm" severity="danger" text @click.stop="openDeleteDialog(league)" />
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="lastPage > 1" class="flex items-center justify-between mt-4 pt-4 border-t">
            <span class="text-sm text-surface-500">{{ totalLeagues }} ligues — page {{ currentPage }}/{{ lastPage }}</span>
            <div class="flex items-center gap-1">
              <Button icon="pi pi-angle-double-left" class="p-button-sm p-button-text" :disabled="currentPage <= 1" @click="goToPage(1)" />
              <Button icon="pi pi-angle-left" class="p-button-sm p-button-text" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)" />
              <template v-for="p in lastPage" :key="p">
                <Button v-if="p === 1 || p === lastPage || (p >= currentPage - 2 && p <= currentPage + 2)" :label="String(p)" class="p-button-sm" :class="p === currentPage ? '' : 'p-button-text'" @click="goToPage(p)" />
                <span v-else-if="p === currentPage - 3 || p === currentPage + 3" class="px-1 text-surface-400">…</span>
              </template>
              <Button icon="pi pi-angle-right" class="p-button-sm p-button-text" :disabled="currentPage >= lastPage" @click="goToPage(currentPage + 1)" />
              <Button icon="pi pi-angle-double-right" class="p-button-sm p-button-text" :disabled="currentPage >= lastPage" @click="goToPage(lastPage)" />
            </div>
          </div>
        </template>
      </Card>

      <!-- Favorite leagues -->
      <Card class="h-fit">
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-star text-yellow-500"></i>
            <span>Vos ligues favoris</span>
            <span v-if="favoriteLeagues.length > 0" class="text-sm text-surface-500">({{ favoriteLeagues.length }})</span>
          </div>
        </template>

        <template #content>
          <div v-if="favoriteLeagues.length === 0" class="text-center py-8">
            <i class="pi pi-star-fill text-4xl text-surface-300 mb-3"></i>
            <p class="text-surface-600 mb-2">Aucune ligue sélectionnée</p>
            <p class="text-sm text-surface-500">Sélectionnez des ligues dans la liste de gauche</p>
          </div>

          <div v-else>
            <p class="text-sm text-surface-600 mb-4"><i class="pi pi-arrows-v mr-1"></i>Glissez-déposez pour réorganiser</p>

            <div ref="favoriteContainer" class="space-y-3">
              <div v-for="(league, index) in favoriteLeagues" :key="league.id" class="flex items-center gap-3 p-3 bg-primary-50 border border-primary-200 rounded-lg cursor-move">
                <div class="flex items-center justify-center w-6 h-6 bg-primary-500 rounded-full text-sm font-bold">{{ index + 1 }}</div>
                <i class="pi pi-bars text-surface-400 cursor-grab"></i>
                <img v-if="league.img" :src="`${apiBaseUrl}/storage/${league.img}`" :alt="league.name" class="w-5 h-5 object-contain rounded-lg" />
                <div class="flex-1">
                  <h4 class="font-semibold text-surface-500">{{ league.name }}</h4>
                  <p v-if="league.country?.name" class="text-sm text-surface-600">{{ league.country.name }}</p>
                </div>
                <Button icon="pi pi-pencil" class="p-button-sm" @click="openEdit(league)" />
                <Button icon="pi pi-times" class="p-button-sm" severity="danger" text @click="toggleFavorite(league)" />
                <Button icon="pi pi-trash" class="p-button-sm" severity="danger" text @click.stop="openDeleteDialog(league)" />
              </div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <Dialog header="Modifier surnom" v-model:visible="editDialog" :modal="true" :draggable="false">
      <div class="p-2">
        <label class="block mb-2">Surnom</label>
        <InputText v-model="editedLeague.nickname" placeholder="Surnom (nickname)" />
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <Button label="Annuler" class="p-button-text" @click="editDialog = false" />
          <Button label="Sauvegarder" icon="pi pi-save" @click="saveEdit" :loading="saving" />
        </div>
      </template>
    </Dialog>

    <Dialog header="Supprimer la ligue" v-model:visible="deleteDialog" :modal="true" :draggable="false">
      <div class="p-2">
        <p class="mb-2">Confirmez la suppression de la ligue <strong>{{ deletingLeague ? deletingLeague.name : '' }}</strong>.</p>
        <p class="mb-2 text-sm text-surface-500">Tapez <strong>SUPPRIMER LIGUE</strong> dans le champ ci-dessous pour confirmer.</p>
        <InputText v-model="deleteConfirmText" placeholder="SUPPRIMER LIGUE" />
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <Button label="Annuler" class="p-button-text" @click="closeDeleteDialog" :disabled="deleteLoading" />
          <Button label="Supprimer" class="p-button-danger" severity="danger" @click="confirmDelete" :loading="deleteLoading" :disabled="deleteConfirmText.trim().toUpperCase() !== 'SUPPRIMER LIGUE' || deleteLoading" />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.sports-preferences { padding: 1rem; }
.sortable-ghost { opacity: 0.4; }
.sortable-chosen { transform: scale(1.02); }
.sortable-drag { transform: rotate(5deg); }
</style>
