# Documentation du Composant TeamField

## Vue d'ensemble

Le composant `TeamField.vue` est un champ de sélection d'équipe utilisant le composant `AutoComplete` de PrimeVue. Il permet aux utilisateurs de rechercher et sélectionner une équipe parmi une liste filtrée selon le sport, pays et ligue sélectionnés, avec affichage en mode "chip" incluant le logo de l'équipe.

## Localisation

```
/src/components/add-bet/fields/TeamField.vue
```

## Fonctionnalités principales

### 1. Sélection d'équipe avec recherche intelligente
- **Recherche en temps réel** : L'utilisateur peut taper pour filtrer les équipes
- **Affichage avec logos** : Chaque équipe s'affiche avec son logo officiel
- **Mode multiple** : Configuré pour accepter plusieurs sélections (limité à 1 dans la logique métier)
- **Filtrage contextuel** : Les équipes sont filtrées selon le sport, pays et ligue
- **Exclusion d'équipe** : Possibilité d'exclure une équipe spécifique (pour éviter team1 = team2)

### 2. Gestion des dépendances
- **Watchers automatiques** : Surveillance des changements de sport, pays et ligue
- **Rechargement intelligent** : Actualisation automatique des équipes selon le contexte
- **Désactivation conditionnelle** : Le champ est désactivé si aucun sport n'est sélectionné
- **Réinitialisation automatique** : Effacement de la sélection lors des changements de contexte

### 3. Interface utilisateur avancée
- **Dropdown interactif** : Menu déroulant avec bouton dédié
- **Pagination** : Chargement progressif des équipes (scroll infini)
- **États de chargement** : Indicateurs visuels pendant les requêtes
- **Gestion d'erreurs** : Affichage des messages d'erreur
- **Accessibilité** : Support complet des attributs ARIA

## Structure du composant

### Props

| Prop | Type | Défaut | Description |
|------|------|--------|-------------|
| `teamType` | String | - | Type d'équipe ('team1' ou 'team2') **[Requis]** |
| `eventIndex` | Number | - | Index de l'événement dans le formulaire **[Requis]** |
| `sportId` | Number/String | `null` | ID du sport sélectionné |
| `countryId` | Number/String | `null` | ID du pays sélectionné |
| `leagueId` | Number/String | `null` | ID de la ligue sélectionnée |
| `modelValue` | Array | `[]` | Équipe(s) sélectionnée(s) |
| `excludedTeamId` | Number/String | `null` | ID de l'équipe à exclure des résultats |
| `hasError` | Boolean | `false` | Indique si le champ a une erreur |
| `errorMessage` | String | `''` | Message d'erreur à afficher |
| `placeholder` | String | `'Sélectionner une équipe'` | Texte d'aide |
| `apiBaseUrl` | String | `''` | URL de base de l'API |

### Événements émis

| Événement | Paramètres | Description |
|-----------|------------|-------------|
| `update:modelValue` | `value: Array` | Mise à jour de la valeur sélectionnée |
| `team-select` | `team: Object` | Équipe sélectionnée |
| `team-clear` | - | Équipe désélectionnée |
| `search-refresh` | - | Demande de rafraîchissement de la recherche |

### Données réactives

```javascript
const selectedTeam = ref([]);           // Équipe(s) sélectionnée(s)
const teamSearchResults = ref([]);      // Résultats de recherche
const loading = ref(false);             // État de chargement
const currentPage = ref(1);             // Page actuelle pour pagination
const hasMore = ref(true);              // Indicateur de pages supplémentaires
const searchQuery = ref('');            // Requête de recherche actuelle
const teamRef = ref(null);              // Référence au composant AutoComplete
```

## Méthodes principales

### `searchTeams(query = '', page = 1, append = false)`
**Objectif** : Rechercher les équipes selon les critères

```javascript
async searchTeams(query = '', page = 1, append = false) {
  // Construit les paramètres de recherche
  // Filtre par sport, pays, ligue
  // Exclut l'équipe spécifiée
  // Gère la pagination
  // Met à jour teamSearchResults
}
```

### `onSearchTeams(event)`
**Objectif** : Gérer la saisie de recherche utilisateur

```javascript
const onSearchTeams = (event) => {
  // Met à jour searchQuery
  // Réinitialise la pagination
  // Lance la recherche avec le nouveau terme
}
```

### `onTeamSelect(event)`
**Objectif** : Gérer la sélection d'une équipe

```javascript
const onTeamSelect = (event) => {
  // Met à jour selectedTeam avec [event.value]
  // Émet update:modelValue et team-select
  // Ferme le dropdown et retire le focus
}
```

### `onTeamClear()`
**Objectif** : Effacer la sélection d'équipe

```javascript
const onTeamClear = () => {
  // Réinitialise selectedTeam à []
  // Émet update:modelValue et team-clear
  // Recharge les équipes sans filtre de recherche
}
```

### `onTeamDropdownShow()`
**Objectif** : Gérer l'ouverture du dropdown

```javascript
const onTeamDropdownShow = () => {
  // Charge les équipes si nécessaire
  // Gère l'état d'ouverture du dropdown
}
```

### `onDropdownClick()`
**Objectif** : Gérer le clic sur le bouton dropdown

```javascript
const onDropdownClick = () => {
  // Alterne l'état du dropdown
  // Charge les équipes si nécessaire
}
```

### `loadMoreTeams()`
**Objectif** : Charger plus d'équipes (pagination)

```javascript
const loadMoreTeams = async () => {
  // Vérifie s'il y a plus de pages
  // Incrémente currentPage
  // Charge la page suivante en mode append
}
```

## Watchers automatiques

### Surveillance du sport
```javascript
watch(() => props.sportId, (newSportId) => {
  // Réinitialise la sélection si sport change
  // Recharge les équipes pour le nouveau sport
}, { immediate: true });
```

### Surveillance du pays
```javascript
watch(() => props.countryId, () => {
  // Recharge les équipes pour le nouveau pays
  // Maintient la sélection si elle reste valide
});
```

### Surveillance de la ligue
```javascript
watch(() => props.leagueId, () => {
  // Recharge les équipes pour la nouvelle ligue
  // Maintient la sélection si elle reste valide
});
```

### Surveillance de l'équipe exclue
```javascript
watch(() => props.excludedTeamId, () => {
  // Recharge les équipes avec la nouvelle exclusion
  // Vérifie si l'équipe sélectionnée doit être désélectionnée
});
```

## Templates et slots

### Template principal
```vue
<template>
  <div class="flex flex-col gap-2">
    <div class="relative">
      <AutoComplete 
        :ref="(el) => { if (el) teamRef = el }"
        :id="`${teamType}-${eventIndex}`" 
        v-model="selectedTeam" 
        :suggestions="teamSearchResults" 
        @complete="onSearchTeams"
        @focus="onTeamDropdownShow"
        @click="onTeamDropdownShow"
        @item-select="onTeamSelect"
        @clear="onTeamClear"
        @dropdown-click="onDropdownClick"
        optionLabel="name"
        :placeholder="selectedTeam && selectedTeam.length > 0 ? '' : placeholder"
        class="w-full max-w-full select-custom"
        :class="{ 'p-invalid': hasError }"
        :loading="loading"
        :disabled="!sportId"
        :minLength="0"
        dropdown
        dropdownMode="blank"
        forceSelection
        multiple
        display="chip"
      >
        <!-- Templates personnalisés -->
      </AutoComplete>
    </div>
  </div>
</template>
```

### Template chip (équipe sélectionnée)
```vue
<template #chip="slotProps">
  <div class="flex items-center gap-2">
    <!-- Logo de l'équipe -->
    <img 
      v-if="slotProps.value && slotProps.value.id"
      :src="`${apiBaseUrl}/storage/team_logos/${slotProps.value.id}.png`" 
      :alt="slotProps.value.name"
      class="w-4 h-4 rounded object-cover flex-shrink-0" 
      @error="$event.target.style.display='none'"
    />
    <!-- Nom de l'équipe -->
    <span>{{ slotProps.value ? slotProps.value.name : '' }}</span>
  </div>
</template>
```

### Template option (équipes dans le dropdown)
```vue
<template #option="slotProps">
  <div class="flex items-center gap-2 truncate max-w-full" :title="slotProps.option.name">
    <!-- Logo de l'équipe -->
    <img 
      v-if="slotProps.option && slotProps.option.id"
      :src="`${apiBaseUrl}/storage/team_logos/${slotProps.option.id}.png`" 
      :alt="slotProps.option.name"
      class="w-6 h-6 rounded object-cover flex-shrink-0" 
      @error="$event.target.style.display='none'"
    />
    <!-- Nom de l'équipe -->
    <span class="truncate">{{ slotProps.option ? slotProps.option.name : '' }}</span>
  </div>
</template>
```

### Template empty (aucun résultat)
```vue
<template #empty>
  <div class="flex justify-center items-center p-2" v-if="loading">
    <i class="pi pi-spin pi-spinner"></i>
  </div>
  <div class="text-center p-2 text-sm text-gray-500" v-else>
    {{ !sportId ? 'Sélectionnez d\'abord un sport' : 'Aucune équipe trouvée' }}
  </div>
</template>
```

## Utilisation

### Utilisation basique
```vue
<TeamField
  team-type="team1"
  :event-index="0"
  :sport-id="selectedSport?.id"
  v-model="selectedTeam1"
  placeholder="Équipe 1"
  @team-select="onTeam1Select"
  @team-clear="onTeam1Clear"
/>
```

### Utilisation avec exclusion
```vue
<TeamField
  team-type="team2"
  :event-index="0"
  :sport-id="selectedSport?.id"
  :country-id="selectedCountry?.id"
  :league-id="selectedLeague?.id"
  :excluded-team-id="selectedTeam1?.[0]?.id"
  v-model="selectedTeam2"
  :has-error="!!errors.team2"
  :error-message="errors.team2"
  placeholder="Équipe 2"
  @team-select="onTeam2Select"
  @team-clear="onTeam2Clear"
/>
```

### Utilisation avec gestion d'erreurs
```vue
<TeamField
  team-type="team1"
  :event-index="eventIndex"
  :sport-id="eventData.sport_id"
  :country-id="eventData.country_id"
  :league-id="eventData.league_id"
  v-model="eventData.selectedTeam1"
  :has-error="!!errors[`team1-${eventIndex}`]"
  :error-message="errors[`team1-${eventIndex}`]"
  placeholder="Équipe 1"
  :api-base-url="apiBaseUrl"
  @team-select="(team) => onTeamSelect(team, 'team1', eventIndex)"
  @team-clear="() => onTeamClear('team1', eventIndex)"
  @search-refresh="() => onTeamSearchRefresh('team1', eventIndex)"
/>
```

## Intégration avec AddBetForm

### Gestionnaires d'événements dans le parent
```javascript
// Gestion de la sélection d'équipe
const onTeamSelect = (team, teamType, eventIndex) => {
  const eventData = eventCards.value[eventIndex];
  if (teamType === 'team1') {
    eventData.selectedTeam1 = [team];
    eventData.team1 = team.id;
  } else {
    eventData.selectedTeam2 = [team];
    eventData.team2 = team.id;
  }
  
  // Validation et autres logiques
  validateTeamSelection(eventIndex);
};

// Gestion de l'effacement d'équipe
const onTeamClear = (teamType, eventIndex) => {
  const eventData = eventCards.value[eventIndex];
  if (teamType === 'team1') {
    eventData.selectedTeam1 = [];
    eventData.team1 = null;
  } else {
    eventData.selectedTeam2 = [];
    eventData.team2 = null;
  }
  
  // Nettoyage des erreurs
  clearTeamErrors(teamType, eventIndex);
};

// Gestion du rafraîchissement
const onTeamSearchRefresh = (teamType, eventIndex) => {
  // Logique de rafraîchissement si nécessaire
  console.log(`Rafraîchissement demandé pour ${teamType} de l'événement ${eventIndex}`);
};
```

## Styles CSS

### Classes personnalisées
```css
.select-custom {
  width: 100%;
}

.p-invalid {
  border-color: #e24c4c;
}

.text-red-500 {
  color: #e24c4c;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}
```

## Gestion des erreurs

### Types d'erreurs gérées
1. **Erreurs de validation** : Affichées via `hasError` et `errorMessage`
2. **Erreurs de chargement** : Gérées dans les appels API avec toast
3. **Erreurs de réseau** : Retry automatique et messages utilisateur
4. **Erreurs de données** : Validation des réponses API

### Exemple de gestion d'erreur
```javascript
try {
  const response = await SportService.getTeams(params);
  // Traitement des données
} catch (error) {
  console.error('Erreur lors du chargement des équipes:', error);
  toast.add({
    severity: 'error',
    summary: 'Erreur',
    detail: 'Impossible de charger les équipes',
    life: 3000
  });
}
```

## Performance et optimisations

### Optimisations implémentées
1. **Debouncing** : Limitation des appels API lors de la saisie
2. **Cache local** : Évite les rechargements inutiles
3. **Pagination** : Chargement progressif des grandes listes
4. **Watchers optimisés** : Surveillance ciblée des changements
5. **Lazy loading** : Chargement à la demande

### Bonnes pratiques
- Utiliser `immediate: true` pour les watchers critiques
- Nettoyer les timeouts et listeners dans `onUnmounted`
- Valider les props avec des validators personnalisés
- Gérer les états de chargement pour une meilleure UX

## Tests et validation

### Points de test recommandés
1. **Sélection d'équipe** : Vérifier la mise à jour du modèle
2. **Exclusion d'équipe** : Tester que l'équipe exclue n'apparaît pas
3. **Filtrage contextuel** : Vérifier le filtrage par sport/pays/ligue
4. **Pagination** : Tester le chargement de pages supplémentaires
5. **Gestion d'erreurs** : Simuler les erreurs API
6. **Watchers** : Vérifier les rechargements automatiques
7. **Accessibilité** : Tester la navigation au clavier

### Exemple de test unitaire
```javascript
describe('TeamField', () => {
  it('should filter teams by sport', async () => {
    const wrapper = mount(TeamField, {
      props: {
        teamType: 'team1',
        eventIndex: 0,
        sportId: 1
      }
    });
    
    // Simuler la recherche
    await wrapper.vm.searchTeams();
    
    // Vérifier que les équipes sont filtrées
    expect(wrapper.vm.teamSearchResults).toBeDefined();
  });
});
```

## Migration et compatibilité

### Migration depuis l'ancienne implémentation
1. **Remplacement des fonctions** : `searchTeam1/2` → `TeamField`
2. **Gestion des événements** : Nouveaux noms d'événements
3. **Structure des données** : Adaptation du format des équipes
4. **Styles** : Migration vers les nouvelles classes CSS

### Rétrocompatibilité
Le composant est conçu pour être rétrocompatible avec l'ancienne structure de données tout en offrant de nouvelles fonctionnalités.

## Conclusion

Le composant `TeamField` offre une solution complète et réutilisable pour la sélection d'équipes dans l'application. Il intègre toutes les fonctionnalités nécessaires tout en maintenant une interface utilisateur moderne et accessible.

### Avantages principaux
- **Réutilisabilité** : Composant autonome utilisable partout
- **Performance** : Optimisations multiples pour une expérience fluide
- **Accessibilité** : Support complet des standards ARIA
- **Maintenabilité** : Code structuré et bien documenté
- **Extensibilité** : Architecture permettant l'ajout de nouvelles fonctionnalités