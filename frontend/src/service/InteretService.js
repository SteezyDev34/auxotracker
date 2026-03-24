import ApiService from "./ApiService.js";

export const InteretService = {
  // Récupérer tous les intérêts
  async getInterets(params = {}) {
    try {
      return await ApiService.get("/interets", { params });
    } catch (error) {
      console.error("Erreur lors de la récupération des intérêts:", error);
      throw error;
    }
  },

  // Récupérer les statistiques des intérêts
  async getInteretStats(params = {}) {
    try {
      return await ApiService.get("/interets/stats", { params });
    } catch (error) {
      console.error(
        "Erreur lors de la récupération des statistiques d'intérêts:",
        error
      );
      throw error;
    }
  },

  // Récupérer l'évolution des intérêts
  async getInteretEvolution(params = {}) {
    try {
      return await ApiService.get("/interets/evolution", { params });
    } catch (error) {
      console.error(
        "Erreur lors de la récupération de l'évolution des intérêts:",
        error
      );
      throw error;
    }
  },

  // Récupérer les options de filtres
  async getFilterOptions() {
    try {
      return await ApiService.get("/interets/filter-options");
    } catch (error) {
      console.error(
        "Erreur lors de la récupération des options de filtres:",
        error
      );
      throw error;
    }
  },

  // Récupérer un intérêt spécifique
  async getInteret(id) {
    try {
      return await ApiService.get(`/interets/${id}`);
    } catch (error) {
      console.error("Erreur lors de la récupération de l'intérêt:", error);
      throw error;
    }
  },

  // Créer un nouvel intérêt (admin uniquement)
  async createInteret(interetData) {
    try {
      return await ApiService.post("/interets", interetData);
    } catch (error) {
      console.error("Erreur lors de la création de l'intérêt:", error);
      throw error;
    }
  },

  // Mettre à jour un intérêt (admin uniquement)
  async updateInteret(id, interetData) {
    try {
      return await ApiService.put(`/interets/${id}`, interetData);
    } catch (error) {
      console.error("Erreur lors de la mise à jour de l'intérêt:", error);
      throw error;
    }
  },

  // Supprimer un intérêt (admin uniquement)
  async deleteInteret(id) {
    try {
      return await ApiService.delete(`/interets/${id}`);
    } catch (error) {
      console.error("Erreur lors de la suppression de l'intérêt:", error);
      throw error;
    }
  },

  // Exporter les intérêts en CSV
  async exportToCSV(params = {}) {
    try {
      return await ApiService.downloadFile(
        "/interets/export/csv",
        params,
        `mes_interets_${new Date().toISOString().split("T")[0]}.csv`
      );
    } catch (error) {
      console.error("Erreur lors de l'export CSV:", error);
      throw error;
    }
  },
};
