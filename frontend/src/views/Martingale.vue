<script setup>
import { ref, computed } from 'vue';
import Card from 'primevue/card';
import Fluid from 'primevue/fluid';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import Checkbox from 'primevue/checkbox';
import { useToast } from 'primevue/usetoast';
import { useBetResults } from '@/composables/useBetResults';

const toast = useToast();

// Composable pour les résultats de paris
const { resultLabels } = useBetResults();

// Variables pour la génération de paris aléatoires
const nombreParis = ref(10);
const coteMin = ref(1.5);
const coteMax = ref(3.0);
const objectifGainGlobal = ref(100);
const gainParParis = ref(10);
const capitalInitial = ref(1000);
const inclureGainsManques = ref(false);
const parisAleatoires = ref([]);





// Fonction pour générer des paris aléatoires avec logique de martingale
const genererParisAleatoires = () => {
    parisAleatoires.value = [];
    let capitalActuel = capitalInitial.value;
    let miseActuelle = 0;
    let seriePertes = 0;
    let gainsManquesCumules = 0; // Cumul des gains manqués lors des pertes
    let pertesCumulees = 0; // Cumul des pertes pour le calcul de la mise
    
    for (let i = 0; i < nombreParis.value; i++) {
        // Arrêter si l'objectif est atteint
        if (capitalActuel >= capitalInitial.value + objectifGainGlobal.value) {
            break;
        }
        
        const cote = (Math.random() * (coteMax.value - coteMin.value) + coteMin.value).toFixed(2);
        
        // Calculer la mise nécessaire pour atteindre le gain par paris
        const miseNecessaire = gainParParis.value / (parseFloat(cote) - 1);
        
        // Calculer la mise selon la logique de martingale
        if (seriePertes === 0) {
            // Premier pari ou après un gain : utiliser la mise pour le gain par paris
            miseActuelle = miseNecessaire;
        } else {
            // Après une perte : calculer la mise pour récupérer les pertes + gain par paris
            if (inclureGainsManques.value) {
                // Mise = (pertes cumulées + gains manqués cumulés + gain par paris) / (cote - 1)
                miseActuelle = (pertesCumulees + gainsManquesCumules + gainParParis.value) / (parseFloat(cote) - 1);
            } else {
                // Mise = (pertes cumulées + gain par paris) / (cote - 1)
                miseActuelle = (pertesCumulees + gainParParis.value) / (parseFloat(cote) - 1);
            }
        }
        
        // Vérifier si le capital est suffisant pour la mise
        if (capitalActuel < miseActuelle) {
            toast.add({ 
                severity: 'warn', 
                summary: 'Capital insuffisant', 
                detail: `Capital actuel: ${capitalActuel.toFixed(2)}€, Mise requise: ${miseActuelle.toFixed(2)}€`, 
                life: 3000 
            });
            break;
        }
        
        const resultat = Math.random() > 0.5;
        let gain = resultat ? (miseActuelle * parseFloat(cote)) - miseActuelle : -miseActuelle;
        
        capitalActuel += gain;
        
        parisAleatoires.value.push({
            id: i + 1,
            cote: parseFloat(cote),
            resultat: resultat ? resultLabels.WIN : resultLabels.LOST,
            mise: miseActuelle.toFixed(2),
            gain: gain.toFixed(2),
            capitalEvolution: capitalActuel.toFixed(2),
            seriePertes: seriePertes,
            gainsManquesCumules: gainsManquesCumules.toFixed(2),
            miseNecessaire: miseNecessaire.toFixed(2)
        });
        
        // Logique de martingale
        if (resultat) {
            // Pari gagné : remettre à zéro les compteurs
            seriePertes = 0;
            pertesCumulees = 0;
            gainsManquesCumules = 0; // Remettre à zéro les gains manqués après un gain
        } else {
            // Pari perdu : mettre à jour les compteurs
            pertesCumulees += miseActuelle;
            seriePertes++;
            
            if (inclureGainsManques.value) {
                // Ajouter le gain manqué (ce qu'on aurait dû gagner)
                gainsManquesCumules += gainParParis.value;
            }
        }
    }
    
    const gainRealise = capitalActuel - capitalInitial.value;
    toast.add({ 
        severity: gainRealise >= objectifGainGlobal.value ? 'success' : 'info', 
        summary: gainRealise >= objectifGainGlobal.value ? 'Objectif atteint !' : 'Simulation terminée', 
        detail: `Capital initial: ${capitalInitial.value.toFixed(2)}€ - Capital final: ${capitalActuel.toFixed(2)}€ - Gain: ${gainRealise.toFixed(2)}€`, 
        life: 3000 
    });
};






</script>

<template>
    <Fluid>
        <div class="flex flex-col gap-8">
            <!-- En-tête -->
            <Card>
                <template #content>
                    <div class="font-semibold text-2xl mb-4">Simulateur de Martingale</div>
                    <p class="text-surface-600 dark:text-surface-400">
                        Simulez et testez différentes stratégies de martingale pour optimiser vos paris sportifs.
                        Générez des paris aléatoires, créez vos propres martingales et sauvegardez vos stratégies.
                    </p>
                </template>
            </Card>

            <!-- Onglets pour les différentes sections -->
            <Card>
                <template #content>
                    <div class="flex flex-col gap-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="font-medium">Nombre de paris max</label>
                                <InputText v-model="nombreParis" type="number" min="1" max="50" placeholder="10" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-medium">Objectif de gain global (€)</label>
                                <InputText v-model="objectifGainGlobal" type="number" step="0.01" min="10" max="10000" placeholder="100.00" @input="(event) => { let value = event.target.value; if (typeof value === 'string' && value.includes(',')) { value = value.replace(',', '.'); } const numericValue = parseFloat(value); objectifGainGlobal = isNaN(numericValue) ? null : numericValue; }" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-medium">Gain par paris (€)</label>
                                <InputText v-model="gainParParis" type="number" step="0.01" min="1" max="1000" placeholder="10.00" @input="(event) => { let value = event.target.value; if (typeof value === 'string' && value.includes(',')) { value = value.replace(',', '.'); } const numericValue = parseFloat(value); gainParParis = isNaN(numericValue) ? null : numericValue; }" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-medium">Capital initial (€)</label>
                                <InputText v-model="capitalInitial" type="number" step="0.01" min="100" max="100000" placeholder="1000.00" @input="(event) => { let value = event.target.value; if (typeof value === 'string' && value.includes(',')) { value = value.replace(',', '.'); } const numericValue = parseFloat(value); capitalInitial = isNaN(numericValue) ? null : numericValue; }" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-medium">Cote minimum</label>
                                <InputText v-model="coteMin" type="number" step="0.01" min="1.01" max="10" placeholder="1.50" @input="(event) => { let value = event.target.value; if (typeof value === 'string' && value.includes(',')) { value = value.replace(',', '.'); } const numericValue = parseFloat(value); coteMin = isNaN(numericValue) ? null : numericValue; }" />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-medium">Cote maximum</label>
                                <InputText v-model="coteMax" type="number" step="0.01" min="1.01" max="10" placeholder="3.00" @input="(event) => { let value = event.target.value; if (typeof value === 'string' && value.includes(',')) { value = value.replace(',', '.'); } const numericValue = parseFloat(value); coteMax = isNaN(numericValue) ? null : numericValue; }" />
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <Checkbox v-model="inclureGainsManques" inputId="inclureGainsManques" :binary="true" />
                            <label for="inclureGainsManques" class="font-medium">Inclure les gains manqués</label>
                        </div>
                        
                        <Button @click="genererParisAleatoires" label="Générer des paris aléatoires" icon="pi pi-refresh" />
                        
                        <!-- Affichage avec Cards en pleine largeur -->
                         <div v-if="parisAleatoires.length > 0" class="mt-4 space-y-4">
                            <Card v-for="pari in parisAleatoires" :key="pari.id" class="border border-surface-200 dark:border-surface-700">
                                <template #content>
                                    <div class="space-y-3">
                                        <!-- En-tête de la carte -->
                                        <div class="flex justify-between items-center pb-2 border-b border-surface-200 dark:border-surface-700">
                                            <span class="font-semibold text-lg">Pari #{{ pari.id }}</span>
                                            <span :class="pari.resultat === resultLabels.WIN ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'" 
                                  class="px-2 py-1 rounded-full text-xs font-medium">
                                {{ pari.resultat }}
                            </span>
                                        </div>
                                        
                                        <!-- Informations principales -->
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="flex flex-col">
                                                <span class="text-xs text-surface-600 dark:text-surface-400 uppercase tracking-wide">Cote</span>
                                                <span class="font-medium text-sm">{{ pari.cote }}</span>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-xs text-surface-600 dark:text-surface-400 uppercase tracking-wide">Mise réelle</span>
                                                <span class="font-medium text-sm">{{ pari.mise }}€</span>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-xs text-surface-600 dark:text-surface-400 uppercase tracking-wide">Mise nécessaire</span>
                                                <span class="font-medium text-sm text-blue-600">{{ pari.miseNecessaire }}€</span>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-xs text-surface-600 dark:text-surface-400 uppercase tracking-wide">Gain/Perte</span>
                                                <span :class="parseFloat(pari.gain) >= 0 ? 'text-green-600' : 'text-red-600'" class="font-medium text-sm">
                                                    {{ pari.gain }}€
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- Informations secondaires -->
                                        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-surface-100 dark:border-surface-800">
                                            <div class="flex flex-col">
                                                <span class="text-xs text-surface-600 dark:text-surface-400 uppercase tracking-wide">Capital</span>
                                                <span :class="parseFloat(pari.capitalEvolution) >= 0 ? 'text-green-600' : 'text-red-600'" class="font-bold text-sm">
                                                    {{ pari.capitalEvolution }}€
                                                </span>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-xs text-surface-600 dark:text-surface-400 uppercase tracking-wide">Série pertes</span>
                                                <span v-if="pari.seriePertes > 0" class="text-orange-600 font-medium text-sm">
                                                    {{ pari.seriePertes }}
                                                </span>
                                                <span v-else class="text-surface-400 text-sm">-</span>
                                            </div>
                                            <div v-if="inclureGainsManques && pari.resultat === resultLabels.LOST" class="flex flex-col col-span-2">
                                                <span class="text-xs text-surface-600 dark:text-surface-400 uppercase tracking-wide">Gains manqués</span>
                                                <span v-if="parseFloat(pari.gainsManquesCumules) > 0" class="text-blue-600 font-medium text-sm">
                                                    {{ pari.gainsManquesCumules }}€
                                                </span>
                                                <span v-else class="text-surface-400 text-sm">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </Card>
                        </div>
                    </div>
                </template>
            </Card>
        </div>
        

        
        <Toast />
    </Fluid>
</template>

<style scoped>
/* Styles personnalisés si nécessaire */
</style>