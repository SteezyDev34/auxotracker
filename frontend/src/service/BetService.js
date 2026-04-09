const API_BASE_URL = import.meta.env.VITE_API_URL;
if (!API_BASE_URL) {
  throw new Error("VITE_API_URL must be set in environment (no fallback allowed).");
}

export const BetService = {
  // Récupérer tous les paris
  async getBets(params = {}) {
    try {
      const queryParams = new URLSearchParams();

      // Ajouter les paramètres de filtrage
      if (params.period) queryParams.append("period", params.period);
      if (params.sports && params.sports.length > 0) {
        params.sports.forEach((sport) => queryParams.append("sports[]", sport));
      }
      if (params.betTypes && params.betTypes.length > 0) {
        params.betTypes.forEach((type) =>
          queryParams.append("bet_types[]", type)
        );
      }
      if (params.bookmakers && params.bookmakers.length > 0) {
        params.bookmakers.forEach((bookmaker) =>
          queryParams.append("bookmakers[]", bookmaker)
        );
      }
      if (params.tipsters && params.tipsters.length > 0) {
        params.tipsters.forEach((tipster) =>
          queryParams.append("tipsters[]", tipster)
        );
      }
      if (params.bankrolls && params.bankrolls.length > 0) {
        params.bankrolls.forEach((id) => queryParams.append("bankrolls[]", id));
      }
      if (params.startDate) queryParams.append("start_date", params.startDate);
      if (params.endDate) queryParams.append("end_date", params.endDate);

      const response = await fetch(`${API_BASE_URL}/bets?${queryParams}`, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          Authorization: `Bearer ${localStorage.getItem("token")}`,
        },
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error("Erreur lors de la récupération des paris:", error);
      throw error;
    }
  },

  // Récupérer les statistiques des paris
  async getBetStats(params = {}) {
    try {
      console.log("🚀 BETSERVICE - Début getBetStats avec filtres:", params);

      const queryParams = new URLSearchParams();

      if (params.period) queryParams.append("period", params.period);
      if (params.sports && params.sports.length > 0) {
        params.sports.forEach((sport) => queryParams.append("sports[]", sport));
      }
      if (params.betTypes && params.betTypes.length > 0) {
        params.betTypes.forEach((type) =>
          queryParams.append("bet_types[]", type)
        );
      }
      if (params.bookmakers && params.bookmakers.length > 0) {
        params.bookmakers.forEach((bookmaker) =>
          queryParams.append("bookmakers[]", bookmaker)
        );
      }
      if (params.tipsters && params.tipsters.length > 0) {
        params.tipsters.forEach((tipster) =>
          queryParams.append("tipsters[]", tipster)
        );
      }

      const url = `${API_BASE_URL}/bets/stats?${queryParams}`;
      console.log("🌐 BETSERVICE - URL construite:", url);
      console.log("🌐 BETSERVICE - API_BASE_URL:", API_BASE_URL);

      const response = await fetch(url, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          Authorization: `Bearer ${localStorage.getItem("token")}`,
        },
      });

      console.log("📡 BETSERVICE - Statut réponse:", response.status);
      console.log(
        "📡 BETSERVICE - Headers réponse:",
        Object.fromEntries(response.headers.entries())
      );

      if (!response.ok) {
        console.error(
          "❌ BETSERVICE - Erreur HTTP:",
          response.status,
          response.statusText
        );
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      console.log("📦 BETSERVICE - JSON reçu (BRUT):", data);
      console.log("📦 BETSERVICE - Type de data:", typeof data);
      console.log("📦 BETSERVICE - data.success:", data.success);
      console.log("📦 BETSERVICE - data.data:", data.data);

      return data;
    } catch (error) {
      console.error("💥 BETSERVICE - Erreur dans getBetStats:", error);
      throw error;
    }
  },

  // Récupérer les statistiques détaillées des paris
  async getDetailedStats(params = {}) {
    try {
      const queryParams = new URLSearchParams();

      if (params.period) queryParams.append("period", params.period);
      if (params.sports && params.sports.length > 0) {
        params.sports.forEach((sport) => queryParams.append("sports[]", sport));
      }
      if (params.betTypes && params.betTypes.length > 0) {
        params.betTypes.forEach((type) =>
          queryParams.append("bet_types[]", type)
        );
      }
      if (params.bookmakers && params.bookmakers.length > 0) {
        params.bookmakers.forEach((bookmaker) =>
          queryParams.append("bookmakers[]", bookmaker)
        );
      }
      if (params.tipsters && params.tipsters.length > 0) {
        params.tipsters.forEach((tipster) =>
          queryParams.append("tipsters[]", tipster)
        );
      }

      const response = await fetch(
        `${API_BASE_URL}/bets/detailed-stats?${queryParams}`,
        {
          method: "GET",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
        }
      );

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error(
        "Erreur lors de la récupération des statistiques détaillées:",
        error
      );
      throw error;
    }
  },

  // Récupérer les statistiques des transactions
  async getTransactionStats(params = {}) {
    try {
      const queryParams = new URLSearchParams();

      if (params.period) queryParams.append("period", params.period);

      const response = await fetch(
        `${API_BASE_URL}/transactions/stats?${queryParams}`,
        {
          method: "GET",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
        }
      );

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error(
        "Erreur lors de la récupération des statistiques de transactions:",
        error
      );
      throw error;
    }
  },

  // Récupérer l'évolution du capital
  async getCapitalEvolution(params = {}) {
    try {
      const queryParams = new URLSearchParams();

      if (params.period) queryParams.append("period", params.period);
      if (params.sports && params.sports.length > 0) {
        params.sports.forEach((sport) => queryParams.append("sports[]", sport));
      }
      if (params.betTypes && params.betTypes.length > 0) {
        params.betTypes.forEach((type) =>
          queryParams.append("bet_types[]", type)
        );
      }
      if (params.bookmakers && params.bookmakers.length > 0) {
        params.bookmakers.forEach((bookmaker) =>
          queryParams.append("bookmakers[]", bookmaker)
        );
      }
      if (params.tipsters && params.tipsters.length > 0) {
        params.tipsters.forEach((tipster) =>
          queryParams.append("tipsters[]", tipster)
        );
      }
      if (params.bankrolls && params.bankrolls.length > 0) {
        params.bankrolls.forEach((id) => 
          queryParams.append("bankrolls[]", id));
      }

      const response = await fetch(
        `${API_BASE_URL}/bets/capital-evolution?${queryParams}`,
        {
          method: "GET",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            Authorization: `Bearer ${localStorage.getItem("token")}`,
          },
        }
      );

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error(
        "Erreur lors de la récupération de l'évolution du capital:",
        error
      );
      throw error;
    }
  },

  // Récupérer les options de filtres
  async getFilterOptions() {
    try {
      const response = await fetch(`${API_BASE_URL}/bets/filter-options`, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          Authorization: `Bearer ${localStorage.getItem("token")}`,
        },
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error(
        "Erreur lors de la récupération des options de filtres:",
        error
      );
      throw error;
    }
  },

  // Récupérer un pari spécifique avec ses événements
  async getBet(betId) {
    try {
      const response = await fetch(`${API_BASE_URL}/bets/${betId}`, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          Authorization: `Bearer ${localStorage.getItem("token")}`,
        },
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error("Erreur lors de la récupération du pari:", error);
      throw error;
    }
  },

  // Créer un nouveau pari
  async createBet(betData) {
    try {
      const response = await fetch(`${API_BASE_URL}/bets`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          Authorization: `Bearer ${localStorage.getItem("token")}`,
        },
        body: JSON.stringify(betData),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error("Erreur lors de la création du pari:", error);
      throw error;
    }
  },

  // Mettre à jour un pari
  async updateBet(betId, betData) {
    try {
      const response = await fetch(`${API_BASE_URL}/bets/${betId}`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          Authorization: `Bearer ${localStorage.getItem("token")}`,
        },
        body: JSON.stringify(betData),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error("Erreur lors de la mise à jour du pari:", error);
      throw error;
    }
  },

  // Supprimer un pari
  async deleteBet(betId) {
    try {
      const response = await fetch(`${API_BASE_URL}/bets/${betId}`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          Authorization: `Bearer ${localStorage.getItem("token")}`,
        },
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error("Erreur lors de la suppression du pari:", error);
      throw error;
    }
  },
};
