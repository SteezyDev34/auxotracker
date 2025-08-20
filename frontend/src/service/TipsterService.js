import axios from 'axios';

// Configuration de l'URL de base de l'API
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8001';

// Configuration des en-têtes par défaut
const getAuthHeaders = () => {
    const token = localStorage.getItem('token');
    return {
        'Content-Type': 'application/json',
        'Authorization': token ? `Bearer ${token}` : ''
    };
};

export default {
    /**
     * Récupère la liste des tipsters de l'utilisateur connecté
     * @returns {Promise} Liste des tipsters
     */
    async getTipsters() {
        try {
            const response = await axios.get(`${API_BASE_URL}/api/tipsters`, {
                headers: getAuthHeaders()
            });
            return response.data.tipsters;
        } catch (error) {
            console.error('Erreur lors de la récupération des tipsters:', error);
            throw error;
        }
    },

    /**
     * Récupère un tipster spécifique par son ID
     * @param {number} id - ID du tipster
     * @returns {Promise} Données du tipster
     */
    async getTipster(id) {
        try {
            const response = await axios.get(`${API_BASE_URL}/api/tipsters/${id}`, {
                headers: getAuthHeaders()
            });
            return response.data.tipster;
        } catch (error) {
            console.error('Erreur lors de la récupération du tipster:', error);
            throw error;
        }
    },

    /**
     * Crée un nouveau tipster
     * @param {Object} tipsterData - Données du tipster à créer
     * @returns {Promise} Tipster créé
     */
    async createTipster(tipsterData) {
        try {
            const response = await axios.post(`${API_BASE_URL}/api/tipsters`, tipsterData, {
                headers: getAuthHeaders()
            });
            return response.data;
        } catch (error) {
            console.error('Erreur lors de la création du tipster:', error);
            throw error;
        }
    },

    /**
     * Met à jour un tipster existant
     * @param {number} id - ID du tipster
     * @param {Object} tipsterData - Nouvelles données du tipster
     * @returns {Promise} Tipster mis à jour
     */
    async updateTipster(id, tipsterData) {
        try {
            const response = await axios.put(`${API_BASE_URL}/api/tipsters/${id}`, tipsterData, {
                headers: getAuthHeaders()
            });
            return response.data;
        } catch (error) {
            console.error('Erreur lors de la mise à jour du tipster:', error);
            throw error;
        }
    },

    /**
     * Supprime un tipster
     * @param {number} id - ID du tipster à supprimer
     * @returns {Promise} Confirmation de suppression
     */
    async deleteTipster(id) {
        try {
            const response = await axios.delete(`${API_BASE_URL}/api/tipsters/${id}`, {
                headers: getAuthHeaders()
            });
            return response.data;
        } catch (error) {
            console.error('Erreur lors de la suppression du tipster:', error);
            throw error;
        }
    }
};