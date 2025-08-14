const API_BASE_URL = import.meta.env.VITE_API_URL || 'https://api.auxotracker.lan/api';

export const BookmakerService = {
    // Récupérer tous les bookmakers
    async getBookmakers() {
        try {
            const response = await fetch(`${API_BASE_URL}/bookmakers`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            return data.bookmakers;
        } catch (error) {
            console.error('Erreur lors de la récupération des bookmakers:', error);
            throw error;
        }
    },

    // Récupérer les bookmakers de l'utilisateur
    async getUserBookmakers() {
        try {
            const response = await fetch(`${API_BASE_URL}/user-bookmakers`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            return data.user_bookmakers;
        } catch (error) {
            console.error('Erreur lors de la récupération des bookmakers utilisateur:', error);
            throw error;
        }
    },

    // Créer un nouveau bookmaker utilisateur
    async createUserBookmaker(bookmakerData) {
        try {
            const response = await fetch(`${API_BASE_URL}/user-bookmakers`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                },
                body: JSON.stringify(bookmakerData)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la création du bookmaker utilisateur:', error);
            throw error;
        }
    },

    // Mettre à jour un bookmaker utilisateur
    async updateUserBookmaker(bookmarkerId, bookmakerData) {
        try {
            const response = await fetch(`${API_BASE_URL}/user-bookmakers/${bookmarkerId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                },
                body: JSON.stringify(bookmakerData)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la mise à jour du bookmaker utilisateur:', error);
            throw error;
        }
    },

    // Supprimer un bookmaker utilisateur
    async deleteUserBookmaker(bookmarkerId) {
        try {
            const response = await fetch(`${API_BASE_URL}/user-bookmakers/${bookmarkerId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la suppression du bookmaker utilisateur:', error);
            throw error;
        }
    }
};