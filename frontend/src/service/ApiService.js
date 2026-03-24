/**
 * Service API centralisé pour gérer toutes les requêtes HTTP
 */

const API_BASE_URL =
  import.meta.env.VITE_API_BASE_URL || "https://api.auxotracker.lan";
const API_URL = `${API_BASE_URL}/api`;

class ApiService {
  /**
   * Récupérer le token d'authentification
   */
  static getAuthToken() {
    return localStorage.getItem("token");
  }

  /**
   * Vérifier si l'utilisateur est authentifié
   */
  static isAuthenticated() {
    const token = this.getAuthToken();
    return !!token;
  }

  /**
   * Obtenir les headers par défaut
   */
  static getDefaultHeaders() {
    const headers = {
      "Content-Type": "application/json",
      Accept: "application/json",
    };

    const token = this.getAuthToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }

    return headers;
  }

  /**
   * Gérer les erreurs de réponse
   */
  static async handleResponse(response) {
    if (!response.ok) {
      // Si erreur 401, c'est un problème d'authentification
      if (response.status === 401) {
        // Supprimer le token invalide
        localStorage.removeItem("token");
        // Rediriger vers la page de connexion
        window.location.href = "/auth/login";
        throw new Error("Session expirée. Veuillez vous reconnecter.");
      }

      // Essayer de récupérer le message d'erreur
      let errorMessage = `HTTP error! status: ${response.status}`;
      try {
        const errorData = await response.json();
        if (errorData.message) {
          errorMessage = errorData.message;
        } else if (errorData.error) {
          errorMessage = errorData.error;
        }
      } catch (e) {
        // Ignorer les erreurs de parsing JSON
      }

      throw new Error(errorMessage);
    }

    return await response.json();
  }

  /**
   * Effectuer une requête GET
   */
  static async get(endpoint, options = {}) {
    const { params, ...fetchOptions } = options;

    let url = `${API_URL}${endpoint}`;

    // Ajouter les paramètres de requête
    if (params) {
      const queryParams = new URLSearchParams();
      Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== "") {
          queryParams.append(key, value);
        }
      });

      if (queryParams.toString()) {
        url += `?${queryParams.toString()}`;
      }
    }

    const response = await fetch(url, {
      method: "GET",
      headers: this.getDefaultHeaders(),
      ...fetchOptions,
    });

    return this.handleResponse(response);
  }

  /**
   * Effectuer une requête POST
   */
  static async post(endpoint, data = null, options = {}) {
    const response = await fetch(`${API_URL}${endpoint}`, {
      method: "POST",
      headers: this.getDefaultHeaders(),
      body: data ? JSON.stringify(data) : null,
      ...options,
    });

    return this.handleResponse(response);
  }

  /**
   * Effectuer une requête PUT
   */
  static async put(endpoint, data = null, options = {}) {
    const response = await fetch(`${API_URL}${endpoint}`, {
      method: "PUT",
      headers: this.getDefaultHeaders(),
      body: data ? JSON.stringify(data) : null,
      ...options,
    });

    return this.handleResponse(response);
  }

  /**
   * Effectuer une requête DELETE
   */
  static async delete(endpoint, options = {}) {
    const response = await fetch(`${API_URL}${endpoint}`, {
      method: "DELETE",
      headers: this.getDefaultHeaders(),
      ...options,
    });

    return this.handleResponse(response);
  }

  /**
   * Effectuer une requête PATCH
   */
  static async patch(endpoint, data = null, options = {}) {
    const response = await fetch(`${API_URL}${endpoint}`, {
      method: "PATCH",
      headers: this.getDefaultHeaders(),
      body: data ? JSON.stringify(data) : null,
      ...options,
    });

    return this.handleResponse(response);
  }

  /**
   * Télécharger un fichier
   */
  static async downloadFile(endpoint, params = {}, filename = null) {
    let url = `${API_URL}${endpoint}`;

    if (params && Object.keys(params).length > 0) {
      const queryParams = new URLSearchParams();
      Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== "") {
          queryParams.append(key, value);
        }
      });

      if (queryParams.toString()) {
        url += `?${queryParams.toString()}`;
      }
    }

    const response = await fetch(url, {
      method: "GET",
      headers: {
        Authorization: this.getAuthToken()
          ? `Bearer ${this.getAuthToken()}`
          : "",
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const blob = await response.blob();
    const downloadUrl = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = downloadUrl;
    a.download =
      filename || `download_${new Date().toISOString().split("T")[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(downloadUrl);

    return { success: true };
  }
}

export default ApiService;
