# Documentation CountryField

## Vue d'ensemble

Le composant `CountryField` est un champ de sélection de pays autonome et réutilisable, conçu pour être utilisé dans les formulaires de paris. Il gère automatiquement le chargement des pays disponibles pour un sport donné et fournit une interface de recherche avec drapeaux.

## Fonctionnalités

- **Chargement automatique** : Charge les pays disponibles pour un sport spécifique
- **Recherche en temps réel** : Filtrage côté client des pays
- **Interface visuelle** : Affichage des drapeaux des pays
- **Gestion d'état** : État de chargement et gestion des erreurs
- **Accessibilité** : Support ARIA et navigation clavier
- **Réactivité** : Mise à jour automatique selon le sport sélectionné

## Props

### `modelValue`
- **Type** : `Object`
- **Défaut** : `null`
- **Description** : Valeur liée au v-model, contient le pays sélectionné

### `eventIndex`
- **Type** : `Number`
- **Requis** : `true`
- **Description** : Index de l'événement dans le formulaire

### `sportId`
- **Type** : `[Number, String]`
- **Défaut** : `null`
- **Description** : ID du sport pour lequel charger les pays

### `error`
- **Type** : `String`
- **Défaut** : `''`
- **Description** : Message d'erreur à afficher

## Événements

### `update:modelValue`
- **Paramètres** : `selectedCountry` (Array)
- **Description** : Émis lors de la mise à jour du pays sélectionné

### `country-select`
- **Paramètres** : `event` (Object), `eventIndex` (Number)
- **Description** : Émis lors de la sélection d'un pays

### `country-change`
- **Paramètres** : `eventIndex` (Number)
- **Description** : Émis lors du changement de pays

## Méthodes principales

### `loadCountriesBySport(sportId)`
Charge les pays disponibles pour un sport spécifique depuis l'API.

```javascript
async loadCountriesBySport(sportId) {
  // Charge les pays via CountryService.getCountriesBySport()
  // Met à jour countriesData et countrySearchResults
}
```

### `onSearchCountries(event)`
Filtre les pays selon la requête de recherche.

```javascript
onSearchCountries(event) {
  // Filtre countriesData selon event.query
  // Met à jour countrySearchResults
}
```

### `onCountrySelect(event)`
Gère la sélection d'un pays.

```javascript
onCountrySelect(event) {
  // Met à jour selectedCountry
  // Émet les événements update:modelValue, country-select, country-change
}
```

## Structure des données

### Pays (Country)
```javascript
{
  id: Number,        // ID unique du pays
  name: String,      // Nom du pays
  code: String       // Code ISO du pays
}
```

## Utilisation

### Utilisation de base
```vue
<template>
  <CountryField
    :event-index="0"
    v-model="selectedCountry"
    :sport-id="sportId"
    :error="errors.country_id"
    @country-select="onCountrySelect"
    @country-change="onCountryChange"
  />
</template>

<script>
import CountryField from '@/components/add-bet/fields/CountryField.vue';

export default {
  components: {
    CountryField
  },
  data() {
    return {
      selectedCountry: [],
      sportId: null,
      errors: {}
    }
  },
  methods: {
    onCountrySelect(event, eventIndex) {
      console.log('Pays sélectionné:', event.value);
    },
    onCountryChange(eventIndex) {
      console.log('Changement de pays pour événement:', eventIndex);
    }
  }
}
</script>
```

### Intégration avec SportField
```vue
<template>
  <div>
    <SportField
      v-model="selectedSport"
      :event-index="0"
      @sport-select="onSportSelect"
    />
    
    <CountryField
      v-if="sportId"
      v-model="selectedCountry"
      :event-index="0"
      :sport-id="sportId"
      @country-change="onCountryChange"
    />
  </div>
</template>
```

## Gestion d'état

### États internes
- `selectedCountry` : Pays actuellement sélectionné
- `countriesData` : Liste complète des pays pour le sport
- `countrySearchResults` : Résultats filtrés pour l'affichage
- `isLoading` : État de chargement
- `dropdownOpeningInProgress` : Prévention des ouvertures multiples

### Cycle de vie
1. **Montage** : Aucun chargement automatique
2. **Changement de sport** : Chargement automatique des pays
3. **Recherche** : Filtrage en temps réel
4. **Sélection** : Mise à jour et émission d'événements

## Dépendances

- **PrimeVue** : Composant AutoComplete
- **CountryService** : Service pour l'API des pays
- **Images** : Drapeaux stockés dans `/storage/country_flags/`

## Styles

Le composant utilise les classes CSS suivantes :
- `select-custom` : Style personnalisé pour le champ
- `p-invalid` : Style d'erreur
- Responsive design avec Tailwind CSS

## Accessibilité

- Labels appropriés avec `for` et `id`
- Attributs ARIA pour les lecteurs d'écran
- Navigation clavier supportée
- Messages d'erreur associés

## Performance

- **Cache côté client** : Les pays sont mis en cache par sport
- **Filtrage local** : Recherche sans appel API
- **Chargement à la demande** : Pays chargés uniquement si nécessaire
- **Debouncing** : Prévention des ouvertures multiples de dropdown

## Exemple complet

```vue
<template>
  <div class="space-y-4">
    <SportField
      v-model="formData.selectedSport"
      :event-index="0"
      @sport-select="handleSportSelect"
    />
    
    <CountryField
      v-if="formData.sport_id"
      v-model="formData.selectedCountry"
      :event-index="0"
      :sport-id="formData.sport_id"
      :error="errors.country_id"
      @country-select="handleCountrySelect"
      @country-change="handleCountryChange"
    />
  </div>
</template>

<script>
import SportField from '@/components/add-bet/fields/SportField.vue';
import CountryField from '@/components/add-bet/fields/CountryField.vue';

export default {
  components: {
    SportField,
    CountryField
  },
  data() {
    return {
      formData: {
        selectedSport: [],
        sport_id: null,
        selectedCountry: [],
        country_id: null
      },
      errors: {}
    }
  },
  methods: {
    handleSportSelect(event, eventIndex) {
      if (event.value) {
        this.formData.sport_id = event.value.id;
        // Réinitialiser le pays lors du changement de sport
        this.formData.selectedCountry = [];
        this.formData.country_id = null;
      }
    },
    
    handleCountrySelect(event, eventIndex) {
      if (event.value) {
        this.formData.country_id = event.value.id;
      }
    },
    
    handleCountryChange(eventIndex) {
      // Logique additionnelle lors du changement de pays
      console.log('Pays changé pour événement:', eventIndex);
    }
  }
}
</script>
```