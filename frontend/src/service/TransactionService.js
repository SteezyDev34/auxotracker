const API_BASE_URL = import.meta.env.VITE_API_URL || 'https://api.auxotracker.lan/api';

export const TransactionService = {
    // Récupérer les statistiques des transactions
    async getTransactionStats(params = {}) {
        try {
            const queryParams = new URLSearchParams();
            
            if (params.period) queryParams.append('period', params.period);

            const response = await fetch(`${API_BASE_URL}/transactions/stats?${queryParams}`, {
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

            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la récupération des statistiques de transactions:', error);
            throw error;
        }
    },

    // Récupérer toutes les transactions
    async getTransactions(params = {}) {
        try {
            const queryParams = new URLSearchParams();
            
            if (params.period) queryParams.append('period', params.period);
            if (params.type) queryParams.append('type', params.type);
            if (params.startDate) queryParams.append('start_date', params.startDate);
            if (params.endDate) queryParams.append('end_date', params.endDate);

            const response = await fetch(`${API_BASE_URL}/transactions?${queryParams}`, {
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

            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la récupération des transactions:', error);
            throw error;
        }
    },

    // Créer une nouvelle transaction
    async createTransaction(transactionData) {
        try {
            const response = await fetch(`${API_BASE_URL}/transactions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                },
                body: JSON.stringify(transactionData)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Erreur lors de la création de la transaction:', error);
            throw error;
        }
    },

    // Mettre à jour une transaction
    async updateTransaction(transactionId, transactionData) {
        try {
            const response = await fetch(`${API_BASE_URL}/transactions/${transactionId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('token')}`
                },
                body: JSON.stringify(transactionData)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error(`Erreur lors de la mise à jour de la transaction ${transactionId}:`, error);
            throw error;
        }
    },

    // Supprimer une transaction
    async deleteTransaction(transactionId) {
        try {
            const response = await fetch(`${API_BASE_URL}/transactions/${transactionId}`, {
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
            console.error(`Erreur lors de la suppression de la transaction ${transactionId}:`, error);
            throw error;
        }
    }
};