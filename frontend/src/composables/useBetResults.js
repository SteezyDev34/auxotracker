import { computed, readonly } from 'vue';

/**
 * Composable pour la gestion globale des options de résultats de paris
 * Fournit des constantes et utilitaires pour les résultats de paris dans toute l'application
 */
export function useBetResults() {
  // Options de résultats disponibles pour les paris
  const resultOptions = readonly([
    { label: 'En cours', value: 'pending' },
    { label: 'Gagné', value: 'win' },
    { label: 'Perdu', value: 'lost' },
    { label: 'Remboursé', value: 'refunded' },
    { label: 'Annulé', value: 'void' },
    { label: '1/2 perdant', value: 'half_lost' },
    { label: '1/2 gagnant', value: 'half_won' }
  ]);

  // Valeurs de résultats sous forme d'objet pour un accès facile
  const resultValues = readonly({
    VOID: 'void',
    WIN: 'win',
    LOST: 'lost',
    PENDING: 'pending',
    REFUNDED: 'refunded',
    HALF_LOST: 'half_lost',
    HALF_WON: 'half_won'
  });

  // Labels de résultats sous forme d'objet pour un accès facile
  const resultLabels = readonly({
    VOID: 'Annulé',
    WIN: 'Gagné',
    LOST: 'Perdu',
    PENDING: 'En cours',
    REFUNDED: 'Remboursé',
    HALF_LOST: '1/2 perdant',
    HALF_WON: '1/2 gagnant'
  });

  /**
   * Obtient le label d'un résultat à partir de sa valeur
   * @param {string} value - La valeur du résultat ('void', 'win', 'lost')
   * @returns {string} Le label correspondant ou la valeur si non trouvée
   */
  const getResultLabel = (value) => {
    const option = resultOptions.find(option => option.value === value);
    return option ? option.label : value;
  };

  /**
   * Obtient la valeur d'un résultat à partir de son label
   * @param {string} label - Le label du résultat ('Annulé', 'Gagné', 'Perdu')
   * @returns {string} La valeur correspondante ou le label si non trouvé
   */
  const getResultValue = (label) => {
    const option = resultOptions.find(option => option.label === label);
    return option ? option.value : label;
  };

  /**
   * Vérifie si une valeur de résultat est valide
   * @param {string} value - La valeur à vérifier
   * @returns {boolean} True si la valeur est valide
   */
  const isValidResult = (value) => {
    return resultOptions.some(option => option.value === value);
  };

  /**
   * Obtient la classe CSS pour un résultat donné (pour l'affichage)
   * @param {string} result - Le résultat ('void', 'win', 'lost', 'pending', 'refunded', 'half_lost', 'half_won')
   * @returns {string} Les classes CSS correspondantes
   */
  const getResultClass = (result) => {
    switch (result) {
      case resultValues.WIN:
        return 'text-green-600 dark:text-green-400';
      case resultValues.LOST:
        return 'text-red-600 dark:text-red-400';
      case resultValues.VOID:
        return 'text-gray-600 dark:text-gray-400';
      case resultValues.PENDING:
        return 'text-blue-600 dark:text-blue-400';
      case resultValues.REFUNDED:
        return 'text-purple-600 dark:text-purple-400';
      case resultValues.HALF_LOST:
        return 'text-orange-600 dark:text-orange-400';
      case resultValues.HALF_WON:
        return 'text-teal-600 dark:text-teal-400';
      default:
        return 'text-gray-600 dark:text-gray-400';
    }
  };

  /**
   * Obtient la classe CSS de badge pour un résultat donné
   * @param {string} result - Le résultat ('void', 'win', 'lost', 'pending', 'refunded', 'half_lost', 'half_won')
   * @returns {string} Les classes CSS de badge correspondantes
   */
  const getResultBadgeClass = (result) => {
    switch (result) {
      case resultValues.WIN:
        return 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200';
      case resultValues.LOST:
        return 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200';
      case resultValues.VOID:
        return 'bg-surface-100 dark:bg-surface-800 text-surface-800 dark:text-surface-200';
      case resultValues.PENDING:
        return 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200';
      case resultValues.REFUNDED:
        return 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-200';
      case resultValues.HALF_LOST:
        return 'bg-orange-100 dark:bg-orange-900/30 text-orange-800 dark:text-orange-200';
      case resultValues.HALF_WON:
        return 'bg-teal-100 dark:bg-teal-900/30 text-teal-800 dark:text-teal-200';
      default:
        return 'bg-surface-100 dark:bg-surface-800 text-surface-800 dark:text-surface-200';
    }
  };

  /**
   * Obtient la classe CSS de fond pour un résultat donné
   * @param {string} result - Le résultat ('void', 'win', 'lost', 'pending', 'refunded', 'half_lost', 'half_won')
   * @returns {string} Les classes CSS de fond correspondantes
   */
  const getResultBackgroundClass = (result) => {
    switch (result) {
      case resultValues.WIN:
        return 'bg-green-500 dark:bg-green-400';
      case resultValues.LOST:
        return 'bg-red-500 dark:bg-red-400';
      case resultValues.VOID:
        return 'bg-surface-500 dark:bg-surface-400';
      case resultValues.PENDING:
        return 'bg-blue-500 dark:bg-blue-400';
      case resultValues.REFUNDED:
        return 'bg-purple-500 dark:bg-purple-400';
      case resultValues.HALF_LOST:
        return 'bg-orange-500 dark:bg-orange-400';
      case resultValues.HALF_WON:
        return 'bg-teal-500 dark:bg-teal-400';
      default:
        return 'bg-surface-500 dark:bg-surface-400';
    }
  };

  // Computed pour les options triées par ordre logique
  const sortedResultOptions = computed(() => {
    return [
      resultOptions.find(option => option.value === resultValues.PENDING),
      resultOptions.find(option => option.value === resultValues.WIN),
      resultOptions.find(option => option.value === resultValues.HALF_WON),
      resultOptions.find(option => option.value === resultValues.LOST),
      resultOptions.find(option => option.value === resultValues.HALF_LOST),
      resultOptions.find(option => option.value === resultValues.VOID),
      resultOptions.find(option => option.value === resultValues.REFUNDED)
    ].filter(Boolean);
  });

  return {
    // Options et valeurs
    resultOptions,
    resultValues,
    resultLabels,
    sortedResultOptions,
    
    // Fonctions utilitaires
    getResultLabel,
    getResultValue,
    isValidResult,
    
    // Fonctions de style
    getResultClass,
    getResultBadgeClass,
    getResultBackgroundClass
  };
}