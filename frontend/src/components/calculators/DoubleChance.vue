<script setup>
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Fluid from 'primevue/fluid';
import Tooltip from 'primevue/tooltip';
import InputText from 'primevue/inputtext';

// Enregistrement de la directive tooltip
const vTooltip = Tooltip;

// États réactifs pour le calcul double chance
const miseGlobaleDoubleChance = ref(null);
const cote1DoubleChance = ref(null);
const cote2DoubleChance = ref(null);

/**
 * Gère le remplacement automatique des virgules par des points pour les cotes
 */
const handleCoteInput = (event, coteRef) => {
    let value = event.target.value;
    if (typeof value === 'string' && value.includes(',')) {
        value = value.replace(',', '.');
    }
    const numericValue = parseFloat(value);
    coteRef.value = isNaN(numericValue) ? null : numericValue;
};

/**
 * Vérifie si les champs du calcul double chance sont remplis
 */
const calculDoubleChanceDisponible = computed(() => {
    return miseGlobaleDoubleChance.value && cote1DoubleChance.value && cote2DoubleChance.value;
});

/**
 * Calcule la mise pour la sélection 1 (double chance)
 */
const miseSelection1DoubleChance = computed(() => {
    // Calcule la mise sur la sélection 1 en répartissant la mise globale de façon à égaliser le retour (dutching)
    if (!miseGlobaleDoubleChance.value || !cote1DoubleChance.value || !cote2DoubleChance.value) return 0;
    const mise1 = (miseGlobaleDoubleChance.value * cote2DoubleChance.value) / (cote1DoubleChance.value + cote2DoubleChance.value);
    return mise1.toFixed(2);
});

/**
 * Calcule la mise pour la sélection 2 (double chance)
 */
const miseSelection2DoubleChance = computed(() => {
    if (!miseGlobaleDoubleChance.value || !miseSelection1DoubleChance.value) return 0;
    const mise2 = miseGlobaleDoubleChance.value - parseFloat(miseSelection1DoubleChance.value);
    return mise2.toFixed(2);
});

/**
 * Calcule la cote totale (double chance)
 */
const coteTotaleDoubleChance = computed(() => {
    // Calcule la cote totale équivalente de la double chance: 1 / (1/cote1 + 1/cote2) = (cote1 * cote2) / (cote1 + cote2)
    if (!cote1DoubleChance.value || !cote2DoubleChance.value) return 0;
    const coteTotal = (cote1DoubleChance.value * cote2DoubleChance.value) / (cote1DoubleChance.value + cote2DoubleChance.value);
    return coteTotal.toFixed(2);
});

/**
 * Calcule le gain si la sélection 1 gagne (double chance)
 */
const gainSelection1DoubleChance = computed(() => {
    if (!miseSelection1DoubleChance.value || !cote1DoubleChance.value) return 0;
    const gain = parseFloat(miseSelection1DoubleChance.value) * cote1DoubleChance.value;
    return gain.toFixed(2);
});

/**
 * Calcule le gain si la sélection 2 gagne (double chance)
 */
const gainSelection2DoubleChance = computed(() => {
    if (!miseSelection2DoubleChance.value || !cote2DoubleChance.value) return 0;
    const gain = parseFloat(miseSelection2DoubleChance.value) * cote2DoubleChance.value;
    return gain.toFixed(2);
});

/**
 * Calcule le bénéfice net si la sélection 1 gagne (double chance)
 */
const beneficeSelection1DoubleChance = computed(() => {
    if (!gainSelection1DoubleChance.value || !miseGlobaleDoubleChance.value) return 0;
    const benefice = parseFloat(gainSelection1DoubleChance.value) - miseGlobaleDoubleChance.value;
    return benefice.toFixed(2);
});

/**
 * Calcule le bénéfice net si la sélection 2 gagne (double chance)
 */
const beneficeSelection2DoubleChance = computed(() => {
    if (!gainSelection2DoubleChance.value || !miseGlobaleDoubleChance.value) return 0;
    const benefice = parseFloat(gainSelection2DoubleChance.value) - miseGlobaleDoubleChance.value;
    return benefice.toFixed(2);
});

/**
 * Calcule le ROI pour le double chance
 */
const roiDoubleChance = computed(() => {
    if (!miseGlobaleDoubleChance.value || !beneficeSelection1DoubleChance.value) return 0;
    const roi = (parseFloat(beneficeSelection1DoubleChance.value) / miseGlobaleDoubleChance.value) * 100;
    return roi.toFixed(2);
});

/**
 * Réinitialise les champs du calcul double chance
 */
const resetDoubleChance = () => {
    miseGlobaleDoubleChance.value = 0;
    cote1DoubleChance.value = 0;
    cote2DoubleChance.value = 0;
};
</script>

<template>
    <Fluid>
        <div class="flex flex-col gap-8">
            <!-- Explication de la Double Chance -->
            <Card>
                <template #title>
                    <span>Stratégie Double Chance</span>
                </template>
                <template #content>
                    <div class="space-y-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-3">Qu'est-ce que la stratégie Double Chance ?</h4>
                            <p class="text-blue-700 dark:text-blue-300 mb-3">
                                La <strong>Double Chance</strong> est une stratégie de paris sportifs qui consiste à parier sur deux sélections différentes 
                                en répartissant intelligemment sa mise pour obtenir le même gain net quel que soit le résultat gagnant.
                            </p>
                            <div class="bg-white dark:bg-surface-800 rounded p-3 mb-3">
                                <strong class="text-blue-800 dark:text-blue-200">Principe :</strong>
                                <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                    On répartit la mise globale de façon proportionnelle aux cotes pour égaliser les gains. 
                                    Cette technique est aussi appelée "Dutching" à deux sélections.
                                </p>
                            </div>
                            <div class="bg-white dark:bg-surface-800 rounded p-3 mb-3">
                                <strong class="text-blue-800 dark:text-blue-200">Formules de répartition :</strong>
                                <div class="mt-2 space-y-1">
                                    <code class="block text-sm bg-surface-100 dark:bg-surface-700 px-2 py-1 rounded">
                                        Mise sélection 1 = (Mise globale × Cote 2) ÷ (Cote 1 + Cote 2)
                                    </code>
                                    <code class="block text-sm bg-surface-100 dark:bg-surface-700 px-2 py-1 rounded">
                                        Mise sélection 2 = Mise globale - Mise sélection 1
                                    </code>
                                    <code class="block text-sm bg-surface-100 dark:bg-surface-700 px-2 py-1 rounded">
                                        Cote équivalente = (Cote 1 × Cote 2) ÷ (Cote 1 + Cote 2)
                                    </code>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <h5 class="font-medium text-blue-800 dark:text-blue-200">Avantages :</h5>
                                <ul class="space-y-1 text-sm text-blue-700 dark:text-blue-300">
                                    <li><span class="text-green-600 dark:text-green-400 font-medium">• Gain garanti :</span> Si l'une des deux sélections est gagnante</li>
                                    <li><span class="text-green-600 dark:text-green-400 font-medium">• Réduction du risque :</span> Par rapport à un pari simple</li>
                                    <li><span class="text-orange-600 dark:text-orange-400 font-medium">• Optimisation :</span> Idéal pour couvrir les favoris dans un événement</li>
                                </ul>
                            </div>
                        </div>
                        
                    
                    </div>
                </template>
            </Card>

            <!-- Calculateur -->
            <Card>
                <template #title>
                    <div class="flex justify-between items-center">
                        <span>Calcul Double Chance</span>
                        <Button 
                            icon="pi pi-refresh" 
                            severity="secondary" 
                            text 
                            @click="resetDoubleChance"
                            v-tooltip="'Réinitialiser'"
                        />
                    </div>
                </template>
                <template #content>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="flex flex-col gap-2">
                        <label for="miseGlobaleDoubleChance">Mise globale</label>
                        <InputText 
                            id="miseGlobaleDoubleChance"
                            v-model="miseGlobaleDoubleChance" 
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="cote1DoubleChance">Cote sélection 1</label>
                        <InputText 
                            id="cote1DoubleChance"
                            v-model="cote1DoubleChance" 
                            type="number"
                            step="0.01"
                            min="1"
                            placeholder="1.00"
                            @input="(event) => { let value = event.target.value; if (typeof value === 'string' && value.includes(',')) { value = value.replace(',', '.'); } const numericValue = parseFloat(value); cote1DoubleChance = isNaN(numericValue) ? null : numericValue; }"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="cote2DoubleChance">Cote sélection 2</label>
                        <InputText 
                            id="cote2DoubleChance"
                            v-model="cote2DoubleChance" 
                            type="number"
                            step="0.01"
                            min="1"
                            placeholder="1.00"
                            @input="(event) => { let value = event.target.value; if (typeof value === 'string' && value.includes(',')) { value = value.replace(',', '.'); } const numericValue = parseFloat(value); cote2DoubleChance = isNaN(numericValue) ? null : numericValue; }"
                        />
                    </div>
                </div>
                
                <!-- Résultats calculés automatiquement -->
                <div v-if="calculDoubleChanceDisponible" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                    <h4 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-3">Résultats du calcul</h4>
                    
                    <!-- Cote totale équivalente -->
                    <div class="mb-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-blue-700 dark:text-blue-300">Cote totale équivalente</label>
                            <InputText 
                                :value="parseFloat(coteTotaleDoubleChance).toFixed(2)" 
                                type="number"
                                step="0.01"
                                readonly
                                class="bg-surface-50 dark:bg-surface-800"
                            />
                        </div>
                    </div>
                    
                    <!-- Répartition des mises -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-blue-700 dark:text-blue-300">Mise sélection 1</label>
                            <InputText 
                                :value="parseFloat(miseSelection1DoubleChance).toFixed(2) + ' €'" 
                                type="text"
                                readonly
                                class="bg-surface-50 dark:bg-surface-800"
                            />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-blue-700 dark:text-blue-300">Mise sélection 2</label>
                            <InputText 
                                :value="parseFloat(miseSelection2DoubleChance).toFixed(2) + ' €'" 
                                type="text"
                                readonly
                                class="bg-surface-50 dark:bg-surface-800"
                            />
                        </div>
                    </div>

                    <div class="border-t border-blue-200 dark:border-blue-700 pt-4">
                        <h5 class="text-md font-medium text-blue-800 dark:text-blue-200 mb-3">Détail des gains par situation</h5>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Si sélection 1 gagne -->
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4">
                                <h6 class="text-sm font-semibold text-green-800 dark:text-green-200 mb-3">Si sélection 1 gagne ({{ cote1DoubleChance }})</h6>
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-green-700 dark:text-green-300 text-sm">Gain brut :</span>
                                        <span class="font-medium text-green-800 dark:text-green-200">{{ gainSelection1DoubleChance }}€</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-green-700 dark:text-green-300 text-sm">Bénéfice net :</span>
                                        <span class="font-medium text-green-800 dark:text-green-200">{{ beneficeSelection1DoubleChance }}€</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-green-700 dark:text-green-300 text-sm">ROI :</span>
                                        <span class="font-medium text-green-800 dark:text-green-200">{{ roiDoubleChance }}%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Si sélection 2 gagne -->
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4">
                                <h6 class="text-sm font-semibold text-green-800 dark:text-green-200 mb-3">Si sélection 2 gagne ({{ cote2DoubleChance }})</h6>
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-green-700 dark:text-green-300 text-sm">Gain brut :</span>
                                        <span class="font-medium text-green-800 dark:text-green-200">{{ gainSelection2DoubleChance }}€</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-green-700 dark:text-green-300 text-sm">Bénéfice net :</span>
                                        <span class="font-medium text-green-800 dark:text-green-200">{{ beneficeSelection2DoubleChance }}€</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-green-700 dark:text-green-300 text-sm">ROI :</span>
                                        <span class="font-medium text-green-800 dark:text-green-200">{{ roiDoubleChance }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
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