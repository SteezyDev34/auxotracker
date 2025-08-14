const API_BASE_URL = import.meta.env.VITE_API_URL || 'https://api.auxotracker.lan/api';

export const BankrollService = {
    // Récupérer toutes les bankrolls de l'utilisateur
    async getBankrolls() {
        try {
            const response = await fetch(`${API_BASE_URL}/bankrolls`, {
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
            return data.bankrolls;
        } catch (error) {
            console.error('Erreur lors de la récupération des bankrolls:', error);
            throw error;
        }
    },

    // Récupérer une bankroll spécifique
    async getBankroll(bankrollId) {
        try {
            const response = await fetch(`${API_BASE_URL}/bankrolls/${bankrollId}`, {
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
            return data.bankroll;
        } catch (error) {
            console.error(`Erreur lors de la récupération de la bankroll ${bankrollId}:`, error);
            throw error;
        }
    },

    // Créer une nouvelle bankroll
    async createBankroll(bankrollData) {
        try {
            const response = await fetch(`${API_BASE_URL}/bankrolls`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                },
                body: JSON.stringify(bankrollData)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la création de la bankroll:', error);
            throw error;
        }
    },

    // Mettre à jour une bankroll existante
    async updateBankroll(bankrollId, bankrollData) {
        try {
            const response = await fetch(`${API_BASE_URL}/bankrolls/${bankrollId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                },
                body: JSON.stringify(bankrollData)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error(`Erreur lors de la mise à jour de la bankroll ${bankrollId}:`, error);
            throw error;
        }
    },

    // Supprimer une bankroll
    async deleteBankroll(bankrollId) {
        try {
            const response = await fetch(`${API_BASE_URL}/bankrolls/${bankrollId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                }
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error(`Erreur lors de la suppression de la bankroll ${bankrollId}:`, error);
            throw error;
        }
    },

    // Récupérer les bookmakers associés à une bankroll
    async getBankrollBookmakers(bankrollId) {
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
            // Filtrer les bookmakers associés à la bankroll spécifiée
            return data.user_bookmakers.filter(bookmaker => bookmaker.users_bankrolls_id === bankrollId);
        } catch (error) {
            console.error(`Erreur lors de la récupération des bookmakers pour la bankroll ${bankrollId}:`, error);
            throw error;
        }
    }
};