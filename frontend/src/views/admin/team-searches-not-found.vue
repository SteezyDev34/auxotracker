<script setup>
import { ref, onMounted } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import AutoComplete from 'primevue/autocomplete';
import Toast from 'primevue/toast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { useToast } from 'primevue/usetoast';
import { TeamSearchService } from '@/service/TeamSearchService';
import { SportService } from '@/service/SportService';

const toast = useToast();

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL;
if (!apiBaseUrl) {
  throw new Error('VITE_API_BASE_URL must be set in environment (no fallback allowed).');
}

const loading = ref(false);
const searches = ref([]);
const currentPage = ref(1);
const lastPage = ref(1);
const totalSearches = ref(0);
const perPage = 50;

// Pour la sélection d'équipe
const teamSearchResults = ref({});
const selectedTeams = ref({});
const teamLoading = ref({});

const loadSearches = async () => {
  loading.value = true;
  try {
    const res = await TeamSearchService.getAll({
      page: currentPage.value,
      per_page: perPage,
      resolved: false
    });
    
    searches.value = res.data || [];
    if (res.pagination) {
      currentPage.value = res.pagination.current_page;
      lastPage.value = res.pagination.last_page;
      totalSearches.value = res.pagination.total;
    }
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les recherches' });
  } finally {
    loading.value = false;
  }
};

const onSearchTeams = async (event, searchId, sportId) => {
  if (!sportId) {
    toast.add({ severity: 'warn', summary: 'Attention', detail: 'Aucun sport associé à cette recherche' });
    return;
  }

  const query = event.query || '';
  teamLoading.value[searchId] = true;

  try {
    // Phase 1 : Charger rapidement les équipes prioritaires (priority > 0)
    const priorityResults = await SportService.searchTeamsBySport(sportId, query, 1, 20, null, null, true);
    
    // Afficher immédiatement les résultats prioritaires
    teamSearchResults.value[searchId] = priorityResults.data || [];

    // Phase 2 : Charger le reste des équipes en arrière-plan
    if (query.trim().length > 0 || priorityResults.data.length < 10) {
      const allResults = await SportService.searchTeamsBySport(sportId, query, 1, 50, null, null, false);
      
      // Fusionner en évitant les doublons (garder l'ordre prioritaire)
      const priorityIds = new Set(priorityResults.data.map(team => team.id));
      const nonPriorityResults = allResults.data.filter(team => !priorityIds.has(team.id));
      
      teamSearchResults.value[searchId] = [...priorityResults.data, ...nonPriorityResults];
    }
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les équipes' });
  } finally {
    teamLoading.value[searchId] = false;
  }
};

// Pré-charger les équipes lors de l'ouverture du dropdown
const onDropdownShow = async (searchId, sportId) => {
  if (!sportId) {
    toast.add({ severity: 'warn', summary: 'Attention', detail: 'Aucun sport associé à cette recherche' });
    return;
  }

  // Si déjà chargé, ne pas recharger
  if (teamSearchResults.value[searchId] && teamSearchResults.value[searchId].length > 0) {
    return;
  }

  teamLoading.value[searchId] = true;

  try {
    // Phase 1 : Charger rapidement uniquement les équipes prioritaires
    const priorityResults = await SportService.searchTeamsBySport(sportId, '', 1, 20, null, null, true);
    
    // Afficher immédiatement
    teamSearchResults.value[searchId] = priorityResults.data || [];

    // Phase 2 : Charger progressivement le reste
    const allResults = await SportService.searchTeamsBySport(sportId, '', 1, 50, null, null, false);
    
    // Fusionner en évitant les doublons
    const priorityIds = new Set(priorityResults.data.map(team => team.id));
    const nonPriorityResults = allResults.data.filter(team => !priorityIds.has(team.id));
    
    teamSearchResults.value[searchId] = [...priorityResults.data, ...nonPriorityResults];
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les équipes' });
  } finally {
    teamLoading.value[searchId] = false;
  }
};

const resolveSearch = async (search) => {
  if (!selectedTeams.value[search.id] || !selectedTeams.value[search.id][0]) {
    toast.add({ severity: 'warn', summary: 'Attention', detail: 'Veuillez sélectionner une équipe' });
    return;
  }

  const teamId = selectedTeams.value[search.id][0].id;

  try {
    await TeamSearchService.resolve(search.id, teamId);
    toast.add({ severity: 'success', summary: 'Succès', detail: 'Recherche associée avec succès' });
    
    // Retirer de la liste
    searches.value = searches.value.filter(s => s.id !== search.id);
    totalSearches.value--;
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message || 'Impossible d\'associer la recherche' });
  }
};

const deleteSearch = async (searchId) => {
  try {
    await TeamSearchService.delete(searchId);
    toast.add({ severity: 'success', summary: 'Succès', detail: 'Recherche supprimée' });
    
    searches.value = searches.value.filter(s => s.id !== searchId);
    totalSearches.value--;
  } catch (e) {
    console.error(e);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer la recherche' });
  }
};

const goToPage = (page) => {
  if (page < 1 || page > lastPage.value) return;
  currentPage.value = page;
  loadSearches();
};

onMounted(() => {
  loadSearches();
});
</script>

<template>
  <div class="p-4">
    <Toast />
    <Card>
      <template #title>
        <div class="flex justify-between items-center">
          <h2>Équipes non trouvées</h2>
          <Button 
            label="Actualiser" 
            icon="pi pi-refresh" 
            @click="loadSearches" 
            :loading="loading"
            size="small"
          />
        </div>
      </template>

      <template #content>
        <DataTable 
          :value="searches" 
          :loading="loading"
          responsiveLayout="scroll"
          class="p-datatable-sm"
        >
          <Column field="search_term" header="Terme recherché" :sortable="true">
            <template #body="{ data }">
              <strong>{{ data.search_term }}</strong>
            </template>
          </Column>

          <Column field="sport.name" header="Sport" :sortable="true">
            <template #body="{ data }">
              {{ data.sport ? data.sport.name : 'N/A' }}
            </template>
          </Column>

          <Column field="user.name" header="Utilisateur" :sortable="true">
            <template #body="{ data }">
              {{ data.user ? data.user.name : 'N/A' }}
            </template>
          </Column>

          <Column field="created_at" header="Date" :sortable="true">
            <template #body="{ data }">
              {{ new Date(data.created_at).toLocaleDateString('fr-FR') }}
            </template>
          </Column>

          <Column header="Associer à une équipe" class="w-1/3">
            <template #body="{ data }">
              <div class="flex gap-2 items-center">
                <AutoComplete
                  v-model="selectedTeams[data.id]"
                  :suggestions="teamSearchResults[data.id] || []"
                  @complete="(event) => onSearchTeams(event, data.id, data.sport_id)"
                  @show="() => onDropdownShow(data.id, data.sport_id)"
                  optionLabel="name"
                  :placeholder="data.sport_id ? 'Rechercher une équipe' : 'Aucun sport associé'"
                  class="flex-1"
                  :loading="teamLoading[data.id]"
                  :disabled="!data.sport_id"
                  dropdown
                  multiple
                  display="chip"
                  :forceSelection="false"
                  :minLength="0"
                >
                  <template #chip="slotProps">
                    <div class="flex items-center gap-2">
                      <img
                        v-if="slotProps.value && slotProps.value.id"
                        :src="`${apiBaseUrl}/storage/team_logos/${slotProps.value.id}.png`"
                        :alt="slotProps.value.name"
                        class="w-4 h-4 rounded object-cover"
                        @error="$event.target.style.display = 'none'"
                      />
                      <span>{{ slotProps.value ? slotProps.value.name : "" }}</span>
                    </div>
                  </template>

                  <template #option="slotProps">
                    <div class="flex items-center gap-2">
                      <img
                        :src="`${apiBaseUrl}/storage/team_logos/${slotProps.option.id}.png`"
                        :alt="slotProps.option.name"
                        class="w-5 h-5 object-contain"
                        @error="$event.target.style.display = 'none'"
                      />
                      <span>{{ slotProps.option.name }}</span>
                    </div>
                  </template>
                </AutoComplete>

                <Button 
                  label="Valider" 
                  icon="pi pi-check" 
                  @click="resolveSearch(data)"
                  size="small"
                  severity="success"
                />
                <Button 
                  icon="pi pi-trash" 
                  @click="deleteSearch(data.id)"
                  size="small"
                  severity="danger"
                  text
                />
              </div>
            </template>
          </Column>
        </DataTable>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="flex justify-center items-center gap-2 mt-4">
          <Button 
            icon="pi pi-angle-left" 
            @click="goToPage(currentPage - 1)" 
            :disabled="currentPage <= 1"
            size="small"
            text
          />
          <span>Page {{ currentPage }} / {{ lastPage }} ({{ totalSearches }} résultats)</span>
          <Button 
            icon="pi pi-angle-right" 
            @click="goToPage(currentPage + 1)" 
            :disabled="currentPage >= lastPage"
            size="small"
            text
          />
        </div>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.p-datatable-sm :deep(.p-datatable-tbody > tr > td) {
  padding: 0.5rem;
}
</style>
