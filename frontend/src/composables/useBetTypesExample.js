import { useBetTypes } from './useBetTypes.js';

/**
 * Exemple d'utilisation du composable useBetTypes
 * Ce fichier démontre comment utiliser les différentes fonctionnalités
 */

// Initialisation du composable
const {
  sports,
  sportLabels,
  betTypes,
  betTypeLabels,
  betTypesBySport,
  sportOptions,
  betTypeOptions,
  getSportLabel,
  getBetTypeLabel,
  getBetTypesForSport,
  getSportsForBetType,
  isBetTypeAvailableForSport,
  isValidSport,
  isValidBetType
} = useBetTypes();

// Exemples d'utilisation

console.log('=== EXEMPLES D\'UTILISATION DU COMPOSABLE useBetTypes ===');

// 1. Obtenir tous les sports disponibles
console.log('\n1. Sports disponibles:');
console.log(sportOptions.value);

// 2. Obtenir tous les types de paris disponibles
console.log('\n2. Types de paris disponibles:');
console.log(betTypeOptions.value);

// 3. Obtenir les types de paris pour le football
console.log('\n3. Types de paris pour le football:');
const footballBetTypes = getBetTypesForSport(sports.FOOTBALL);
footballBetTypes.forEach(betType => {
  console.log(`- ${getBetTypeLabel(betType)} (${betType})`);
});

// 4. Obtenir les sports qui supportent le type "Over/Under"
console.log('\n4. Sports qui supportent Over/Under:');
const overUnderSports = getSportsForBetType(betTypes.OVER_UNDER);
overUnderSports.forEach(sport => {
  console.log(`- ${getSportLabel(sport)} (${sport})`);
});

// 5. Vérifier si un type de pari est disponible pour un sport
console.log('\n5. Vérifications de disponibilité:');
console.log(`Handicap disponible pour le tennis: ${isBetTypeAvailableForSport(sports.TENNIS, betTypes.HANDICAP)}`);
console.log(`Both teams score disponible pour le basketball: ${isBetTypeAvailableForSport(sports.BASKETBALL, betTypes.BOTH_TEAMS_SCORE)}`);

// 6. Validation des données
console.log('\n6. Validations:');
console.log(`"football" est un sport valide: ${isValidSport('football')}`);
console.log(`"invalid_sport" est un sport valide: ${isValidSport('invalid_sport')}`);
console.log(`"match_winner" est un type de pari valide: ${isValidBetType('match_winner')}`);
console.log(`"invalid_bet_type" est un type de pari valide: ${isValidBetType('invalid_bet_type')}`);

// 7. Exemple d'utilisation dans un composant Vue
export function createBetTypeSelector(selectedSport) {
  // Obtenir les types de paris disponibles pour le sport sélectionné
  const availableBetTypes = getBetTypesForSport(selectedSport);
  
  // Créer les options pour un sélecteur
  return availableBetTypes.map(betType => ({
    label: getBetTypeLabel(betType),
    value: betType
  }));
}

// 8. Exemple de filtrage dynamique
export function filterBetTypesBySports(selectedSports) {
  const commonBetTypes = [];
  
  // Trouver les types de paris communs à tous les sports sélectionnés
  Object.values(betTypes).forEach(betType => {
    const isAvailableInAllSports = selectedSports.every(sport => 
      isBetTypeAvailableForSport(sport, betType)
    );
    
    if (isAvailableInAllSports) {
      commonBetTypes.push({
        label: getBetTypeLabel(betType),
        value: betType
      });
    }
  });
  
  return commonBetTypes;
}

// 9. Exemple de statistiques
export function getBetTypeStatistics() {
  const stats = {
    totalSports: Object.keys(sports).length,
    totalBetTypes: Object.keys(betTypes).length,
    betTypesBySportCount: {},
    mostPopularBetTypes: {}
  };
  
  // Compter les types de paris par sport
  Object.keys(betTypesBySport).forEach(sport => {
    stats.betTypesBySportCount[getSportLabel(sport)] = betTypesBySport[sport].length;
  });
  
  // Trouver les types de paris les plus populaires (disponibles dans le plus de sports)
  Object.values(betTypes).forEach(betType => {
    const supportedSportsCount = getSportsForBetType(betType).length;
    stats.mostPopularBetTypes[getBetTypeLabel(betType)] = supportedSportsCount;
  });
  
  return stats;
}

console.log('\n7. Statistiques:');
console.log(getBetTypeStatistics());