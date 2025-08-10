const API_BASE_URL = import.meta.env.VITE_API_URL || 'https://api.auxotracker.lan/api';

export const CountryService = {
    /**
     * Récupérer tous les pays depuis l'API
     * @returns {Promise<Array>} Liste des pays avec leurs vrais IDs
     */
    async getCountries() {
        try {
            const response = await fetch(`${API_BASE_URL}/countries`, {
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

            const result = await response.json();
            return result.data || [];
        } catch (error) {
            console.error('Erreur lors de la récupération des pays:', error);
            throw error;
        }
    },

    /**
     * Rechercher des pays par nom
     * @param {string} search - Terme de recherche
     * @returns {Promise<Array>} Liste des pays filtrés
     */
    async searchCountries(search = '') {
        try {
            const params = new URLSearchParams();
            if (search.trim()) {
                params.append('search', search.trim());
            }

            const response = await fetch(`${API_BASE_URL}/countries/search?${params}`, {
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

            const result = await response.json();
            return result.data || [];
        } catch (error) {
            console.error('Erreur lors de la recherche des pays:', error);
            throw error;
        }
    },

    /**
     * Rechercher des pays avec pagination
     * @param {string} search - Terme de recherche
     * @param {number} page - Numéro de page (défaut: 1)
     * @param {number} limit - Nombre d'éléments par page (défaut: 30)
     * @returns {Promise<Object>} Objet contenant les données et informations de pagination
     */
    async searchCountriesWithPagination(search = '', page = 1, limit = 30) {
        try {
            const params = new URLSearchParams();
            if (search.trim()) {
                params.append('search', search.trim());
            }
            params.append('page', page.toString());
            params.append('limit', limit.toString());

            const response = await fetch(`${API_BASE_URL}/countries/search?${params}`, {
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

            const result = await response.json();
            return {
                data: result.data || [],
                hasMore: result.hasMore || false,
                pagination: result.pagination || {}
            };
        } catch (error) {
            console.error('Erreur lors de la recherche des pays avec pagination:', error);
            throw error;
        }
    },

    /**
     * Méthode de compatibilité pour les anciens appels
     * @deprecated Utiliser getCountries() à la place
     */
    getData() {
        console.warn('CountryService.getData() est déprécié. Utilisez getCountries() à la place.');
        return this.getCountries();
    }
};
