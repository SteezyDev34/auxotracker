import { computed, readonly } from 'vue';

/**
 * Composable pour la gestion globale des types de paris par sport
 * Fournit des constantes et utilitaires pour les types de paris dans toute l'application
 */
export function useBetTypes() {
  // Sports disponibles
  const sports = readonly({
    FOOTBALL: 'football',
    BASKETBALL: 'basketball',
    TENNIS: 'tennis',
    HOCKEY: 'hockey',
    BASEBALL: 'baseball',
    VOLLEYBALL: 'volleyball',
    HANDBALL: 'handball',
    RUGBY: 'rugby',
    AMERICAN_FOOTBALL: 'american_football',
    ESPORTS: 'esports'
  });

  // Labels des sports
  const sportLabels = readonly({
    FOOTBALL: 'Football',
    BASKETBALL: 'Basketball',
    TENNIS: 'Tennis',
    HOCKEY: 'Hockey',
    BASEBALL: 'Baseball',
    VOLLEYBALL: 'Volleyball',
    HANDBALL: 'Handball',
    RUGBY: 'Rugby',
    AMERICAN_FOOTBALL: 'Football Américain',
    ESPORTS: 'Esports'
  });

  // Types de paris disponibles
  const betTypes = readonly({
    // Types de base (disponibles pour tous les sports)
    MATCH_WINNER: 'match_winner',
    DRAW_NO_BET: 'draw_no_bet',
    DOUBLE_CHANCE: 'double_chance',
    
    // Types avec scores/points
    OVER_UNDER: 'over_under',
    EXACT_SCORE: 'exact_score',
    BOTH_TEAMS_SCORE: 'both_teams_score',
    
    // Types avec handicap
    HANDICAP: 'handicap',
    ASIAN_HANDICAP: 'asian_handicap',
    
    // Types temporels
    FIRST_HALF: 'first_half',
    SECOND_HALF: 'second_half',
    FIRST_QUARTER: 'first_quarter',
    SECOND_QUARTER: 'second_quarter',
    THIRD_QUARTER: 'third_quarter',
    FOURTH_QUARTER: 'fourth_quarter',
    
    // Types spécifiques
    CORRECT_SCORE: 'correct_score',
    FIRST_GOAL: 'first_goal',
    LAST_GOAL: 'last_goal',
    ANYTIME_SCORER: 'anytime_scorer',
    FIRST_SCORER: 'first_scorer',
    
    // Types tennis spécifiques
    SET_WINNER: 'set_winner',
    GAME_WINNER: 'game_winner',
    
    // Types basketball spécifiques
    POINT_SPREAD: 'point_spread',
    TOTAL_POINTS: 'total_points'
  });

  // Labels des types de paris
  const betTypeLabels = readonly({
    MATCH_WINNER: '1X2 / Vainqueur du match',
    DRAW_NO_BET: 'Match nul remboursé',
    DOUBLE_CHANCE: 'Double chance',
    OVER_UNDER: 'Plus/Moins',
    EXACT_SCORE: 'Score exact',
    BOTH_TEAMS_SCORE: 'Les deux équipes marquent',
    HANDICAP: 'Handicap',
    ASIAN_HANDICAP: 'Handicap asiatique',
    FIRST_HALF: 'Première mi-temps',
    SECOND_HALF: 'Deuxième mi-temps',
    FIRST_QUARTER: 'Premier quart-temps',
    SECOND_QUARTER: 'Deuxième quart-temps',
    THIRD_QUARTER: 'Troisième quart-temps',
    FOURTH_QUARTER: 'Quatrième quart-temps',
    CORRECT_SCORE: 'Score correct',
    FIRST_GOAL: 'Premier but',
    LAST_GOAL: 'Dernier but',
    ANYTIME_SCORER: 'Buteur à tout moment',
    FIRST_SCORER: 'Premier buteur',
    SET_WINNER: 'Vainqueur du set',
    GAME_WINNER: 'Vainqueur du jeu',
    POINT_SPREAD: 'Écart de points',
    TOTAL_POINTS: 'Total de points'
  });

  // Mapping des types de paris par sport
  const betTypesBySport = readonly({
    [sports.FOOTBALL]: [
      betTypes.MATCH_WINNER,
      betTypes.DRAW_NO_BET,
      betTypes.DOUBLE_CHANCE,
      betTypes.OVER_UNDER,
      betTypes.EXACT_SCORE,
      betTypes.BOTH_TEAMS_SCORE,
      betTypes.HANDICAP,
      betTypes.ASIAN_HANDICAP,
      betTypes.FIRST_HALF,
      betTypes.SECOND_HALF,
      betTypes.CORRECT_SCORE,
      betTypes.FIRST_GOAL,
      betTypes.LAST_GOAL,
      betTypes.ANYTIME_SCORER,
      betTypes.FIRST_SCORER
    ],
    [sports.BASKETBALL]: [
      betTypes.MATCH_WINNER,
      betTypes.OVER_UNDER,
      betTypes.HANDICAP,
      betTypes.POINT_SPREAD,
      betTypes.TOTAL_POINTS,
      betTypes.FIRST_QUARTER,
      betTypes.SECOND_QUARTER,
      betTypes.THIRD_QUARTER,
      betTypes.FOURTH_QUARTER,
      betTypes.FIRST_HALF,
      betTypes.SECOND_HALF
    ],
    [sports.TENNIS]: [
      betTypes.MATCH_WINNER,
      betTypes.SET_WINNER,
      betTypes.GAME_WINNER,
      betTypes.HANDICAP,
      betTypes.OVER_UNDER,
      betTypes.EXACT_SCORE
    ],
    [sports.HOCKEY]: [
      betTypes.MATCH_WINNER,
      betTypes.DRAW_NO_BET,
      betTypes.OVER_UNDER,
      betTypes.HANDICAP,
      betTypes.FIRST_HALF,
      betTypes.SECOND_HALF,
      betTypes.EXACT_SCORE
    ],
    [sports.BASEBALL]: [
      betTypes.MATCH_WINNER,
      betTypes.OVER_UNDER,
      betTypes.HANDICAP,
      betTypes.FIRST_HALF,
      betTypes.TOTAL_POINTS
    ],
    [sports.VOLLEYBALL]: [
      betTypes.MATCH_WINNER,
      betTypes.SET_WINNER,
      betTypes.HANDICAP,
      betTypes.OVER_UNDER,
      betTypes.EXACT_SCORE
    ],
    [sports.HANDBALL]: [
      betTypes.MATCH_WINNER,
      betTypes.DRAW_NO_BET,
      betTypes.DOUBLE_CHANCE,
      betTypes.OVER_UNDER,
      betTypes.HANDICAP,
      betTypes.FIRST_HALF,
      betTypes.SECOND_HALF,
      betTypes.EXACT_SCORE
    ],
    [sports.RUGBY]: [
      betTypes.MATCH_WINNER,
      betTypes.DRAW_NO_BET,
      betTypes.HANDICAP,
      betTypes.OVER_UNDER,
      betTypes.FIRST_HALF,
      betTypes.SECOND_HALF,
      betTypes.EXACT_SCORE
    ],
    [sports.AMERICAN_FOOTBALL]: [
      betTypes.MATCH_WINNER,
      betTypes.POINT_SPREAD,
      betTypes.OVER_UNDER,
      betTypes.HANDICAP,
      betTypes.FIRST_QUARTER,
      betTypes.SECOND_QUARTER,
      betTypes.THIRD_QUARTER,
      betTypes.FOURTH_QUARTER,
      betTypes.FIRST_HALF,
      betTypes.SECOND_HALF
    ],
    [sports.ESPORTS]: [
      betTypes.MATCH_WINNER,
      betTypes.HANDICAP,
      betTypes.OVER_UNDER,
      betTypes.EXACT_SCORE
    ]
  });

  /**
   * Obtient le label d'un sport à partir de sa valeur
   * @param {string} value - La valeur du sport
   * @returns {string} Le label correspondant ou la valeur si non trouvée
   */
  const getSportLabel = (value) => {
    const sportKey = Object.keys(sports).find(key => sports[key] === value);
    return sportKey ? sportLabels[sportKey] : value;
  };

  /**
   * Obtient le label d'un type de pari à partir de sa valeur
   * @param {string} value - La valeur du type de pari
   * @returns {string} Le label correspondant ou la valeur si non trouvée
   */
  const getBetTypeLabel = (value) => {
    const betTypeKey = Object.keys(betTypes).find(key => betTypes[key] === value);
    return betTypeKey ? betTypeLabels[betTypeKey] : value;
  };

  /**
   * Obtient les types de paris disponibles pour un sport donné
   * @param {string} sport - Le sport pour lequel obtenir les types de paris
   * @returns {Array} Liste des types de paris disponibles pour ce sport
   */
  const getBetTypesForSport = (sport) => {
    return betTypesBySport[sport] || [];
  };

  /**
   * Obtient les sports qui supportent un type de pari donné
   * @param {string} betType - Le type de pari
   * @returns {Array} Liste des sports qui supportent ce type de pari
   */
  const getSportsForBetType = (betType) => {
    const supportedSports = [];
    Object.keys(betTypesBySport).forEach(sport => {
      if (betTypesBySport[sport].includes(betType)) {
        supportedSports.push(sport);
      }
    });
    return supportedSports;
  };

  /**
   * Vérifie si un type de pari est disponible pour un sport donné
   * @param {string} sport - Le sport
   * @param {string} betType - Le type de pari
   * @returns {boolean} True si le type de pari est disponible pour ce sport
   */
  const isBetTypeAvailableForSport = (sport, betType) => {
    return betTypesBySport[sport]?.includes(betType) || false;
  };

  /**
   * Vérifie si un sport est valide
   * @param {string} sport - Le sport à vérifier
   * @returns {boolean} True si le sport est valide
   */
  const isValidSport = (sport) => {
    return Object.values(sports).includes(sport);
  };

  /**
   * Vérifie si un type de pari est valide
   * @param {string} betType - Le type de pari à vérifier
   * @returns {boolean} True si le type de pari est valide
   */
  const isValidBetType = (betType) => {
    return Object.values(betTypes).includes(betType);
  };

  // Options de sports pour les sélecteurs
  const sportOptions = computed(() => {
    return Object.keys(sports).map(key => ({
      label: sportLabels[key],
      value: sports[key]
    }));
  });

  // Options de types de paris pour les sélecteurs
  const betTypeOptions = computed(() => {
    return Object.keys(betTypes).map(key => ({
      label: betTypeLabels[key],
      value: betTypes[key]
    }));
  });

  return {
    // Constantes
    sports,
    sportLabels,
    betTypes,
    betTypeLabels,
    betTypesBySport,
    
    // Options pour les sélecteurs
    sportOptions,
    betTypeOptions,
    
    // Fonctions utilitaires
    getSportLabel,
    getBetTypeLabel,
    getBetTypesForSport,
    getSportsForBetType,
    isBetTypeAvailableForSport,
    isValidSport,
    isValidBetType
  };
}