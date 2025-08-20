<script setup>
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Divider from 'primevue/divider';
import Fluid from 'primevue/fluid';
import Tooltip from 'primevue/tooltip';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import InputText from 'primevue/inputtext';

// Enregistrement de la directive tooltip
const vTooltip = Tooltip;

// États réactifs pour le calcul Dutching
const miseGlobaleDutching = ref(null);
const cotesDutching = ref([null, null, null]); // Tableau dynamique de cotes, commence avec 3 cotes vides

// Les composants InputNumber de PrimeVue gèrent automatiquement les décimaux



/**
 * Vérifie si les champs du calcul Dutching sont remplis
 */
const calculDutchingDisponible = computed(() => {
    return miseGlobaleDutching.value && cotesDutching.value.length >= 2 && 
           cotesDutching.value.every(cote => cote !== null && cote > 0);
});

/**
 * Calcule les mises pour chaque sélection (Dutching)
 */
const misesDutching = computed(() => {
    if (!calculDutchingDisponible.value) return [];
    
    const sommeProbabilites = cotesDutching.value.reduce((sum, cote) => sum + (1 / cote), 0);
    
    return cotesDutching.value.map(cote => {
        const mise = (miseGlobaleDutching.value * (1 / cote)) / sommeProbabilites;
        return mise.toFixed(2);
    });
});

/**
 * Calcule le gain net pour chaque sélection (Dutching)
 */
const gainsNetDutching = computed(() => {
    if (!calculDutchingDisponible.value) return [];
    
    return misesDutching.value.map((mise, index) => {
        const gainBrut = parseFloat(mise) * cotesDutching.value[index];
        const gainNet = gainBrut - miseGlobaleDutching.value;
        return gainNet.toFixed(2);
    });
});

/**
 * Calcule le gain net identique pour toutes les sélections (Dutching)
 */
const gainNetDutching = computed(() => {
    if (!calculDutchingDisponible.value || gainsNetDutching.value.length === 0) return 0;
    
    // Le gain net est identique peu importe la sélection qui gagne
    return gainsNetDutching.value[0];
});

/**
 * Calcule le ROI pour le Dutching
 */
const roiDutching = computed(() => {
    if (!calculDutchingDisponible.value || !miseGlobaleDutching.value) return 0;
    const roi = (parseFloat(gainNetDutching.value) / miseGlobaleDutching.value) * 100;
    return roi.toFixed(2);
});

// Fonctions pour gérer les cotes dynamiques
const ajouterCote = () => {
    cotesDutching.value.push(null);
};

const supprimerCote = (index) => {
    if (cotesDutching.value.length > 2) {
        cotesDutching.value.splice(index, 1);
    }
};

/**
 * Réinitialise les champs du calcul Dutching
 */
const resetDutching = () => {
    miseGlobaleDutching.value = null;
    cotesDutching.value = [null, null, null];
};
</script>

<template>
    <Fluid>
        <div class="flex flex-col gap-8">
            <!-- Explication du Dutching -->
            <Card>
                <template #title>
                    <span>Calcul Dutching</span>
                </template>
                <template #content>
                    <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-lg p-4">
                        <h4 class="text-lg font-semibold text-purple-800 dark:text-purple-200 mb-3">Qu'est-ce que le Dutching ?</h4>
                        <p class="text-purple-700 dark:text-purple-300 mb-3">
                            Le Dutching est une stratégie de paris qui permet de répartir votre mise sur plusieurs sélections d'un même événement 
                            pour garantir un gain identique peu importe laquelle gagne.
                        </p>
                        <div class="bg-white dark:bg-surface-800 rounded p-3 mb-3">
                            <strong class="text-purple-800 dark:text-purple-200">Principe :</strong>
                            <p class="text-sm text-purple-700 dark:text-purple-300 mt-1">
                                Les mises sont calculées proportionnellement aux probabilités implicites de chaque cote 
                                pour égaliser le retour sur investissement.
                            </p>
                        </div>
                        <div class="space-y-2">
                            <h5 class="font-medium text-purple-800 dark:text-purple-200">Avantages :</h5>
                            <ul class="space-y-1 text-sm text-purple-700 dark:text-purple-300">
                                <li>• Gain garanti si l'une des sélections gagne</li>
                                <li>• Réduction du risque par diversification</li>
                                <li>• Optimisation mathématique de la répartition</li>
                            </ul>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Section de calcul -->
            <Card>
                <template #title>
                    <div class="flex justify-between items-center">
                        <span>Calculateur Dutching</span>
                        <Button 
                            icon="pi pi-refresh" 
                            severity="secondary" 
                            text 
                            @click="resetDutching"
                            v-tooltip="'Réinitialiser'"
                        />
                    </div>
                </template>
                <template #content>
                    <!-- Gestion dynamique des cotes -->
                    <div class="flex flex-col gap-2 mb-4">
                        <div class="flex justify-between items-center">
                            <label>Cotes ({{ cotesDutching.length }} sélections)</label>
                            <Button 
                                icon="pi pi-plus" 
                                severity="success" 
                                size="small"
                                @click="ajouterCote"
                                v-tooltip="'Ajouter une cote'"
                            />
                        </div>
                    </div>

                    <!-- Champs de saisie -->
                    <div class="flex flex-col gap-4 mb-6">
                        <div class="flex flex-col gap-2">
                            <label for="miseGlobaleDutching">Mise globale</label>
                            <InputText
                                id="miseGlobaleDutching"
                                v-model="miseGlobaleDutching"
                                type="text"
                                inputmode="decimal"
                                placeholder="100.00"
                                class="w-full p-inputtext-sm"
                            />
                        </div>
                        <div v-for="(cote, index) in cotesDutching" :key="index" class="flex flex-col gap-2">
                            <label :for="`coteDutching${index}`">Cote sélection {{ index + 1 }}</label>
                            <InputGroup>
                                <InputText
                                    :id="`coteDutching${index}`"
                                    v-model="cotesDutching[index]"
                                    type="text"
                                    inputmode="decimal"
                                    :placeholder="(2.0 + index * 0.5).toFixed(2)"
                                    class="w-full p-inputtext-sm"
                                    @input="(event) => { let value = event.target.value; if (typeof value === 'string' && value.includes(',')) { value = value.replace(',', '.'); } const numericValue = parseFloat(value); cotesDutching[index] = isNaN(numericValue) ? null : numericValue; }"
                                />
                                <InputGroupAddon v-if="cotesDutching.length > 2">
                                    <Button 
                                        icon="pi pi-trash" 
                                        severity="danger" 
                                        text
                                        size="small"
                                        @click="supprimerCote(index)"
                                        v-tooltip="'Supprimer cette cote'"
                                    />
                                </InputGroupAddon>
                            </InputGroup>
                        </div>
                    </div>

                    <!-- Résultats calculés automatiquement -->
                    <div v-if="calculDutchingDisponible" class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-lg p-4">
                        <h4 class="text-lg font-semibold text-purple-800 dark:text-purple-200 mb-3">Résultats du calcul Dutching</h4>
                        
                        <!-- Répartition des mises -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-4">
                            <h5 class="text-md font-semibold text-blue-800 dark:text-blue-200 mb-3">Répartition optimale des mises</h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div v-for="(mise, index) in misesDutching" :key="index" class="flex justify-between items-center">
                                    <span class="text-blue-700 dark:text-blue-300">Mise sélection {{ index + 1 }} :</span>
                                    <span class="font-medium text-blue-800 dark:text-blue-200">{{ mise }}€</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Détail des gains par sélection -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4 mb-4">
                            <div v-for="(cote, index) in cotesDutching" :key="index" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4">
                                <h6 class="text-sm font-semibold text-green-800 dark:text-green-200 mb-3">Si sélection {{ index + 1 }} gagne ({{ cote }})</h6>
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-green-700 dark:text-green-300 text-sm">Gain brut :</span>
                                        <span class="font-medium text-green-800 dark:text-green-200">{{ (parseFloat(misesDutching[index]) * cote).toFixed(2) }}€</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-green-700 dark:text-green-300 text-sm">Mise totale :</span>
                                        <span class="font-medium text-green-800 dark:text-green-200">-{{ miseGlobaleDutching }}€</span>
                                    </div>
                                    <div class="border-t border-green-200 dark:border-green-600 pt-2">
                                        <div class="flex justify-between items-center">
                                            <span class="text-green-700 dark:text-green-300 font-medium">Gain net :</span>
                                            <span class="font-bold text-green-800 dark:text-green-200">{{ gainNetDutching }}€</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Résumé global -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-700 dark:text-gray-300 font-medium">Gain net garanti :</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ gainNetDutching }}€</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700 dark:text-gray-300 font-medium">ROI (Retour sur investissement) :</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">{{ roiDutching }}%</span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                                Stratégie Dutching : gain identique peu importe la sélection gagnante.
                            </p>
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </Fluid>
</template>

<style scoped>
/* Styles spécifiques au composant si nécessaire */
</style>