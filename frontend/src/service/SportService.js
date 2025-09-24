const API_BASE_URL = import.meta.env.VITE_API_URL || 'https://api.auxotracker.lan/api';

export const SportService = {
    /**
     * Récupérer tous les sports disponibles
     */
    async getSports() {
        try {
            const response = await fetch(`${API_BASE_URL}/sports`, {
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
            console.error('Erreur lors de la récupération des sports:', error);
            throw error;
        }
    },

    /**
     * Récupérer les ligues d'un sport spécifique
     */
    async getLeaguesBySport(sportId) {
        try {
            const response = await fetch(`${API_BASE_URL}/sports/${sportId}/leagues`, {
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
            console.error('Erreur lors de la récupération des ligues:', error);
            throw error;
        }
    },

    /**
     * Récupérer les équipes d'une ligue spécifique
     */
    async getTeamsByLeague(leagueId) {
        try {
            const response = await fetch(`${API_BASE_URL}/leagues/${leagueId}/teams`, {
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
            console.error('Erreur lors de la récupération des équipes par ligue:', error);
            throw error;
        }
    },

    /**
     * Récupérer toutes les équipes d'un sport (toutes ligues confondues)
     */
    async getTeamsBySport(sportId) {
        try {
            const response = await fetch(`${API_BASE_URL}/sports/${sportId}/teams`, {
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
            console.error('Erreur lors de la récupération des équipes par sport:', error);
            throw error;
        }
    },

    /**
     * Rechercher des ligues par sport avec pagination et filtrage
     * @param {number} sportId - ID du sport
     * @param {string} search - Terme de recherche (optionnel)
     * @param {number} page - Numéro de page (défaut: 1)
     * @param {number} limit - Nombre d'éléments par page (défaut: 200)
     * @param {number} countryId - ID du pays pour filtrer (optionnel)
     */
    async searchLeaguesBySport(sportId, search = '', page = 1, limit = 200, countryId = null) {
        try {
            const params = new URLSearchParams({
                page: page.toString(),
                limit: limit.toString()
            });
            
            if (search.trim()) {
                params.append('search', search.trim());
            }

            if (countryId) {
                params.append('country_id', countryId.toString());
            }

            const response = await fetch(`${API_BASE_URL}/sports/${sportId}/leagues/search?${params}`, {
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
                total: result.total || 0,
                currentPage: result.current_page || page,
                lastPage: result.last_page || 1,
                hasMore: result.hasMore !== undefined ? result.hasMore : (result.current_page < result.last_page),
                pagination: result.pagination
            };
        } catch (error) {
            console.error('Erreur lors de la recherche des ligues:', error);
            throw error;
        }
    },

    /**
     * Rechercher des équipes par sport avec pagination et filtrage
     * @param {number} sportId - ID du sport
     * @param {string} search - Terme de recherche (optionnel)
     * @param {number} page - Numéro de page (défaut: 1)
     * @param {number} limit - Nombre d'éléments par page (défaut: 200)
     * @param {number} leagueId - ID de la ligue pour filtrer (optionnel)
     */
    async searchTeamsBySport(sportId, search = '', page = 1, limit = 200, leagueId = null, countryId = null) {
        try {
            const params = new URLSearchParams({
                page: page.toString(),
                limit: limit.toString()
            });
            
            if (search.trim()) {
                params.append('search', search.trim());
            }

            if (leagueId) {
                params.append('league_id', leagueId.toString());
            }

            if (countryId) {
                params.append('country_id', countryId.toString());
            }

            const response = await fetch(`${API_BASE_URL}/sports/${sportId}/teams/search?${params}`, {
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
            console.log('🔍 SportService - Réponse API équipes brute:', result);
            
            return {
                data: result.data || [],
                total: result.total || 0,
                currentPage: result.current_page || page,
                lastPage: result.last_page || 1,
                hasMore: result.hasMore !== undefined ? result.hasMore : (result.current_page < result.last_page),
                pagination: result.pagination
            };
        } catch (error) {
            console.error('Erreur lors de la recherche des équipes:', error);
            throw error;
        }
    },

    /**
     * Récupérer les pays qui ont des ligues pour un sport donné
     * @param {number} sportId - ID du sport
     * @returns {Promise<Array>} Liste des pays avec des ligues pour ce sport
     */
    async getCountriesBySport(sportId) {
        try {
            const response = await fetch(`${API_BASE_URL}/sports/${sportId}/countries`, {
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
            console.error('Erreur lors de la récupération des pays par sport:', error);
            throw error;
        }
    }
};