# Documentation LeagueField.vue

## Vue d'ensemble

Le composant `LeagueField.vue` est un champ de sélection de ligue intelligent qui permet aux utilisateurs de rechercher et sélectionner une ligue sportive. Il fait partie du système de formulaire de paris et s'intègre parfaitement avec les composants `SportField` et `CountryField`.

## Caractéristiques principales

- **Recherche intelligente** : Recherche de ligues par nom avec filtrage par sport et pays
- **Affichage en chips** : Affichage de la ligue sélectionnée sous forme de chip avec logo
- **Logos dynamiques** : Support des logos de ligues avec thème clair/sombre
- **Validation** : Gestion des erreurs et messages d'erreur
- **Accessibilité** : Support complet ARIA pour l'accessibilité
- **Réactivité** : Synchronisation automatique avec les changements de sport/pays

---

## Props

### `sportId`
- **Type** : `Number | String`
- **Défaut** : `null`
- **Description** : ID du sport sélectionné. Requis pour activer le champ de ligue.
- **Exemple** : `1` (pour Football)

### `countryId`
- **Type** : `Number | String`
- **Défaut** : `null`
- **Description** : ID du pays sélectionné. Utilisé pour filtrer les ligues par pays.
- **Exemple** : `75` (pour France)

### `modelValue`
- **Type** : `Array`
- **Défaut** : `[]`
- **Description** : Tableau contenant la ligue sélectionnée. Compatible avec le système v-model.
- **Format** : `[{ id: Number, name: String, country: Object, ... }]`

### `hasError`
- **Type** : `Boolean`
- **Défaut** : `false`
- **Description** : Indique si le champ est en état d'erreur pour l'affichage visuel.

### `errorMessage`
- **Type** : `String`
- **Défaut** : `''`
- **Description** : Message d'erreur à afficher sous le champ.

---

## Événements émis

### `update:modelValue`
- **Payload** : `Array`
- **Description** : Émis lors de la modification de la sélection pour la synchronisation v-model.
- **Exemple** : `[{ id: 17, name: "Ligue 1", country: {...} }]`

### `league-select`
- **Payload** : `Object | null`
- **Description** : Émis lors de la sélection d'une ligue.
- **Exemple** : `{ id: 17, name: "Ligue 1", country: {...} }`

### `league-clear`
- **Payload** : `void`
- **Description** : Émis lors de l'effacement de la sélection.

---

## Fonctions principales

### `searchLeagues(event)`
**Description** : Recherche des ligues par sport et pays avec filtrage par nom.

**Paramètres** :
- `event.query` (String) : Terme de recherche saisi par l'utilisateur

**Comportement** :
1. Vérifie qu'un sport est sélectionné
2. Appelle l'API `SportService.searchLeaguesBySport()`
3. Filtre par pays si `countryId` est fourni
4. Met à jour `leagueSearchResults`
5. Gère les erreurs avec des toasts

**Exemple d'utilisation** :
```javascript
// Recherche automatique lors de la saisie
searchLeagues({ query: "Ligue" }); // Trouve "Ligue 1", "Ligue 2", etc.
```

### `onLeagueDropdownShow()`
**Description** : Gère l'ouverture du dropdown de sélection.

**Comportement** :
1. Vérifie si l'ouverture n'est pas déjà en cours
2. Contrôle qu'un sport est sélectionné
3. Charge les ligues si nécessaire
4. Force l'ouverture du dropdown
5. Prévient les ouvertures multiples

### `onLeagueSelect(event)`
**Description** : Gère la sélection d'une ligue.

**Paramètres** :
- `event.value` (Object) : Objet ligue sélectionnée

**Comportement** :
1. Remplace la sélection existante par la nouvelle ligue
2. Émet `update:modelValue` et `league-select`
3. Ferme le dropdown automatiquement
4. Retire le focus du champ

**Format de l'objet ligue** :
```javascript
{
  id: 17,
  name: "Ligue 1",
  country: {
    id: 75,
    name: "France"
  },
  sport_id: 1,
  slug: "ligue-1"
}
```

### `onLeagueClear()`
**Description** : Gère l'effacement de la sélection.

**Comportement** :
1. Réinitialise `selectedLeague` à un tableau vide
2. Émet `update:modelValue` et `league-clear`
3. Log de l'action pour le débogage

### `onDropdownClick()`
**Description** : Gère le clic sur l'icône dropdown.

**Comportement** :
1. Vérifie qu'un sport est sélectionné
2. Affiche un toast d'avertissement si aucun sport
3. Charge les ligues si nécessaire

### `closeDropdownAndBlur()`
**Description** : Ferme le dropdown et retire le focus du champ.

**Utilisation** : Appelée automatiquement après sélection pour améliorer l'UX.

---

## Watchers

### Watcher `props.modelValue`
**Description** : Synchronise la valeur externe avec l'état local.
```javascript
watch(() => props.modelValue, (newVal) => {
  selectedLeague.value = newVal || [];
}, { deep: true });
```

### Watcher `[sportId, countryId]`
**Description** : Réinitialise la sélection lors du changement de sport ou pays.
```javascript
watch([() => props.sportId, () => props.countryId], ([newSportId, newCountryId], [oldSportId, oldCountryId]) => {
  if (newSportId !== oldSportId || newCountryId !== oldCountryId) {
    selectedLeague.value = [];
    leagueSearchResults.value = [];
    emit('update:modelValue', null);
    emit('league-clear');
  }
});
```

---

## Templates

### Template `#chip`
**Description** : Affichage de la ligue sélectionnée sous forme de chip.

**Fonctionnalités** :
- Logo de la ligue avec support thème clair/sombre
- Nom de la ligue
- Gestion d'erreur de chargement d'image

```vue
<template #chip="slotProps">
  <div class="flex items-center gap-2">
    <img 
      v-if="slotProps.value && slotProps.value.id"
      :src="`${apiBaseUrl}/storage/league_logos/${slotProps.value.id}${isDarkTheme ? '-dark' : ''}.png`" 
      :alt="slotProps.value.name"
      class="w-4 h-4 rounded object-cover flex-shrink-0" 
      @error="$event.target.style.display='none'"
    />
    <span>{{ slotProps.value ? slotProps.value.name : '' }}</span>
  </div>
</template>
```

### Template `#option`
**Description** : Affichage des options dans le dropdown.

**Fonctionnalités** :
- Drapeau du pays de la ligue
- Logo de la ligue
- Nom de la ligue
- Tooltip avec le nom complet

```vue
<template #option="slotProps">
  <div class="flex items-center gap-2 truncate max-w-full" :title="slotProps.option.name">
    <img
      v-if="slotProps.option.id"
      :src="`${apiBaseUrl}/storage/country_flags/${slotProps.option.country.id}.png`"
      class="w-4 h-4 rounded object-cover flex-shrink-0" 
    />
    <img
      v-if="slotProps.option.id"
      :src="`${apiBaseUrl}/storage/league_logos/${slotProps.option.id}${isDarkTheme ? '-dark' : ''}.png`"
      :alt="slotProps.option.name"
      class="w-4 h-4 rounded object-cover flex-shrink-0" 
      @error="$event.target.style.display='none'"
    />
    <span>{{ slotProps.option.name }}</span>
  </div>
</template>
```

---

## Configuration AutoComplete

### Propriétés principales
```vue
<AutoComplete
  v-model="selectedLeague"
  :suggestions="leagueSearchResults"
  optionLabel="name"
  :placeholder="selectedLeague && selectedLeague.length > 0 ? '' : 'Ligue...'"
  :loading="loading"
  dropdown
  dropdownMode="blank"
  forceSelection
  multiple
  display="chip"
  :disabled="!sportId"
/>
```

### Propriétés d'accessibilité
```vue
aria-label="Rechercher et sélectionner une ligue"
role="combobox"
aria-expanded="false"
aria-autocomplete="list"
```

---

## Gestion des logos

### Structure des URLs
- **Logos de ligues** : `${apiBaseUrl}/storage/league_logos/${leagueId}${isDarkTheme ? '-dark' : ''}.png`
- **Drapeaux de pays** : `${apiBaseUrl}/storage/country_flags/${countryId}.png`

### Support thème sombre
Le composant détecte automatiquement le thème via `useLayout()` et charge les logos appropriés :
- Thème clair : `league_logo_17.png`
- Thème sombre : `league_logo_17-dark.png`

### Gestion d'erreur
```javascript
@error="$event.target.style.display='none'"
```
Cache automatiquement les images qui ne se chargent pas.

---

## Intégration avec AddBetForm

### Utilisation dans le formulaire
```vue
<LeagueField
  v-model="eventData.selectedLeague"
  :sport-id="eventData.sport_id"
  :country-id="eventData.country_id"
  :has-error="!!errors[`league-${eventIndex}`]"
  :error-message="errors[`league-${eventIndex}`]"
  @league-select="(league) => onLeagueSelect({ value: league }, eventIndex)"
  @league-clear="() => onLeagueClear(eventIndex)"
/>
```

### Synchronisation des données
Le composant maintient deux propriétés synchronisées :
- `selectedLeague` : Tableau pour le v-model (affichage)
- `league` : ID numérique pour la logique métier

---

## Gestion des erreurs

### Types d'erreurs gérées
1. **Erreur API** : Problème de connexion ou serveur
2. **Erreur de validation** : Champ requis non rempli
3. **Erreur de chargement d'image** : Logo non disponible

### Affichage des erreurs
```vue
<small v-if="hasError" class="p-error">{{ errorMessage }}</small>
```

### Toasts d'erreur
```javascript
toast.add({
  severity: 'error',
  summary: 'Erreur',
  detail: 'Impossible de rechercher les ligues',
  life: 3000
});
```

---

## Styles CSS

### Classes principales
```css
.field {
  margin-bottom: 1rem;
}

.p-autocomplete {
  width: 100%;
}

.p-invalid {
  border-color: #e24c4c;
}

.p-error {
  color: #e24c4c;
}
```

### Classes Tailwind utilisées
- `w-full` : Largeur complète
- `flex items-center gap-2` : Layout flex avec espacement
- `w-4 h-4 rounded` : Taille et forme des logos
- `truncate max-w-full` : Gestion du débordement de texte

---

## Dépendances

### Composants Vue
- `AutoComplete` (PrimeVue)

### Composables
- `useToast` (PrimeVue)
- `useLayout` (Layout personnalisé)

### Services
- `SportService.searchLeaguesBySport()`

### Fonctions Vue
- `ref`, `watch`, `computed`, `nextTick`

---

## Exemples d'utilisation

### Utilisation basique
```vue
<template>
  <LeagueField
    v-model="selectedLeague"
    :sport-id="sportId"
    :country-id="countryId"
  />
</template>

<script>
import LeagueField from '@/components/add-bet/fields/LeagueField.vue';

export default {
  components: { LeagueField },
  data() {
    return {
      selectedLeague: [],
      sportId: 1,
      countryId: 75
    };
  }
};
</script>
```

### Utilisation avec gestion d'erreurs
```vue
<template>
  <LeagueField
    v-model="selectedLeague"
    :sport-id="sportId"
    :country-id="countryId"
    :has-error="!!errors.league"
    :error-message="errors.league"
    @league-select="onLeagueSelected"
    @league-clear="onLeagueCleared"
  />
</template>

<script>
export default {
  data() {
    return {
      selectedLeague: [],
      sportId: 1,
      countryId: 75,
      errors: {}
    };
  },
  methods: {
    onLeagueSelected(league) {
      console.log('Ligue sélectionnée:', league);
      this.errors.league = null; // Effacer l'erreur
    },
    onLeagueCleared() {
      console.log('Ligue effacée');
    }
  }
};
</script>
```

---

## Bonnes pratiques

### Performance
1. **Lazy loading** : Les ligues ne sont chargées qu'à l'ouverture du dropdown
2. **Debouncing** : La recherche est optimisée pour éviter les appels excessifs
3. **Cache** : Les résultats sont mis en cache localement

### UX/UI
1. **Feedback visuel** : Loading spinner pendant la recherche
2. **Validation** : Messages d'erreur clairs et contextuels
3. **Accessibilité** : Support complet des lecteurs d'écran

### Maintenance
1. **Logs** : Logging détaillé pour le débogage
2. **Gestion d'erreur** : Gestion robuste des cas d'erreur
3. **Documentation** : Code bien commenté en français

---

## Dépannage

### Problèmes courants

#### Le dropdown ne s'ouvre pas
**Cause** : Aucun sport sélectionné
**Solution** : Vérifier que `sportId` est défini

#### Aucune ligue trouvée
**Cause** : Problème de connexion API ou filtres trop restrictifs
**Solution** : Vérifier la console pour les erreurs API

#### Logos manquants
**Cause** : Fichiers d'images non disponibles sur le serveur
**Solution** : Vérifier la structure des dossiers `/storage/league_logos/`

#### Erreur de type modelValue
**Cause** : Incompatibilité entre Array et Number
**Solution** : S'assurer que le parent utilise un tableau pour le v-model

---

## Évolutions futures

### Améliorations possibles
1. **Pagination** : Support de la pagination pour de grandes listes
2. **Favoris** : Système de ligues favorites
3. **Recherche avancée** : Filtres supplémentaires (continent, niveau)
4. **Cache intelligent** : Mise en cache côté client plus sophistiquée

### Compatibilité
- Vue 3.x
- PrimeVue 3.x
- Navigateurs modernes (ES6+)