import ApiService from './ApiService';

const LeagueService = {
  getAll(params = {}) {
    return ApiService.get('/admin/leagues', { params });
  },

  updatePriorities(priorities) {
    return ApiService.put('/admin/leagues/priorities', { priorities });
  },

  update(leagueId, data) {
    return ApiService.put(`/admin/leagues/${leagueId}`, data);
  },

  getSports() {
    return ApiService.get('/sports');
  },

  getCountries() {
    return ApiService.get('/countries');
  },

  getCountriesBySport(sportId) {
    return ApiService.get(`/sports/${sportId}/countries`);
  }
};

export { LeagueService };
