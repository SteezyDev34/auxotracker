<template>
  <Toast />
  <Fluid>
    <div class="flex flex-col gap-8">
      <!-- En-tête avec titre et actions -->
      <div class="card">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-2xl font-semibold">Gestion des Investissements</h2>
          <div class="flex gap-2">
            <Button
              @click="openCreateDialog"
              icon="pi pi-plus"
              label="Nouvel Investissement"
              severity="success"
              size="small"
            />
          </div>
        </div>

        <!-- Filtres -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div class="field">
            <label class="block text-sm font-medium mb-2">Utilisateur</label>
            <Select
              v-model="selectedUserId"
              :options="users"
              optionLabel="name"
              optionValue="id"
              placeholder="Tous les utilisateurs"
              class="w-full"
              @change="loadData"
            />
          </div>
          <div class="field">
            <label class="block text-sm font-medium mb-2">Statut</label>
            <Select
              v-model="selectedStatut"
              :options="statutOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Tous les statuts"
              class="w-full"
              @change="loadData"
            />
          </div>
          <div class="flex items-end">
            <Button
              @click="clearFilters"
              label="Réinitialiser"
              class="p-button-outlined w-full"
              icon="pi pi-refresh"
            />
          </div>
        </div>
      </div>

      <!-- Statistiques globales -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card text-center">
          <div class="text-2xl font-bold text-blue-600 mb-2">
            {{ stats.total_investissements }}
          </div>
          <div class="text-sm text-gray-600">Total Investissements</div>
        </div>
        <div class="card text-center">
          <div class="text-2xl font-bold text-green-600 mb-2">
            {{ formatCurrency(stats.total_actif) }}
          </div>
          <div class="text-sm text-gray-600">Investissements Actifs</div>
        </div>
        <div class="card text-center">
          <div class="text-2xl font-bold text-orange-600 mb-2">
            {{ formatCurrency(stats.total_inactif) }}
          </div>
          <div class="text-sm text-gray-600">Investissements Inactifs</div>
        </div>
        <div class="card text-center">
          <div class="text-2xl font-bold text-red-600 mb-2">
            {{ formatCurrency(stats.total_retire) }}
          </div>
          <div class="text-sm text-gray-600">Investissements Retirés</div>
        </div>
      </div>

      <!-- Liste des investissements -->
      <div class="card">
        <h3 class="text-xl font-semibold mb-4">
          Liste des Investissements ({{ investments.length }})
        </h3>

        <div v-if="loading" class="text-center py-8">
          <i class="pi pi-spin pi-spinner text-2xl"></i>
          <p class="mt-2 text-muted-color">Chargement des investissements...</p>
        </div>

        <div v-else-if="error" class="text-center py-8 text-red-600">
          <i class="pi pi-exclamation-triangle text-2xl"></i>
          <p class="mt-2">{{ error }}</p>
        </div>

        <div
          v-else-if="investments.length === 0"
          class="text-center py-8 text-muted-color"
        >
          <i class="pi pi-info-circle text-2xl"></i>
          <p class="mt-2">Aucun investissement trouvé</p>
        </div>

        <DataTable
          v-else
          :value="investments"
          :paginator="true"
          :rows="10"
          :rowsPerPageOptions="[5, 10, 20, 50]"
          paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
          currentPageReportTemplate="Affichage de {first} à {last} sur {totalRecords} entrées"
          stripedRows
          sortMode="multiple"
          removableSort
          class="p-datatable-sm"
        >
          <Column field="user.name" header="Utilisateur" sortable>
            <template #body="slotProps">
              <div class="flex items-center gap-2">
                <img
                  v-if="slotProps.data.user.avatar"
                  :src="slotProps.data.user.avatar"
                  :alt="slotProps.data.user.name"
                  class="w-6 h-6 rounded-full"
                />
                <div
                  v-else
                  class="w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-xs font-semibold text-primary-600 dark:text-primary-400"
                >
                  {{ slotProps.data.user.name.charAt(0).toUpperCase() }}
                </div>
                <span>{{ slotProps.data.user.name }}</span>
              </div>
            </template>
          </Column>

          <Column field="montant_investi" header="Montant" sortable>
            <template #body="slotProps">
              <span class="font-semibold">
                {{ formatCurrency(slotProps.data.montant_investi) }}
              </span>
            </template>
          </Column>

          <Column field="date_investissement" header="Date" sortable>
            <template #body="slotProps">
              {{ formatDate(slotProps.data.date_investissement) }}
            </template>
          </Column>

          <Column field="statut" header="Statut" sortable>
            <template #body="slotProps">
              <Tag
                :value="getStatutLibelle(slotProps.data.statut)"
                :severity="getStatutSeverity(slotProps.data.statut)"
              />
            </template>
          </Column>

          <Column header="Actions" style="width: 8rem">
            <template #body="slotProps">
              <div class="flex gap-1">
                <Button
                  @click="editInvestment(slotProps.data)"
                  icon="pi pi-pencil"
                  size="small"
                  class="p-button-rounded p-button-outlined"
                  v-tooltip.top="'Modifier'"
                />
                <Button
                  @click="deleteInvestment(slotProps.data)"
                  icon="pi pi-trash"
                  size="small"
                  severity="danger"
                  class="p-button-rounded p-button-outlined"
                  v-tooltip.top="'Supprimer'"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>
  </Fluid>

  <!-- Dialog de création/modification -->
  <Dialog
    v-model:visible="showDialog"
    :header="
      editingInvestment ? 'Modifier l\'investissement' : 'Nouvel investissement'
    "
    :modal="true"
    :closable="true"
    :draggable="false"
    class="w-full max-w-md"
  >
    <div class="space-y-4">
      <div class="field">
        <label class="block text-sm font-medium mb-2">Utilisateur *</label>
        <Select
          v-model="investmentForm.user_id"
          :options="users"
          optionLabel="name"
          optionValue="id"
          placeholder="Sélectionner un utilisateur"
          class="w-full"
          :disabled="editingInvestment"
        />
      </div>

      <div class="field">
        <label class="block text-sm font-medium mb-2">Montant investi *</label>
        <InputNumber
          v-model="investmentForm.montant_investi"
          mode="currency"
          currency="EUR"
          locale="fr-FR"
          class="w-full"
          :min="0.01"
          :max="1000000"
          :minFractionDigits="2"
          :maxFractionDigits="2"
        />
      </div>

      <div class="field">
        <label class="block text-sm font-medium mb-2"
          >Date d'investissement *</label
        >
        <DatePicker
          v-model="investmentForm.date_investissement"
          dateFormat="dd/mm/yy"
          class="w-full"
          showIcon
          :maxDate="new Date()"
        />
      </div>

      <div class="field">
        <label class="block text-sm font-medium mb-2">Statut</label>
        <Select
          v-model="investmentForm.statut"
          :options="statutOptions"
          optionLabel="label"
          optionValue="value"
          class="w-full"
        />
      </div>

      <div class="field">
        <label class="block text-sm font-medium mb-2">Commentaire</label>
        <Textarea
          v-model="investmentForm.commentaire"
          rows="3"
          class="w-full"
          maxlength="1000"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          @click="showDialog = false"
          label="Annuler"
          class="p-button-outlined"
        />
        <Button
          @click="saveInvestment"
          :label="editingInvestment ? 'Modifier' : 'Créer'"
          :loading="saving"
        />
      </div>
    </template>
  </Dialog>

  <!-- Dialog de confirmation de suppression -->
  <Dialog
    v-model:visible="showDeleteDialog"
    header="Confirmer la suppression"
    :modal="true"
    :closable="true"
    :draggable="false"
    class="w-full max-w-md"
  >
    <div class="flex items-center gap-3 mb-4">
      <i class="pi pi-exclamation-triangle text-2xl text-orange-500"></i>
      <div>
        <p class="text-lg font-semibold">Supprimer cet investissement ?</p>
        <p class="text-sm text-muted-color mt-1">
          Cette action est irréversible.
        </p>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          @click="showDeleteDialog = false"
          label="Annuler"
          class="p-button-outlined"
        />
        <Button
          @click="confirmDelete"
          label="Supprimer"
          severity="danger"
          :loading="deleting"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import { useToast } from "primevue/usetoast";
import Toast from "primevue/toast";
import Button from "primevue/button";
import Select from "primevue/select";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Tag from "primevue/tag";
import Dialog from "primevue/dialog";
import InputNumber from "primevue/inputnumber";
import DatePicker from "primevue/datepicker";
import Textarea from "primevue/textarea";
import Fluid from "primevue/fluid";
import Tooltip from "primevue/tooltip";
import { InvestmentService } from "@/service/InvestmentService.js";
import { UserService } from "@/service/UserService.js";

const vTooltip = Tooltip;
const toast = useToast();

// États réactifs
const loading = ref(false);
const error = ref(null);
const saving = ref(false);
const deleting = ref(false);

// Données
const investments = ref([]);
const users = ref([]);
const stats = ref({
  total_investissements: 0,
  total_actif: 0,
  total_inactif: 0,
  total_retire: 0,
});

// Filtres
const selectedUserId = ref(null);
const selectedStatut = ref(null);
const statutOptions = ref([
  { label: "Actif", value: "actif" },
  { label: "Inactif", value: "inactif" },
  { label: "Retiré", value: "retire" },
]);

// Dialog
const showDialog = ref(false);
const showDeleteDialog = ref(false);
const editingInvestment = ref(null);
const investmentToDelete = ref(null);

const investmentForm = reactive({
  user_id: null,
  montant_investi: null,
  date_investissement: new Date(),
  statut: "actif",
  commentaire: "",
});

// Méthodes utilitaires
const formatCurrency = (value) => {
  return new Intl.NumberFormat("fr-FR", {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(value || 0);
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString("fr-FR", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

const getStatutLibelle = (statut) => {
  switch (statut) {
    case "actif":
      return "Actif";
    case "inactif":
      return "Inactif";
    case "retire":
      return "Retiré";
    default:
      return "Inconnu";
  }
};

const getStatutSeverity = (statut) => {
  switch (statut) {
    case "actif":
      return "success";
    case "inactif":
      return "warning";
    case "retire":
      return "danger";
    default:
      return "info";
  }
};

// Méthodes de chargement des données
const loadInvestments = async () => {
  try {
    const params = {};
    if (selectedUserId.value) {
      params.user_id = selectedUserId.value;
    }
    if (selectedStatut.value) {
      params.statut = selectedStatut.value;
    }

    const response = await InvestmentService.getInvestments(params);
    investments.value = response.data || [];
  } catch (err) {
    console.error("Erreur lors du chargement des investissements:", err);
    throw err;
  }
};

const loadStats = async () => {
  try {
    const response = await InvestmentService.getInvestmentStats();
    stats.value = response.data || {};
  } catch (err) {
    console.error("Erreur lors du chargement des statistiques:", err);
    throw err;
  }
};

const loadUsers = async () => {
  try {
    // Utilisation de l'API admin pour récupérer tous les utilisateurs
    const response = await UserService.getUsers();
    users.value = response.data || [];
  } catch (err) {
    console.error("Erreur lors du chargement des utilisateurs:", err);
  }
};

const loadData = async () => {
  loading.value = true;
  error.value = null;

  try {
    await Promise.all([loadInvestments(), loadStats()]);
  } catch (err) {
    error.value = "Erreur lors du chargement des données";
    toast.add({
      severity: "error",
      summary: "Erreur",
      detail: "Impossible de charger les données des investissements",
      life: 3000,
    });
  } finally {
    loading.value = false;
  }
};

const clearFilters = () => {
  selectedUserId.value = null;
  selectedStatut.value = null;
  loadData();
};

// Méthodes de gestion des investissements
const openCreateDialog = () => {
  editingInvestment.value = null;
  Object.assign(investmentForm, {
    user_id: null,
    montant_investi: null,
    date_investissement: new Date(),
    statut: "actif",
    commentaire: "",
  });
  showDialog.value = true;
};

const editInvestment = (investment) => {
  editingInvestment.value = investment;
  Object.assign(investmentForm, {
    user_id: investment.user_id,
    montant_investi: investment.montant_investi,
    date_investissement: new Date(investment.date_investissement),
    statut: investment.statut,
    commentaire: investment.commentaire || "",
  });
  showDialog.value = true;
};

const saveInvestment = async () => {
  if (!investmentForm.user_id || !investmentForm.montant_investi) {
    toast.add({
      severity: "warn",
      summary: "Attention",
      detail: "Veuillez remplir tous les champs obligatoires",
      life: 3000,
    });
    return;
  }

  saving.value = true;
  try {
    const data = {
      ...investmentForm,
      date_investissement: investmentForm.date_investissement
        .toISOString()
        .split("T")[0],
    };

    if (editingInvestment.value) {
      await InvestmentService.updateInvestment(
        editingInvestment.value.id,
        data
      );
      toast.add({
        severity: "success",
        summary: "Succès",
        detail: "Investissement modifié avec succès",
        life: 3000,
      });
    } else {
      await InvestmentService.createInvestment(data);
      toast.add({
        severity: "success",
        summary: "Succès",
        detail: "Investissement créé avec succès",
        life: 3000,
      });
    }

    showDialog.value = false;
    await loadData();
  } catch (err) {
    toast.add({
      severity: "error",
      summary: "Erreur",
      detail: "Erreur lors de la sauvegarde de l'investissement",
      life: 3000,
    });
  } finally {
    saving.value = false;
  }
};

const deleteInvestment = (investment) => {
  investmentToDelete.value = investment;
  showDeleteDialog.value = true;
};

const confirmDelete = async () => {
  deleting.value = true;
  try {
    await InvestmentService.deleteInvestment(investmentToDelete.value.id);
    toast.add({
      severity: "success",
      summary: "Succès",
      detail: "Investissement supprimé avec succès",
      life: 3000,
    });
    showDeleteDialog.value = false;
    await loadData();
  } catch (err) {
    toast.add({
      severity: "error",
      summary: "Erreur",
      detail: "Erreur lors de la suppression de l'investissement",
      life: 3000,
    });
  } finally {
    deleting.value = false;
  }
};

// Initialisation
onMounted(async () => {
  await loadUsers();
  await loadData();
});
</script>

<style scoped>
.field label {
  color: #374151;
}
</style>
