# Dépendances de la vue profile/bankrolls

## Imports Vue.js
- `ref`, `onMounted`, `reactive` de Vue.js

## Imports PrimeVue
- `useToast` de primevue/usetoast
- `FilterMatchMode` de @primevue/core/api

## Imports Vuelidate
- `useVuelidate` de @vuelidate/core
- `required`, `minValue` de @vuelidate/validators

## Imports date-fns
- `format` de date-fns
- `fr` de date-fns/locale

## Services personnalisés
- `BankrollService` de ../../service/BankrollService
- `BookmakerService` de ../../service/BookmakerService

## Composants PrimeVue utilisés dans le template
- InputText
- InputNumber
- Button
- DataTable
- Column
- Dialog
- Toast (implicite via useToast)

Ces dépendances sont nécessaires pour le fonctionnement de la vue bankrolls.vue qui gère l'affichage, l'ajout, la modification et la suppression des bankrolls de l'utilisateur.