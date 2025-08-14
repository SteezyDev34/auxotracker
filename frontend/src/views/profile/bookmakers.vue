<template>
  <div class="grid">
    <div class="col-12">
      <div class="card">
        <div class="font-semibold text-xl">Mes Bookmakers</div>
        <p>Gérez vos bookmakers et leurs montants associés à vos bankrolls.</p>

        <!-- Formulaire d'ajout de bookmaker -->
        <div class="card mb-4 p-4 border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 rounded">
          <div class="font-semibold text-lg mb-3">Ajouter un bookmaker à votre compte</div>
          <div class="formgrid grid">
            <div class="field col-12 md:col-3">
              <label for="bookmaker" class="font-medium">Bookmaker</label>
              <Dropdown 
                id="bookmaker" 
                v-model="newUserBookmaker.bookmakers_id" 
                :options="bookmakers" 
                optionLabel="bookmaker_name" 
                optionValue="id" 
                placeholder="Sélectionnez un bookmaker"
                class="w-full"
                :class="{ 'p-invalid': v$.newUserBookmaker.bookmakers_id.$invalid && submitted }"
              />
              <small v-if="v$.newUserBookmaker.bookmakers_id.$invalid && submitted" class="p-error">Veuillez sélectionner un bookmaker</small>
            </div>

            <div class="field col-12 md:col-3">
              <label for="bankroll" class="font-medium">Bankroll</label>
              <Dropdown 
                id="bankroll" 
                v-model="newUserBookmaker.users_bankrolls_id" 
                :options="bankrolls" 
                optionLabel="bankroll_name" 
                optionValue="id" 
                placeholder="Sélectionnez une bankroll"
                class="w-full"
                :class="{ 'p-invalid': v$.newUserBookmaker.users_bankrolls_id.$invalid && submitted }"
              />
              <small v-if="v$.newUserBookmaker.users_bankrolls_id.$invalid && submitted" class="p-error">Veuillez sélectionner une bankroll</small>
            </div>

            <div class="field col-12 md:col-3">
              <label for="start_amount" class="font-medium">Montant initial</label>
              <InputNumber 
                id="start_amount" 
                v-model="newUserBookmaker.bookmaker_start_amount" 
                mode="currency" 
                currency="EUR" 
                locale="fr-FR"
                class="w-full"
                :class="{ 'p-invalid': v$.newUserBookmaker.bookmaker_start_amount.$invalid && submitted }"
              />
              <small v-if="v$.newUserBookmaker.bookmaker_start_amount.$invalid && submitted" class="p-error">Veuillez entrer un montant initial valide</small>
            </div>

            <div class="field col-12 md:col-3">
              <label for="actual_amount" class="font-medium">Montant actuel</label>
              <InputNumber 
                id="actual_amount" 
                v-model="newUserBookmaker.bookmaker_actual_amount" 
                mode="currency" 
                currency="EUR" 
                locale="fr-FR"
                class="w-full"
                :class="{ 'p-invalid': v$.newUserBookmaker.bookmaker_actual_amount.$invalid && submitted }"
              />
              <small v-if="v$.newUserBookmaker.bookmaker_actual_amount.$invalid && submitted" class="p-error">Veuillez entrer un montant actuel valide</small>
            </div>

            <div class="field col-12">
              <label for="comment" class="font-medium">Commentaire</label>
              <Textarea 
                id="comment" 
                v-model="newUserBookmaker.bookmaker_comment" 
                rows="3" 
                class="w-full"
              />
            </div>

            <div class="field col-12">
              <Button label="Ajouter" icon="pi pi-plus" @click="addUserBookmaker" :loading="loading" class="p-button-primary" />
            </div>
          </div>
        </div>

        <!-- Liste des bookmakers de l'utilisateur avec vue alternative -->
        <DataView :value="userBookmakers" :layout="layout" :paginator="true" :rows="9" :loading="loading" :filters="filters" :globalFilterFields="['bookmaker.bookmaker_name', 'bankroll.bankroll_name']">
          <template #header>
            <div class="flex justify-between">
              <div>
                <Button type="button" icon="pi pi-filter-slash" label="Effacer" class="p-button-outlined mr-2" @click="clearFilter()" />
                <span class="p-input-icon-left">
                  <i class="pi pi-search" />
                  <InputText v-model="filters['global'].value" placeholder="Rechercher..." />
                </span>
              </div>
              <div>
                <SelectButton v-model="layout" :options="layoutOptions" :allowEmpty="false">
                  <template #option="{ option }">
                    <i :class="[option === 'list' ? 'pi pi-bars' : 'pi pi-th-large']" />
                  </template>
                </SelectButton>
              </div>
            </div>
          </template>
          
          <!-- Template pour la vue liste -->
          <template #list="slotProps">
            <div class="flex flex-col">
              <div v-for="(item, index) in slotProps.items" :key="index">
                <div class="flex flex-col sm:flex-row sm:items-center p-6 gap-4" :class="{ 'border-t border-surface-200 dark:border-surface-700': index !== 0 }">
                  <div class="md:w-24 relative">
                    <img class="block mx-auto rounded w-full" :src="`/images/bookmakers/${item.bookmaker.bookmaker_img}`" :alt="item.bookmaker.bookmaker_name" />
                  </div>
                  <div class="flex flex-col md:flex-row justify-between md:items-center flex-1 gap-6">
                    <div class="flex flex-row md:flex-col justify-between items-start gap-2">
                      <div>
                        <span class="font-medium text-surface-500 dark:text-surface-400 text-sm">{{ item.bankroll.bankroll_name }}</span>
                        <div class="text-lg font-medium mt-2">{{ item.bookmaker.bookmaker_name }}</div>
                      </div>
                      <Tag :value="formatDate(item.created_at)" severity="info" class="text-xs" />
                    </div>
                    <div class="flex flex-col md:items-end gap-4">
                      <div class="flex flex-col">
                        <span class="text-sm text-surface-500">Montant initial</span>
                        <span class="text-lg font-medium">{{ formatCurrency(item.bookmaker_start_amount) }}</span>
                      </div>
                      <div class="flex flex-col">
                        <span class="text-sm text-surface-500">Montant actuel</span>
                        <span class="text-lg font-medium">{{ formatCurrency(item.bookmaker_actual_amount) }}</span>
                      </div>
                      <div class="flex flex-col">
                        <span class="text-sm text-surface-500">Profit/Perte</span>
                        <span :class="getProfitClass(item)" class="text-xl font-semibold">
                          {{ formatCurrency(item.bookmaker_actual_amount - item.bookmaker_start_amount) }}
                        </span>
                      </div>
                    </div>
                    <div class="flex flex-row gap-2">
                      <Button icon="pi pi-pencil" class="p-button-rounded p-button-success" @click="editUserBookmaker(item)" />
                      <Button icon="pi pi-trash" class="p-button-rounded p-button-danger" @click="confirmDeleteUserBookmaker(item)" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- Template pour la vue grille -->
          <template #grid="slotProps">
            <div class="grid grid-cols-12 gap-4">
              <div v-for="(item, index) in slotProps.items" :key="index" class="col-span-12 sm:col-span-6 lg:col-span-4 p-2">
                <div class="p-4 border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 rounded flex flex-col h-full grid-item">
                  <div class="flex justify-between items-start mb-3">
                    <div class="flex items-center gap-2">
                      <img class="rounded w-12" :src="`/images/bookmakers/${item.bookmaker.bookmaker_img}`" :alt="item.bookmaker.bookmaker_name" />
                      <div>
                        <div class="text-lg font-medium">{{ item.bookmaker.bookmaker_name }}</div>
                        <span class="font-medium text-surface-500 dark:text-surface-400 text-sm">{{ item.bankroll.bankroll_name }}</span>
                      </div>
                    </div>
                    <Tag :value="formatDate(item.created_at)" severity="info" class="text-xs" />
                  </div>
                  <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="col-span-1">
                      <div class="text-sm text-surface-500">Montant initial</div>
                      <div class="text-lg font-medium">{{ formatCurrency(item.bookmaker_start_amount) }}</div>
                    </div>
                    <div class="col-span-1">
                      <div class="text-sm text-surface-500">Montant actuel</div>
                      <div class="text-lg font-medium">{{ formatCurrency(item.bookmaker_actual_amount) }}</div>
                    </div>
                    <div class="col-span-2">
                      <div class="text-sm text-surface-500">Profit/Perte</div>
                      <div :class="getProfitClass(item)" class="text-xl font-semibold">
                        {{ formatCurrency(item.bookmaker_actual_amount - item.bookmaker_start_amount) }}
                      </div>
                    </div>
                  </div>
                  <div class="mt-auto pt-3 border-t border-surface-200 dark:border-surface-700">
                    <div class="flex justify-end gap-2">
                      <Button icon="pi pi-pencil" class="p-button-rounded p-button-success" @click="editUserBookmaker(item)" />
                      <Button icon="pi pi-trash" class="p-button-rounded p-button-danger" @click="confirmDeleteUserBookmaker(item)" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </DataView>
      </div>
    </div>

    <!-- Dialog d'édition -->
    <Dialog v-model:visible="editDialog" header="Modifier le bookmaker" :modal="true" class="p-fluid" :style="{ width: '450px' }">
      <div class="field">
        <label for="edit_bookmaker">Bookmaker</label>
        <div class="flex align-items-center gap-2">
          <img v-if="editingUserBookmaker.bookmaker" :src="`/images/bookmakers/${editingUserBookmaker.bookmaker.bookmaker_img}`" :alt="editingUserBookmaker.bookmaker.bookmaker_name" width="32" />
          <span>{{ editingUserBookmaker.bookmaker ? editingUserBookmaker.bookmaker.bookmaker_name : '' }}</span>
        </div>
      </div>

      <div class="field">
        <label for="edit_bankroll">Bankroll</label>
        <Dropdown 
          id="edit_bankroll" 
          v-model="editingUserBookmaker.users_bankrolls_id" 
          :options="bankrolls" 
          optionLabel="bankroll_name" 
          optionValue="id" 
          placeholder="Sélectionnez une bankroll"
          class="w-full"
          :class="{ 'p-invalid': v$.editingUserBookmaker.users_bankrolls_id.$invalid && editSubmitted }"
        />
        <small v-if="v$.editingUserBookmaker.users_bankrolls_id.$invalid && editSubmitted" class="p-error">Veuillez sélectionner une bankroll</small>
      </div>

      <div class="field">
        <label for="edit_start_amount">Montant initial</label>
        <InputNumber 
          id="edit_start_amount" 
          v-model="editingUserBookmaker.bookmaker_start_amount" 
          mode="currency" 
          currency="EUR" 
          locale="fr-FR"
          class="w-full"
          :class="{ 'p-invalid': v$.editingUserBookmaker.bookmaker_start_amount.$invalid && editSubmitted }"
        />
        <small v-if="v$.editingUserBookmaker.bookmaker_start_amount.$invalid && editSubmitted" class="p-error">Veuillez entrer un montant initial valide</small>
      </div>

      <div class="field">
        <label for="edit_actual_amount">Montant actuel</label>
        <InputNumber 
          id="edit_actual_amount" 
          v-model="editingUserBookmaker.bookmaker_actual_amount" 
          mode="currency" 
          currency="EUR" 
          locale="fr-FR"
          class="w-full"
          :class="{ 'p-invalid': v$.editingUserBookmaker.bookmaker_actual_amount.$invalid && editSubmitted }"
        />
        <small v-if="v$.editingUserBookmaker.bookmaker_actual_amount.$invalid && editSubmitted" class="p-error">Veuillez entrer un montant actuel valide</small>
      </div>

      <div class="field">
        <label for="edit_comment">Commentaire</label>
        <Textarea 
          id="edit_comment" 
          v-model="editingUserBookmaker.bookmaker_comment" 
          rows="3" 
          class="w-full"
        />
      </div>

      <template #footer>
        <Button label="Annuler" icon="pi pi-times" class="p-button-text" @click="hideEditDialog" />
        <Button label="Enregistrer" icon="pi pi-check" class="p-button-text" @click="updateUserBookmaker" :loading="loading" />
      </template>
    </Dialog>

    <!-- Dialog de confirmation de suppression -->
    <Dialog v-model:visible="deleteDialog" :style="{ width: '450px' }" header="Confirmation" :modal="true">
      <div class="confirmation-content">
        <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
        <span v-if="editingUserBookmaker.bookmaker">Êtes-vous sûr de vouloir supprimer <b>{{ editingUserBookmaker.bookmaker.bookmaker_name }}</b> ?</span>
      </div>
      <template #footer>
        <Button label="Non" icon="pi pi-times" class="p-button-text" @click="deleteDialog = false" />
        <Button label="Oui" icon="pi pi-check" class="p-button-text" @click="deleteUserBookmaker" :loading="loading" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useToast } from 'primevue/usetoast';
import { FilterMatchMode } from '@primevue/core/api';
import { useVuelidate } from '@vuelidate/core';
import { required, minValue } from '@vuelidate/validators';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import { BookmakerService } from '../../service/BookmakerService';
import { BankrollService } from '../../service/BankrollService';

// Import des composants PrimeVue
import DataView from 'primevue/dataview';
import SelectButton from 'primevue/selectbutton';
import Button from 'primevue/button';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';

const toast = useToast();
const loading = ref(false);
const bookmakers = ref([]);
const bankrolls = ref([]);
const userBookmakers = ref([]);
const editDialog = ref(false);
const deleteDialog = ref(false);
const submitted = ref(false);
const editSubmitted = ref(false);

// Options de mise en page (liste/grille)
const layout = ref('list');
const layoutOptions = ref(['list', 'grid']);

// Filtres pour le DataView
const filters = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
  'bookmaker.bookmaker_name': { value: null, matchMode: FilterMatchMode.STARTS_WITH },
  'bankroll.bankroll_name': { value: null, matchMode: FilterMatchMode.STARTS_WITH },
});

// Modèle pour un nouveau bookmaker utilisateur
const newUserBookmaker = reactive({
  bookmakers_id: null,
  users_bankrolls_id: null,
  bookmaker_start_amount: 0,
  bookmaker_actual_amount: 0,
  bookmaker_comment: null
});

// Modèle pour l'édition d'un bookmaker utilisateur
const editingUserBookmaker = reactive({
  id: null,
  bookmakers_id: null,
  users_bankrolls_id: null,
  bookmaker_start_amount: 0,
  bookmaker_actual_amount: 0,
  bookmaker_comment: null,
  bookmaker: null,
  bankroll: null
});

// Règles de validation
const rules = {
  newUserBookmaker: {
    bookmakers_id: { required },
    users_bankrolls_id: { required },
    bookmaker_start_amount: { required, minValue: minValue(0) },
    bookmaker_actual_amount: { required, minValue: minValue(0) }
  },
  editingUserBookmaker: {
    users_bankrolls_id: { required },
    bookmaker_start_amount: { required, minValue: minValue(0) },
    bookmaker_actual_amount: { required, minValue: minValue(0) }
  }
};

const v$ = useVuelidate(rules, { newUserBookmaker, editingUserBookmaker });

// Chargement des données
onMounted(() => {
  loadBookmakers();
  loadBankrolls();
  loadUserBookmakers();
});

// Fonctions pour charger les données
const loadBookmakers = async () => {
  try {
    loading.value = true;
    bookmakers.value = await BookmakerService.getBookmakers();
  } catch (error) {
    console.error('Erreur lors du chargement des bookmakers:', error);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les bookmakers', life: 3000 });
  } finally {
    loading.value = false;
  }
};

const loadBankrolls = async () => {
  try {
    loading.value = true;
    const response = await BankrollService.getBankrolls();
    bankrolls.value = response.bankrolls;
  } catch (error) {
    console.error('Erreur lors du chargement des bankrolls:', error);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les bankrolls', life: 3000 });
  } finally {
    loading.value = false;
  }
};

const loadUserBookmakers = async () => {
  try {
    loading.value = true;
    userBookmakers.value = await BookmakerService.getUserBookmakers();
  } catch (error) {
    console.error('Erreur lors du chargement des bookmakers utilisateur:', error);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger vos bookmakers', life: 3000 });
  } finally {
    loading.value = false;
  }
};

// Fonctions pour gérer les bookmakers utilisateur
const addUserBookmaker = async () => {
  submitted.value = true;
  const isValid = await v$.value.newUserBookmaker.$validate();

  if (!isValid) {
    return;
  }

  try {
    loading.value = true;
    const response = await BookmakerService.createUserBookmaker(newUserBookmaker);
    userBookmakers.value.push(response.user_bookmaker);
    toast.add({ severity: 'success', summary: 'Succès', detail: 'Bookmaker ajouté avec succès', life: 3000 });
    resetNewUserBookmaker();
    submitted.value = false;
  } catch (error) {
    console.error('Erreur lors de l\'ajout du bookmaker:', error);
    if (error.message) {
      toast.add({ severity: 'error', summary: 'Erreur', detail: error.message, life: 3000 });
    } else {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible d\'ajouter le bookmaker', life: 3000 });
    }
  } finally {
    loading.value = false;
  }
};

const editUserBookmaker = (data) => {
  editingUserBookmaker.id = data.id;
  editingUserBookmaker.bookmakers_id = data.bookmakers_id;
  editingUserBookmaker.users_bankrolls_id = data.users_bankrolls_id;
  editingUserBookmaker.bookmaker_start_amount = data.bookmaker_start_amount;
  editingUserBookmaker.bookmaker_actual_amount = data.bookmaker_actual_amount;
  editingUserBookmaker.bookmaker_comment = data.bookmaker_comment;
  editingUserBookmaker.bookmaker = data.bookmaker;
  editingUserBookmaker.bankroll = data.bankroll;
  editDialog.value = true;
};

const updateUserBookmaker = async () => {
  editSubmitted.value = true;
  const isValid = await v$.value.editingUserBookmaker.$validate();

  if (!isValid) {
    return;
  }

  try {
    loading.value = true;
    const bookmakerData = {
      users_bankrolls_id: editingUserBookmaker.users_bankrolls_id,
      bookmaker_start_amount: editingUserBookmaker.bookmaker_start_amount,
      bookmaker_actual_amount: editingUserBookmaker.bookmaker_actual_amount,
      bookmaker_comment: editingUserBookmaker.bookmaker_comment
    };
    
    const response = await BookmakerService.updateUserBookmaker(editingUserBookmaker.id, bookmakerData);

    const index = userBookmakers.value.findIndex(item => item.id === editingUserBookmaker.id);
    if (index !== -1) {
      userBookmakers.value[index] = response.user_bookmaker;
    }

    toast.add({ severity: 'success', summary: 'Succès', detail: 'Bookmaker mis à jour avec succès', life: 3000 });
    hideEditDialog();
  } catch (error) {
    console.error('Erreur lors de la mise à jour du bookmaker:', error);
    if (error.message) {
      toast.add({ severity: 'error', summary: 'Erreur', detail: error.message, life: 3000 });
    } else {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de mettre à jour le bookmaker', life: 3000 });
    }
  } finally {
    loading.value = false;
  }
};

const confirmDeleteUserBookmaker = (data) => {
  editingUserBookmaker.id = data.id;
  editingUserBookmaker.bookmaker = data.bookmaker;
  deleteDialog.value = true;
};

const deleteUserBookmaker = async () => {
  try {
    loading.value = true;
    await BookmakerService.deleteUserBookmaker(editingUserBookmaker.id);
    userBookmakers.value = userBookmakers.value.filter(item => item.id !== editingUserBookmaker.id);
    toast.add({ severity: 'success', summary: 'Succès', detail: 'Bookmaker supprimé avec succès', life: 3000 });
    deleteDialog.value = false;
  } catch (error) {
    console.error('Erreur lors de la suppression du bookmaker:', error);
    if (error.message) {
      toast.add({ severity: 'error', summary: 'Erreur', detail: error.message, life: 3000 });
    } else {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer le bookmaker', life: 3000 });
    }
  } finally {
    loading.value = false;
  }
};

// Fonctions utilitaires
const resetNewUserBookmaker = () => {
  newUserBookmaker.bookmakers_id = null;
  newUserBookmaker.users_bankrolls_id = null;
  newUserBookmaker.bookmaker_start_amount = 0;
  newUserBookmaker.bookmaker_actual_amount = 0;
  newUserBookmaker.bookmaker_comment = null;
};

const hideEditDialog = () => {
  editDialog.value = false;
  editSubmitted.value = false;
};

const clearFilter = () => {
  filters.value = {
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    'bookmaker.bookmaker_name': { value: null, matchMode: FilterMatchMode.STARTS_WITH },
    'bankroll.bankroll_name': { value: null, matchMode: FilterMatchMode.STARTS_WITH },
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
  const profit = data.bookmaker_actual_amount - data.bookmaker_start_amount;
  if (profit > 0) return 'text-green-500 font-bold';
  if (profit < 0) return 'text-red-500 font-bold';
  return '';
};
</script>

<style scoped>
.p-button {
  margin-right: 0.5rem;
}

.confirmation-content {
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Styles pour les cartes en vue grille */
.grid-item {
  transition: transform 0.2s, box-shadow 0.2s;
}

.grid-item:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

/* Styles pour les montants */
.text-green-500 {
  color: #22c55e;
}

.text-red-500 {
  color: #ef4444;
}
</style>