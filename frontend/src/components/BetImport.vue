<template>
  <div class="bet-import-container">
    <!-- Header -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-upload me-2"></i>
          Importer des paris depuis JSON
        </h5>
      </div>

      <div class="card-body">
        <p class="text-muted mb-3">
          Importez vos paris depuis un fichier JSON. Le format attendu inclut
          les champs :
          <code>date</code>, <code>statut</code>, <code>mise</code>,
          <code>cote</code>, <code>sport</code>, etc.
        </p>

        <!-- Sélection de bankroll -->
        <div class="row mb-3">
          <div class="col-md-6">
            <label for="bankroll-select" class="form-label"
              >Bankroll de destination</label
            >
            <select
              id="bankroll-select"
              v-model="selectedBankrollId"
              class="form-select"
              :disabled="isImporting"
            >
              <option value="">Sélectionner une bankroll</option>
              <option
                v-for="bankroll in bankrolls"
                :key="bankroll.id"
                :value="bankroll.id"
              >
                {{ bankroll.name }} ({{
                  formatCurrency(bankroll.initial_amount)
                }})
              </option>
            </select>
          </div>
        </div>

        <!-- Zone de dépôt de fichier -->
        <div class="mb-4">
          <label for="json-file" class="form-label">Fichier JSON</label>
          <div
            class="file-drop-zone"
            :class="{
              dragover: isDragOver,
              'has-file': jsonData,
              'has-error': hasFileError,
            }"
            @dragover.prevent="isDragOver = true"
            @dragleave.prevent="isDragOver = false"
            @drop.prevent="handleFileDrop"
            @click="$refs.fileInput.click()"
          >
            <input
              ref="fileInput"
              type="file"
              accept=".json"
              @change="handleFileSelect"
              class="d-none"
            />

            <div v-if="!jsonData" class="text-center py-4">
              <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
              <p class="mb-2">
                Glissez-déposez votre fichier JSON ici ou cliquez pour
                sélectionner
              </p>
              <small class="text-muted">Formats acceptés: .json</small>
            </div>

            <div v-else class="text-center py-3">
              <i class="fas fa-file-alt fa-2x text-success mb-2"></i>
              <p class="mb-1 fw-bold">{{ fileName }}</p>
              <small class="text-muted"
                >{{ fileSize }} - Prêt pour l'importation</small
              >
              <button
                @click.stop="clearFile"
                class="btn btn-sm btn-outline-danger mt-2"
              >
                <i class="fas fa-times me-1"></i>Retirer le fichier
              </button>
            </div>
          </div>

          <div v-if="hasFileError" class="alert alert-danger mt-2">
            {{ fileError }}
          </div>
        </div>

        <!-- Actions -->
        <div class="d-flex gap-2 mb-3">
          <button
            @click="previewImport"
            :disabled="!jsonData || isImporting || isPreviewing"
            class="btn btn-outline-primary"
          >
            <i class="fas fa-eye me-1"></i>
            <span v-if="isPreviewing">Prévisualisation...</span>
            <span v-else>Prévisualiser</span>
          </button>

          <button
            @click="startImport"
            :disabled="!jsonData || !selectedBankrollId || isImporting"
            class="btn btn-primary"
          >
            <i class="fas fa-upload me-1"></i>
            <span v-if="isImporting">Importation en cours...</span>
            <span v-else>Importer</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Prévisualisation -->
    <div v-if="previewData" class="card mb-4">
      <div class="card-header">
        <h6 class="card-title mb-0">
          <i class="fas fa-eye me-2"></i>
          Prévisualisation ({{ previewData.preview_count }} sur
          {{ previewData.total_bets }} paris)
        </h6>
      </div>

      <div class="card-body">
        <div
          v-if="previewData.total_bets > previewData.preview_count"
          class="alert alert-info"
        >
          <i class="fas fa-info-circle me-2"></i>
          Seuls les {{ previewData.preview_count }} premiers paris sont
          affichés. Au total, {{ previewData.total_bets }} paris seront
          importés.
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>Index</th>
                <th>Date</th>
                <th>Sport</th>
                <th>Mise</th>
                <th>Cote</th>
                <th>Statut</th>
                <th>Gain pot.</th>
                <th>Erreurs</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in previewData.preview"
                :key="item.index"
                :class="{ 'table-danger': !item.is_valid }"
              >
                <td>{{ item.index + 1 }}</td>
                <td>
                  <span v-if="item.processed_data.date">
                    {{ formatDate(item.processed_data.date) }}
                  </span>
                  <span v-else class="text-muted">-</span>
                </td>
                <td>{{ item.processed_data.sport || "-" }}</td>
                <td>
                  <span v-if="item.processed_data.stake">
                    {{ formatCurrency(item.processed_data.stake) }}
                  </span>
                  <span v-else class="text-muted">-</span>
                </td>
                <td>
                  <span v-if="item.processed_data.odds">
                    {{ item.processed_data.odds }}
                  </span>
                  <span v-else class="text-muted">-</span>
                </td>
                <td>
                  <span
                    v-if="item.processed_data.result"
                    class="badge"
                    :class="getResultBadgeClass(item.processed_data.result)"
                  >
                    {{ getResultLabel(item.processed_data.result) }}
                  </span>
                </td>
                <td>
                  <span v-if="item.processed_data.potential_win">
                    {{ formatCurrency(item.processed_data.potential_win) }}
                  </span>
                  <span v-else class="text-muted">-</span>
                </td>
                <td>
                  <span v-if="item.errors.length > 0" class="text-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    {{ item.errors.length }}
                  </span>
                  <span v-else class="text-success">
                    <i class="fas fa-check"></i>
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Résultats d'importation -->
    <div v-if="importResult" class="card">
      <div class="card-header">
        <h6 class="card-title mb-0">
          <i class="fas fa-chart-bar me-2"></i>
          Résultats de l'importation
        </h6>
      </div>

      <div class="card-body">
        <div class="row">
          <div class="col-md-3">
            <div class="text-center">
              <div class="h3 text-primary">{{ importResult.total }}</div>
              <small class="text-muted">Total traités</small>
            </div>
          </div>

          <div class="col-md-3">
            <div class="text-center">
              <div class="h3 text-success">{{ importResult.success }}</div>
              <small class="text-muted">Importés</small>
            </div>
          </div>

          <div class="col-md-3">
            <div class="text-center">
              <div class="h3 text-warning">{{ importResult.skipped }}</div>
              <small class="text-muted">Ignorés</small>
            </div>
          </div>

          <div class="col-md-3">
            <div class="text-center">
              <div class="h3 text-danger">{{ importResult.errors }}</div>
              <small class="text-muted">Erreurs</small>
            </div>
          </div>
        </div>

        <div
          v-if="
            importResult.error_details && importResult.error_details.length > 0
          "
          class="mt-3"
        >
          <h6>Détails des erreurs:</h6>
          <ul class="list-unstyled">
            <li
              v-for="error in importResult.error_details"
              :key="error"
              class="text-danger"
            >
              <i class="fas fa-exclamation-circle me-1"></i>
              {{ error }}
            </li>
          </ul>
        </div>

        <div class="mt-3">
          <button @click="resetImport" class="btn btn-outline-primary">
            <i class="fas fa-refresh me-1"></i>
            Nouvelle importation
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from "vue";
import { useBankrolls } from "@/composables/useBankrolls";
import { useToast } from "@/composables/useToast";
import { api } from "@/services/api";

export default {
  name: "BetImport",
  setup() {
    // Composables
    const { bankrolls, fetchBankrolls } = useBankrolls();
    const { showToast } = useToast();

    // State
    const jsonData = ref(null);
    const fileName = ref("");
    const fileSize = ref("");
    const selectedBankrollId = ref("");
    const isDragOver = ref(false);
    const hasFileError = ref(false);
    const fileError = ref("");
    const isImporting = ref(false);
    const isPreviewing = ref(false);
    const previewData = ref(null);
    const importResult = ref(null);

    // Méthodes
    const handleFileDrop = (event) => {
      isDragOver.value = false;
      const files = event.dataTransfer.files;
      if (files.length > 0) {
        processFile(files[0]);
      }
    };

    const handleFileSelect = (event) => {
      const file = event.target.files[0];
      if (file) {
        processFile(file);
      }
    };

    const processFile = (file) => {
      // Réinitialiser l'état
      hasFileError.value = false;
      fileError.value = "";
      previewData.value = null;
      importResult.value = null;

      // Vérifier le type de fichier
      if (!file.name.toLowerCase().endsWith(".json")) {
        hasFileError.value = true;
        fileError.value = "Veuillez sélectionner un fichier JSON valide.";
        return;
      }

      // Vérifier la taille (limite à 10MB)
      const maxSize = 10 * 1024 * 1024; // 10MB
      if (file.size > maxSize) {
        hasFileError.value = true;
        fileError.value = "Le fichier est trop volumineux (limite: 10MB).";
        return;
      }

      fileName.value = file.name;
      fileSize.value = formatFileSize(file.size);

      // Lire le fichier
      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const data = JSON.parse(e.target.result);
          if (!Array.isArray(data)) {
            throw new Error(
              "Le fichier JSON doit contenir un tableau de paris."
            );
          }
          jsonData.value = JSON.stringify(data);
          showToast("Fichier JSON chargé avec succès", "success");
        } catch (error) {
          hasFileError.value = true;
          fileError.value = `Erreur de lecture du JSON: ${error.message}`;
          jsonData.value = null;
        }
      };
      reader.readAsText(file);
    };

    const clearFile = () => {
      jsonData.value = null;
      fileName.value = "";
      fileSize.value = "";
      hasFileError.value = false;
      fileError.value = "";
      previewData.value = null;
      importResult.value = null;
    };

    const previewImport = async () => {
      if (!jsonData.value) return;

      isPreviewing.value = true;
      try {
        const response = await api.post("/bets/import/preview", {
          json_data: jsonData.value,
          limit: 20, // Limiter la prévisualisation
        });

        if (response.data.success) {
          previewData.value = response.data.data;
          showToast("Prévisualisation générée", "success");
        } else {
          showToast("Erreur lors de la prévisualisation", "error");
        }
      } catch (error) {
        console.error("Erreur prévisualisation:", error);
        showToast("Erreur lors de la prévisualisation", "error");
      } finally {
        isPreviewing.value = false;
      }
    };

    const startImport = async () => {
      if (!jsonData.value || !selectedBankrollId.value) return;

      isImporting.value = true;
      try {
        const response = await api.post("/bets/import/json", {
          json_data: jsonData.value,
          bankroll_id: selectedBankrollId.value,
        });

        if (response.data.success) {
          importResult.value = response.data.stats;
          showToast("Importation terminée", "success");

          // Émettre un événement pour actualiser les données
          emitImportComplete();
        } else {
          showToast(
            response.data.error || "Erreur lors de l'importation",
            "error"
          );
        }
      } catch (error) {
        console.error("Erreur importation:", error);
        showToast("Erreur lors de l'importation", "error");
      } finally {
        isImporting.value = false;
      }
    };

    const resetImport = () => {
      clearFile();
      selectedBankrollId.value = "";
    };

    const emitImportComplete = () => {
      // Émettre un événement global pour actualiser les données
      window.dispatchEvent(new CustomEvent("bets-imported"));
    };

    // Utilitaires
    const formatFileSize = (bytes) => {
      if (bytes === 0) return "0 Bytes";
      const k = 1024;
      const sizes = ["Bytes", "KB", "MB", "GB"];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
    };

    const formatCurrency = (amount) => {
      return new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency: "EUR",
      }).format(amount);
    };

    const formatDate = (dateString) => {
      return new Date(dateString).toLocaleDateString("fr-FR");
    };

    const getResultBadgeClass = (result) => {
      const classes = {
        win: "bg-success",
        lost: "bg-danger",
        void: "bg-secondary",
        pending: "bg-warning",
      };
      return classes[result] || "bg-info";
    };

    const getResultLabel = (result) => {
      const labels = {
        win: "Gagné",
        lost: "Perdu",
        void: "Annulé",
        pending: "En cours",
      };
      return labels[result] || result;
    };

    // Lifecycle
    onMounted(() => {
      fetchBankrolls();
    });

    return {
      // État
      jsonData,
      fileName,
      fileSize,
      selectedBankrollId,
      isDragOver,
      hasFileError,
      fileError,
      isImporting,
      isPreviewing,
      previewData,
      importResult,
      bankrolls,

      // Méthodes
      handleFileDrop,
      handleFileSelect,
      clearFile,
      previewImport,
      startImport,
      resetImport,
      formatCurrency,
      formatDate,
      getResultBadgeClass,
      getResultLabel,
    };
  },
};
</script>

<style scoped>
.bet-import-container {
  max-width: 1200px;
  margin: 0 auto;
}

.file-drop-zone {
  border: 2px dashed #dee2e6;
  border-radius: 0.375rem;
  background-color: #f8f9fa;
  transition: all 0.3s ease;
  cursor: pointer;
}

.file-drop-zone.dragover {
  border-color: #0d6efd;
  background-color: #e7f3ff;
}

.file-drop-zone.has-file {
  border-color: #198754;
  background-color: #f0f9f0;
}

.file-drop-zone.has-error {
  border-color: #dc3545;
  background-color: #fdf2f2;
}

.file-drop-zone:hover {
  border-color: #adb5bd;
}

.table-responsive {
  max-height: 400px;
  overflow-y: auto;
}

.table td {
  vertical-align: middle;
}

@media (max-width: 768px) {
  .table-responsive {
    font-size: 0.875rem;
  }
}
</style>
