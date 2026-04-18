import ApiService from './ApiService';

const TeamSearchService = {
  /**
   * Récupérer la liste des recherches non trouvées
   * TODO: Remettre /admin/ une fois l'authentification réactivée
   */
  getAll(params = {}) {
    return ApiService.get('/team-searches/not-found', { params });
  },

  /**
   * Enregistrer un terme de recherche non trouvé
   * TODO: Remettre /admin/ une fois l'authentification réactivée
   */
  store(data) {
    return ApiService.post('/team-searches/not-found', data);
  },

  /**
   * Associer une recherche à une équipe existante
   * TODO: Remettre /admin/ une fois l'authentification réactivée
   */
  resolve(searchId, teamId) {
    return ApiService.put(`/team-searches/not-found/${searchId}/resolve`, { team_id: teamId });
  },

  /**
   * Supprimer une recherche
   * TODO: Remettre /admin/ une fois l'authentification réactivée
   */
  delete(searchId) {
    return ApiService.delete(`/team-searches/not-found/${searchId}`);
  }
};

export { TeamSearchService };
