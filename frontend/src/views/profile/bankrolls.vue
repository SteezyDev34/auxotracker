<template>
  <div class="grid">
    <div class="col-12">
      <div class="card">
        <h5>Mes Bankrolls</h5>
        <p>Gérez vos bankrolls pour suivre vos investissements sur différents bookmakers.</p>

        <!-- Formulaire d'ajout de bankroll -->
        <div class="card mb-4">
          <h6>Ajouter une nouvelle bankroll</h6>
          <div class="formgrid grid">
            <div class="field col-12 md:col-4">
              <label for="bankroll_name">Nom de la bankroll</label>
              <InputText 
                id="bankroll_name" 
                v-model="newBankroll.bankroll_name" 
                class="w-full"
                :class="{ 'p-invalid': v$.newBankroll.bankroll_name.$invalid && submitted }"
              />
              <small v-if="v$.newBankroll.bankroll_name.$invalid && submitted" class="p-error">Veuillez entrer un nom pour la bankroll</small>
            </div>

            <div class="field col-12 md:col-4">
              <label for="start_amount">Montant initial</label>
              <InputNumber 
                id="start_amount" 
                v-model="newBankroll.bankroll_start_amount" 
                mode="currency" 
                currency="EUR" 
                locale="fr-FR"
                class="w-full"
                :class="{ 'p-invalid': v$.newBankroll.bankroll_start_amount.$invalid && submitted }"
              />
              <small v-if="v$.newBankroll.bankroll_start_amount.$invalid && submitted" class="p-error">Veuillez entrer un montant initial valide</small>
            </div>

            <div class="field col-12 md:col-4">
              <label for="actual_amount">Montant actuel</label>
              <InputNumber 
                id="actual_amount" 
                v-model="newBankroll.bankroll_actual_amount" 
                mode="currency" 
                currency="EUR" 
                locale="fr-FR"
                class="w-full"
                :class="{ 'p-invalid': v$.newBankroll.bankroll_actual_amount.$invalid && submitted }"
              />
              <small v-if="v$.newBankroll.bankroll_actual_amount.$invalid && submitted" class="p-error">Veuillez entrer un montant actuel valide</small>
            </div>

            <div class="field col-12">
              <label for="description">Description</label>
              <Textarea 
                id="description" 
                v-model="newBankroll.bankroll_description" 
                rows="3" 
                class="w-full"
              />
            </div>

            <div class="field col-12">
              <Button label="Ajouter" icon="pi pi-plus" @click="addBankroll" :loading="loading" />
            </div>
          </div>
        </div>

        <!-- Liste des bankrolls -->
        <DataTable 
          :value="bankrolls" 
          :paginator="true" 
          :rows="10"
          :rowsPerPageOptions="[5, 10, 25, 50]"
          paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
          currentPageReportTemplate="{first} à {last} sur {totalRecords} bankrolls"
          responsiveLayout="scroll"
          stripedRows
          :loading="loading"
          v-model:filters="filters"
          filterDisplay="menu"
          :globalFilterFields="['bankroll_name']"
        >
          <template #header>
            <div class="flex justify-content-between">
              <Button type="button" icon="pi pi-filter-slash" label="Effacer" class="p-button-outlined" @click="clearFilter()" />
              <span class="p-input-icon-left">
                <i class="pi pi-search" />
                <InputText v-model="filters['global'].value" placeholder="Rechercher..." />
              </span>
            </div>
          </template>

          <Column field="bankroll_name" header="Nom" sortable>
            <template #filter="{ filterModel, filterCallback }">
              <InputText v-model="filterModel.value" @input="filterCallback()" placeholder="Rechercher par nom" class="p-column-filter" />
            </template>
          </Column>

          <Column field="bankroll_start_amount" header="Montant initial" sortable>
            <template #body="{ data }">
              {{ formatCurrency(data.bankroll_start_amount) }}
            </template>
          </Column>

          <Column field="bankroll_actual_amount" header="Montant actuel" sortable>
            <template #body="{ data }">
              {{ formatCurrency(data.bankroll_actual_amount) }}
            </template>
          </Column>

          <Column field="profit" header="Profit/Perte" sortable>
            <template #body="{ data }">
              <span :class="getProfitClass(data)">
                {{ formatCurrency(data.bankroll_actual_amount - data.bankroll_start_amount) }}
              </span>
            </template>
          </Column>

          <Column field="bookmakers_count" header="Bookmakers" sortable>
            <template #body="{ data }">
              <Tag :value="data.bookmakers_count || 0" severity="info" />
            </template>
          </Column>

          <Column field="created_at" header="Date de création" sortable>
            <template #body="{ data }">
              {{ formatDate(data.created_at) }}
            </template>
          </Column>

          <Column header="Actions" :exportable="false" style="min-width: 8rem">
            <template #body="{ data }">
              <Button icon="pi pi-pencil" class="p-button-rounded p-button-success mr-2" @click="editBankroll(data)" />
              <Button icon="pi pi-trash" class="p-button-rounded p-button-danger" @click="confirmDeleteBankroll(data)" />
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Dialog d'édition -->
    <Dialog v-model:visible="editDialog" header="Modifier la bankroll" :modal="true" class="p-fluid" :style="{ width: '450px' }">
      <div class="field">
        <label for="edit_name">Nom de la bankroll</label>
        <InputText 
          id="edit_name" 
          v-model="editingBankroll.bankroll_name" 
          class="w-full"
          :class="{ 'p-invalid': v$.editingBankroll.bankroll_name.$invalid && editSubmitted }"
        />
        <small v-if="v$.editingBankroll.bankroll_name.$invalid && editSubmitted" class="p-error">Veuillez entrer un nom pour la bankroll</small>
      </div>

      <div class="field">
        <label for="edit_start_amount">Montant initial</label>
        <InputNumber 
          id="edit_start_amount" 
          v-model="editingBankroll.bankroll_start_amount" 
          mode="currency" 
          currency="EUR" 
          locale="fr-FR"
          class="w-full"
          :class="{ 'p-invalid': v$.editingBankroll.bankroll_start_amount.$invalid && editSubmitted }"
        />
        <small v-if="v$.editingBankroll.bankroll_start_amount.$invalid && editSubmitted" class="p-error">Veuillez entrer un montant initial valide</small>
      </div>

      <div class="field">
        <label for="edit_actual_amount">Montant actuel</label>
        <InputNumber 
          id="edit_actual_amount" 
          v-model="editingBankroll.bankroll_actual_amount" 
          mode="currency" 
          currency="EUR" 
          locale="fr-FR"
          class="w-full"
          :class="{ 'p-invalid': v$.editingBankroll.bankroll_actual_amount.$invalid && editSubmitted }"
        />
        <small v-if="v$.editingBankroll.bankroll_actual_amount.$invalid && editSubmitted" class="p-error">Veuillez entrer un montant actuel valide</small>
      </div>

      <div class="field">
        <label for="edit_description">Description</label>
        <Textarea 
          id="edit_description" 
          v-model="editingBankroll.bankroll_description" 
          rows="3" 
          class="w-full"
        />
      </div>

      <template #footer>
        <Button label="Annuler" icon="pi pi-times" class="p-button-text" @click="hideEditDialog" />
        <Button label="Enregistrer" icon="pi pi-check" class="p-button-text" @click="updateBankroll" :loading="loading" />
      </template>
    </Dialog>

    <!-- Dialog de confirmation de suppression -->
    <Dialog v-model:visible="deleteDialog" :style="{ width: '450px' }" header="Confirmation" :modal="true">
      <div class="confirmation-content">
        <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
        <span>Êtes-vous sûr de vouloir supprimer <b>{{ editingBankroll.bankroll_name }}</b> ?</span>
      </div>
      <template #footer>
        <Button label="Non" icon="pi pi-times" class="p-button-text" @click="deleteDialog = false" />
        <Button label="Oui" icon="pi pi-check" class="p-button-text" @click="deleteBankroll" :loading="loading" />
      </template>
    </Dialog>
  </div>
</template>

<script>
import { ref, onMounted, reactive } from 'vue';
import { useToast } from 'primevue/usetoast';
import { FilterMatchMode } from '@primevue/core/api';
import { useVuelidate } from '@vuelidate/core';
import { required, minValue } from '@vuelidate/validators';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { BankrollService } from '../../service/BankrollService';
import { BookmakerService } from '../../service/BookmakerService';

export default {
  setup() {
    const toast = useToast();
    const loading = ref(false);
    const bankrolls = ref([]);
    const editDialog = ref(false);
    const deleteDialog = ref(false);
    const submitted = ref(false);
    const editSubmitted = ref(false);

    // Filtres pour le DataTable
    const filters = ref({
      global: { value: null, matchMode: FilterMatchMode.CONTAINS },
      'bankroll_name': { value: null, matchMode: FilterMatchMode.STARTS_WITH },
    });

    // Modèle pour une nouvelle bankroll
    const newBankroll = reactive({
      bankroll_name: '',
      bankroll_start_amount: 0,
      bankroll_actual_amount: 0,
      bankroll_description: null
    });

    // Modèle pour l'édition d'une bankroll
    const editingBankroll = reactive({
      id: null,
      bankroll_name: '',
      bankroll_start_amount: 0,
      bankroll_actual_amount: 0,
      bankroll_description: null,
      bookmakers_count: 0
    });

    // Règles de validation
    const rules = {
      newBankroll: {
        bankroll_name: { required },
        bankroll_start_amount: { required, minValue: minValue(0) },
        bankroll_actual_amount: { required, minValue: minValue(0) }
      },
      editingBankroll: {
        bankroll_name: { required },
        bankroll_start_amount: { required, minValue: minValue(0) },
        bankroll_actual_amount: { required, minValue: minValue(0) }
      }
    };

    const v$ = useVuelidate(rules, { newBankroll, editingBankroll });

    // Chargement des données
    onMounted(() => {
      loadBankrolls();
    });

    // Fonction pour charger les bankrolls
    const loadBankrolls = async () => {
      try {
        loading.value = true;
        const response = await BankrollService.getBankrolls();
        // Ajouter le nombre de bookmakers associés à chaque bankroll
        bankrolls.value = await Promise.all(response.bankrolls.map(async (bankroll) => {
          try {
            const bookmakers = await BookmakerService.getUserBookmakers();
            const count = bookmakers.filter(b => b.users_bankrolls_id === bankroll.id).length;
            return { ...bankroll, bookmakers_count: count };
          } catch (error) {
            console.error('Erreur lors du comptage des bookmakers:', error);
            return { ...bankroll, bookmakers_count: 0 };
          }
        }));
      } catch (error) {
        console.error('Erreur lors du chargement des bankrolls:', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les bankrolls', life: 3000 });
      } finally {
        loading.value = false;
      }
    };

    // Fonctions pour gérer les bankrolls
    const addBankroll = async () => {
      submitted.value = true;
      const isValid = await v$.value.newBankroll.$validate();

      if (!isValid) {
        return;
      }

      try {
        loading.value = true;
        const response = await BankrollService.createBankroll(newBankroll);
        bankrolls.value.push({ ...response.bankroll, bookmakers_count: 0 });
        toast.add({ severity: 'success', summary: 'Succès', detail: 'Bankroll créée avec succès', life: 3000 });
        resetNewBankroll();
        submitted.value = false;
      } catch (error) {
        console.error('Erreur lors de la création de la bankroll:', error);
        if (error.message) {
          toast.add({ severity: 'error', summary: 'Erreur', detail: error.message, life: 3000 });
        } else {
          toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de créer la bankroll', life: 3000 });
        }
      } finally {
        loading.value = false;
      }
    };

    const editBankroll = (data) => {
      editingBankroll.id = data.id;
      editingBankroll.bankroll_name = data.bankroll_name;
      editingBankroll.bankroll_start_amount = data.bankroll_start_amount;
      editingBankroll.bankroll_actual_amount = data.bankroll_actual_amount;
      editingBankroll.bankroll_description = data.bankroll_description;
      editingBankroll.bookmakers_count = data.bookmakers_count;
      editDialog.value = true;
    };

    const updateBankroll = async () => {
      editSubmitted.value = true;
      const isValid = await v$.value.editingBankroll.$validate();

      if (!isValid) {
        return;
      }

      try {
        loading.value = true;
        const bankrollData = {
          bankroll_name: editingBankroll.bankroll_name,
          bankroll_start_amount: editingBankroll.bankroll_start_amount,
          bankroll_actual_amount: editingBankroll.bankroll_actual_amount,
          bankroll_description: editingBankroll.bankroll_description
        };
        
        const response = await BankrollService.updateBankroll(editingBankroll.id, bankrollData);

        const index = bankrolls.value.findIndex(item => item.id === editingBankroll.id);
        if (index !== -1) {
          bankrolls.value[index] = { 
            ...response.bankroll, 
            bookmakers_count: editingBankroll.bookmakers_count 
          };
        }

        toast.add({ severity: 'success', summary: 'Succès', detail: 'Bankroll mise à jour avec succès', life: 3000 });
        hideEditDialog();
      } catch (error) {
        console.error('Erreur lors de la mise à jour de la bankroll:', error);
        if (error.message) {
          toast.add({ severity: 'error', summary: 'Erreur', detail: error.message, life: 3000 });
        } else {
          toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de mettre à jour la bankroll', life: 3000 });
        }
      } finally {
        loading.value = false;
      }
    };

    const confirmDeleteBankroll = (data) => {
      editingBankroll.id = data.id;
      editingBankroll.bankroll_name = data.bankroll_name;
      editingBankroll.bookmakers_count = data.bookmakers_count;
      deleteDialog.value = true;
    };

    const deleteBankroll = async () => {
      try {
        loading.value = true;
        await BankrollService.deleteBankroll(editingBankroll.id);
        bankrolls.value = bankrolls.value.filter(item => item.id !== editingBankroll.id);
        toast.add({ severity: 'success', summary: 'Succès', detail: 'Bankroll supprimée avec succès', life: 3000 });
        deleteDialog.value = false;
      } catch (error) {
        console.error('Erreur lors de la suppression de la bankroll:', error);
        if (error.message) {
          toast.add({ severity: 'error', summary: 'Erreur', detail: error.message, life: 3000 });
        } else {
          toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer la bankroll', life: 3000 });
        }
      } finally {
        loading.value = false;
      }
    };

    // Fonctions utilitaires
    const resetNewBankroll = () => {
      newBankroll.bankroll_name = '';
      newBankroll.bankroll_start_amount = 0;
      newBankroll.bankroll_actual_amount = 0;
      newBankroll.bankroll_description = null;
    };

    const hideEditDialog = () => {
      editDialog.value = false;
      editSubmitted.value = false;
    };

    const clearFilter = () => {
      filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        'bankroll_name': { value: null, matchMode: FilterMatchMode.STARTS_WITH },
      };
    };

    const formatCurrency = (value) => {
      return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(value);
    };

    const formatDate = (value) => {
      if (!value) return '';
      return format(new Date(value), 'dd MMMM yyyy', { locale: fr });
    };

    const getProfitClass = (data) => {
      const profit = data.bankroll_actual_amount - data.bankroll_start_amount;
      if (profit > 0) return 'text-green-500 font-bold';
      if (profit < 0) return 'text-red-500 font-bold';
      return '';
    };

    return {
      loading,
      bankrolls,
      newBankroll,
      editingBankroll,
      editDialog,
      deleteDialog,
      filters,
      submitted,
      editSubmitted,
      v$,
      addBankroll,
      editBankroll,
      updateBankroll,
      confirmDeleteBankroll,
      deleteBankroll,
      hideEditDialog,
      clearFilter,
      formatCurrency,
      formatDate,
      getProfitClass
    };
  }
};
</script>

<style scoped>
.p-button {
  margin-right: 0.5rem;
}

.p-datatable-header {
  display: flex;
  justify-content: space-between;
}

.confirmation-content {
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>