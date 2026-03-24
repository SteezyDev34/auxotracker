<template>
  <Toast />
  <Fluid>
    <div class="flex flex-col gap-8">
      <!-- En-tête avec titre et filtres -->
      <div class="card">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-2xl font-semibold">Mes Intérêts</h2>
          <div class="flex gap-2">
            <Button
              @click="exportToCSV"
              icon="pi pi-download"
              label="Export CSV"
              class="p-button-outlined"
              :loading="exportLoading"
              size="small"
            />
          </div>
        </div>

        <!-- Filtres -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div class="field">
            <label class="block text-sm font-medium mb-2">Période</label>
            <Select
              v-model="selectedPeriod"
              :options="periods"
              optionLabel="label"
              optionValue="value"
              placeholder="Sélectionner une période"
              class="w-full"
              @change="loadData"
            />
          </div>
          <div class="field">
            <label class="block text-sm font-medium mb-2"
              >Moyen de paiement</label
            >
            <Select
              v-model="selectedMoyenPaiement"
              :options="moyensPaiement"
              optionLabel="label"
              optionValue="value"
              placeholder="Tous les moyens"
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

      <!-- Statistiques -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card text-center">
          <div class="text-2xl font-bold text-blue-600 mb-2">
            {{ formatCurrency(stats.total_investi) }}
          </div>
          <div class="text-sm text-gray-600">Montant Total Investi</div>
        </div>
        <div class="card text-center">
          <div class="text-2xl font-bold text-green-600 mb-2">
            {{ formatCurrency(stats.total_interets) }}
          </div>
          <div class="text-sm text-gray-600">Total des Intérêts Perçus</div>
        </div>
        <div class="card text-center">
          <div class="text-2xl font-bold text-purple-600 mb-2">
            {{ stats.taux_rendement }}%
          </div>
          <div class="text-sm text-gray-600">Taux de Rendement</div>
        </div>
      </div>

      <!-- Liste des intérêts -->
      <div class="card">
        <h3 class="text-xl font-semibold mb-4">
          Historique des Versements ({{ interets.length }})
        </h3>

        <div v-if="loading" class="text-center py-8">
          <i class="pi pi-spin pi-spinner text-2xl"></i>
          <p class="mt-2 text-muted-color">Chargement des intérêts...</p>
        </div>

        <div v-else-if="error" class="text-center py-8 text-red-600">
          <i class="pi pi-exclamation-triangle text-2xl"></i>
          <p class="mt-2">{{ error }}</p>
        </div>

        <div
          v-else-if="interets.length === 0"
          class="text-center py-8 text-muted-color"
        >
          <i class="pi pi-info-circle text-2xl"></i>
          <p class="mt-2">Aucun versement d'intérêts trouvé</p>
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="interet in interets"
            :key="interet.id"
            class="flex items-center justify-between p-4 bg-surface-50 dark:bg-surface-800 rounded-lg border border-surface-200 dark:border-surface-700"
          >
            <div class="flex-1">
              <div class="flex items-center gap-3 mb-2">
                <i
                  :class="getMoyenPaiementIcon(interet.moyen_paiement)"
                  class="text-lg"
                  :style="{
                    color: getMoyenPaiementColor(interet.moyen_paiement),
                  }"
                ></i>
                <div>
                  <div class="font-semibold">
                    {{ getMoyenPaiementLibelle(interet.moyen_paiement) }}
                  </div>
                  <div class="text-sm text-muted-color">
                    {{ formatDate(interet.date_versement) }}
                  </div>
                </div>
              </div>
              <div class="text-sm text-muted-color">
                <strong>Détail :</strong> {{ getDetailPaiement(interet) }}
              </div>
              <div class="text-sm text-muted-color mt-1">
                <strong>Montant total investi :</strong>
                {{
                  formatCurrency(interet.montant_total_investi_date_versement)
                }}
              </div>
            </div>
            <div class="text-right">
              <div class="text-lg font-bold text-green-600">
                +{{ formatCurrency(interet.montant_interet) }}
              </div>
              <div class="text-sm text-muted-color">
                {{
                  (
                    (interet.montant_interet /
                      interet.montant_total_investi_date_versement) *
                    100
                  ).toFixed(1)
                }}%
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Fluid>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useToast } from "primevue/usetoast";
import Toast from "primevue/toast";
import Button from "primevue/button";
import Select from "primevue/select";
import Fluid from "primevue/fluid";
import { InteretService } from "@/service/InteretService.js";
import { useAuth } from "@/composables/useAuth.js";

const toast = useToast();

// États réactifs
const loading = ref(false);
const error = ref(null);
const exportLoading = ref(false);

// Données
const interets = ref([]);
const stats = ref({
  total_investi: 0,
  total_interets: 0,
  taux_rendement: 0,
  total_versements: 0,
});

// Filtres
const selectedPeriod = ref("all");
const selectedMoyenPaiement = ref(null);
const periods = ref([
  { label: "3 mois", value: "3m" },
  { label: "6 mois", value: "6m" },
  { label: "1 an", value: "1an" },
  { label: "Tout", value: "all" },
]);
const moyensPaiement = ref([]);

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

const getMoyenPaiementIcon = (moyen) => {
  switch (moyen) {
    case "paypal":
      return "pi pi-paypal";
    case "virement_bancaire":
      return "pi pi-building";
    default:
      return "pi pi-wallet";
  }
};

const getMoyenPaiementColor = (moyen) => {
  switch (moyen) {
    case "paypal":
      return "#0070ba";
    case "virement_bancaire":
      return "#2563eb";
    default:
      return "#6b7280";
  }
};

const getMoyenPaiementLibelle = (moyen) => {
  switch (moyen) {
    case "paypal":
      return "PayPal";
    case "virement_bancaire":
      return "Virement bancaire";
    default:
      return "Autre";
  }
};

const getDetailPaiement = (interet) => {
  if (!interet.detail_paiement) {
    return "Non spécifié";
  }

  switch (interet.moyen_paiement) {
    case "paypal":
      return interet.detail_paiement;
    case "virement_bancaire":
      // Masquer l'IBAN
      const iban = interet.detail_paiement;
      if (iban.length > 8) {
        const debut = iban.substring(0, 4);
        const fin = iban.substring(iban.length - 4);
        const milieu = "*".repeat(iban.length - 8);
        return `${debut} ${milieu} ${fin}`;
      }
      return iban;
    default:
      return interet.detail_paiement;
  }
};

// Méthodes de chargement des données
const loadInterets = async () => {
  try {
    const params = {};
    if (selectedPeriod.value && selectedPeriod.value !== "all") {
      params.period = selectedPeriod.value;
    }
    if (selectedMoyenPaiement.value) {
      params.moyen_paiement = selectedMoyenPaiement.value;
    }

    const response = await InteretService.getInterets(params);
    interets.value = response.data || [];
  } catch (err) {
    console.error("Erreur lors du chargement des intérêts:", err);
    throw err;
  }
};

const loadStats = async () => {
  try {
    const params = {};
    if (selectedPeriod.value && selectedPeriod.value !== "all") {
      params.period = selectedPeriod.value;
    }
    if (selectedMoyenPaiement.value) {
      params.moyen_paiement = selectedMoyenPaiement.value;
    }

    const response = await InteretService.getInteretStats(params);
    stats.value = response.data || {};
  } catch (err) {
    console.error("Erreur lors du chargement des statistiques:", err);
    throw err;
  }
};

const loadFilterOptions = async () => {
  try {
    const response = await InteretService.getFilterOptions();
    moyensPaiement.value = response.data.moyens_paiement || [];
  } catch (err) {
    console.error("Erreur lors du chargement des options de filtres:", err);
  }
};

const loadData = async () => {
  loading.value = true;
  error.value = null;

  try {
    await Promise.all([loadInterets(), loadStats()]);
  } catch (err) {
    error.value = "Erreur lors du chargement des données";
    toast.add({
      severity: "error",
      summary: "Erreur",
      detail: "Impossible de charger les données des intérêts",
      life: 3000,
    });
  } finally {
    loading.value = false;
  }
};

const clearFilters = () => {
  selectedPeriod.value = "all";
  selectedMoyenPaiement.value = null;
  loadData();
};

const exportToCSV = async () => {
  exportLoading.value = true;
  try {
    const params = {};
    if (selectedPeriod.value && selectedPeriod.value !== "all") {
      params.period = selectedPeriod.value;
    }
    if (selectedMoyenPaiement.value) {
      params.moyen_paiement = selectedMoyenPaiement.value;
    }

    await InteretService.exportToCSV(params);
    toast.add({
      severity: "success",
      summary: "Succès",
      detail: "Export CSV téléchargé avec succès",
      life: 3000,
    });
  } catch (err) {
    toast.add({
      severity: "error",
      summary: "Erreur",
      detail: "Erreur lors de l'export CSV",
      life: 3000,
    });
  } finally {
    exportLoading.value = false;
  }
};

// Initialisation
onMounted(async () => {
  await loadFilterOptions();
  await loadData();
});
</script>

<style scoped>
.field label {
  color: #374151;
}
</style>
