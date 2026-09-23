import ApiService from './ApiService';

const MatchService = {
    async getTodayMatches(sportId = null, date = null, page = 1, perPage = 50, sort = null, valueBetsOnly = false) {
        const params = { page, per_page: perPage };
        if (sportId) params.sport_id = sportId;
        if (date) params.date = date;
        if (sort) params.sort = sort;
        if (valueBetsOnly) params.value_bets = 1;
        return ApiService.get('/matches/today', { params });
    }
};

export default MatchService;
