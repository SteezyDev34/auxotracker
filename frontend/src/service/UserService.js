/**
 * Service pour gérer les opérations liées aux utilisateurs
 */
const API_BASE_URL = `${import.meta.env.VITE_API_BASE_URL}/api`;

export const UserService = {
    /**
     * Récupère les informations de l'utilisateur connecté
     * @returns {Promise<Object>} Les données de l'utilisateur
     */
    async getUserInfo() {
        try {
            const token = localStorage.getItem('token');
            if (!token) {
                throw new Error('Aucun token d\'authentification trouvé');
            }
            
            const response = await fetch(`${API_BASE_URL}/user`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la récupération des informations utilisateur:', error);
            throw error;
        }
    },

    /**
     * Met à jour les paramètres de l'utilisateur
     * @param {Object} settingsData - Les paramètres à mettre à jour
     * @returns {Promise<Object>} Le résultat de la mise à jour
     */
    async updateUserSettings(settingsData) {
        try {
            const token = localStorage.getItem('token');
            if (!token) {
                throw new Error('Aucun token d\'authentification trouvé');
            }
            
            const response = await fetch(`${API_BASE_URL}/user/settings`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(settingsData)
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la mise à jour des paramètres:', error);
            throw error;
        }
    },

    /**
     * Met à jour l'avatar de l'utilisateur
     * @param {File} avatarFile - Le fichier d'avatar à télécharger
     * @returns {Promise<Object>} Le résultat de la mise à jour
     */
    async updateAvatar(avatarFile) {
        try {
            const token = localStorage.getItem('token');
            if (!token) {
                throw new Error('Aucun token d\'authentification trouvé');
            }
            
            const formData = new FormData();
            formData.append('avatar', avatarFile);
            
            const response = await fetch(`${API_BASE_URL}/user/avatar`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`
                },
                body: formData
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la mise à jour de l\'avatar:', error);
            throw error;
        }
    },

    /**
     * Supprime l'avatar de l'utilisateur
     * @returns {Promise<Object>} Le résultat de la suppression
     */
    async deleteAvatar() {
        try {
            const token = localStorage.getItem('token');
            if (!token) {
                throw new Error('Aucun token d\'authentification trouvé');
            }
            
            const response = await fetch(`${API_BASE_URL}/user/avatar`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la suppression de l\'avatar:', error);
            throw error;
        }
    },

    /**
     * Récupérer les préférences sportives de l'utilisateur
     * @returns {Promise<Object>} Les préférences sportives
     */
    async getUserSportsPreferences() {
        try {
            const token = localStorage.getItem('token');
            if (!token) {
                throw new Error('Aucun token d\'authentification trouvé');
            }
            
            const response = await fetch(`${API_BASE_URL}/user/sports-preferences`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Erreur lors de la récupération des préférences sportives');
            }
            
            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la récupération des préférences sportives:', error);
            throw error;
        }
    },

    /**
     * Mettre à jour les préférences sportives de l'utilisateur
     * @param {Array} sportsPreferences - Les préférences sportives
     * @returns {Promise<Object>} Le résultat de la mise à jour
     */
    async updateUserSportsPreferences(sportsPreferences) {
        try {
            const token = localStorage.getItem('token');
            if (!token) {
                throw new Error('Aucun token d\'authentification trouvé');
            }
            
            const response = await fetch(`${API_BASE_URL}/user/sports-preferences`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ sports_preferences: sportsPreferences })
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Erreur lors de la mise à jour des préférences sportives');
            }
            
            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la mise à jour des préférences sportives:', error);
            throw error;
        }
    },

    /**
     * Récupérer uniquement les sports favoris de l'utilisateur
     * @returns {Promise<Object>} Les sports favoris
     */
    async getUserFavoriteSports() {
        try {
            const token = localStorage.getItem('token');
            if (!token) {
                throw new Error('Aucun token d\'authentification trouvé');
            }
            
            const response = await fetch(`${API_BASE_URL}/user/sports-preferences/favorites`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Erreur lors de la récupération des sports favoris');
            }
            
            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la récupération des sports favoris:', error);
            throw error;
        }
    }
};