import ApiService from "./ApiService.js";

export const InvestmentService = {
  /**
   * Récupérer tous les investissements de l'utilisateur connecté
   */
  async getInvestments(params = {}) {
    try {
      return await ApiService.get("/investments", { params });
    } catch (error) {
      console.error(
        "Erreur lors de la récupération des investissements:",
        error
      );
      throw error;
    }
  },

  /**
   * Récupérer les statistiques des investissements
   */
  async getInvestmentStats(params = {}) {
    try {
      return await ApiService.get("/investments/stats", { params });
    } catch (error) {
      console.error(
        "Erreur lors de la récupération des statistiques d'investissements:",
        error
      );
      throw error;
    }
  },

  /**
   * Créer un nouvel investissement
   */
  async createInvestment(data) {
    try {
      return await ApiService.post("/investments", data);
    } catch (error) {
      console.error("Erreur lors de la création de l'investissement:", error);
      throw error;
    }
  },

  /**
   * Récupérer un investissement spécifique
   */
  async getInvestment(id) {
    try {
      return await ApiService.get(`/investments/${id}`);
    } catch (error) {
      console.error(
        "Erreur lors de la récupération de l'investissement:",
        error
      );
      throw error;
    }
  },

  /**
   * Mettre à jour un investissement
   */
  async updateInvestment(id, data) {
    try {
      return await ApiService.put(`/investments/${id}`, data);
    } catch (error) {
      console.error(
        "Erreur lors de la mise à jour de l'investissement:",
        error
      );
      throw error;
    }
  },

  /**
   * Supprimer un investissement
   */
  async deleteInvestment(id) {
    try {
      return await ApiService.delete(`/investments/${id}`);
    } catch (error) {
      console.error(
        "Erreur lors de la suppression de l'investissement:",
        error
      );
      throw error;
    }
  },
};
