<script setup>
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Divider from 'primevue/divider';
import Fluid from 'primevue/fluid';
import Tooltip from 'primevue/tooltip';
import Badge from 'primevue/badge';
import InputText from 'primevue/inputtext';

// Enregistrement de la directive tooltip
const vTooltip = Tooltip;

// États réactifs pour le TRJ avec 2 cotes
const cote1_2 = ref(1.5);
const cote2_2 = ref(3.5);

// États réactifs pour le TRJ avec 3 cotes
const cote1_3 = ref(1.5);
const cote2_3 = ref(2.5);
const cote3_3 = ref(3.5);

/**
 * Gère le remplacement automatique des virgules par des points pour les cotes
 */
const handleCoteInput = (event, coteRef) => {
    const value = event.target.value;
    if (typeof value === 'string' && value.includes(',')) {
        const correctedValue = value.replace(',', '.');
        coteRef.value = parseFloat(correctedValue) || null;
    }
};

/**
 * Calcule le TRJ pour 2 cotes
 */
const trj2Cotes = computed(() => {
    if (!cote1_2.value || !cote2_2.value) return 0;
    const trj = ((1 / cote1_2.value) + (1 / cote2_2.value)) * 100;
    return trj.toFixed(2);
});

/**
 * Calcule les probabilités pour 2 cotes
 */
const probabilite1_2 = computed(() => {
    if (!cote1_2.value) return 0;
    return ((1 / cote1_2.value) * 100).toFixed(2);
});

const probabilite2_2 = computed(() => {
    if (!cote2_2.value) return 0;
    return ((1 / cote2_2.value) * 100).toFixed(2);
});

/**
 * Calcule le TRJ pour 3 cotes
 */
const trj3Cotes = computed(() => {
    if (!cote1_3.value || !cote2_3.value || !cote3_3.value) return 0;
    const trj = ((1 / cote1_3.value) + (1 / cote2_3.value) + (1 / cote3_3.value)) * 100;
    return trj.toFixed(2);
});

/**
 * Calcule les probabilités pour 3 cotes
 */
const probabilite1_3 = computed(() => {
    if (!cote1_3.value) return 0;
    return ((1 / cote1_3.value) * 100).toFixed(2);
});

const probabilite2_3 = computed(() => {
    if (!cote2_3.value) return 0;
    return ((1 / cote2_3.value) * 100).toFixed(2);
});

const probabilite3_3 = computed(() => {
    if (!cote3_3.value) return 0;
    return ((1 / cote3_3.value) * 100).toFixed(2);
});

/**
 * Calcule la marge du bookmaker pour 2 cotes
 */
const margeBookmaker2 = computed(() => {
    if (!cote1_2.value || !cote2_2.value) return 0;
    const marge = parseFloat(trj2Cotes.value) - 100;
    return Math.abs(marge).toFixed(2);
});

/**
 * Calcule la marge du bookmaker pour 3 cotes
 */
const margeBookmaker3 = computed(() => {
    if (!cote1_3.value || !cote2_3.value || !cote3_3.value) return 0;
    const marge = parseFloat(trj3Cotes.value) - 100;
    return Math.abs(marge).toFixed(2);
});

/**
 * Détermine si le TRJ indique une value bet (2 cotes)
 */
const isValueBet2 = computed(() => {
    return parseFloat(trj2Cotes.value) > 100;
});

/**
 * Détermine si le TRJ indique une value bet (3 cotes)
 */
const isValueBet3 = computed(() => {
    return parseFloat(trj3Cotes.value) > 100;
});

/**
 * Retourne la classe CSS pour le TRJ selon sa valeur (2 cotes)
 */
const trjClass2 = computed(() => {
    const trj = parseFloat(trj2Cotes.value);
    if (trj > 100) return 'text-green-600 dark:text-green-400';
    if (trj > 95) return 'text-orange-600 dark:text-orange-400';
    return 'text-red-600 dark:text-red-400';
});

/**
 * Retourne la classe CSS pour le TRJ selon sa valeur (3 cotes)
 */
const trjClass3 = computed(() => {
    const trj = parseFloat(trj3Cotes.value);
    if (trj > 100) return 'text-green-600 dark:text-green-400';
    if (trj > 95) return 'text-orange-600 dark:text-orange-400';
    return 'text-red-600 dark:text-red-400';
});

/**
 * Réinitialise les champs du TRJ 2 cotes
 */
const resetTrj2Cotes = () => {
    cote1_2.value = 1.5;
    cote2_2.value = 3.5;
};

/**
 * Réinitialise les champs du TRJ 3 cotes
 */
const resetTrj3Cotes = () => {
    cote1_3.value = 1.5;
    cote2_3.value = 2.5;
    cote3_3.value = 3.5;
};
</script>

<template>
    <Fluid>
        <div class="flex flex-col gap-8">
            <!-- Explication du TRJ -->
            <Card>
                <template #title>
                    <span>Calcul Taux de Retour Joueur (TRJ)</span>
                </template>
                <template #content>
                    <div class="space-y-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-3">Qu'est-ce que le TRJ ?</h4>
                            <p class="text-blue-700 dark:text-blue-300 mb-3">
                                Le Taux de Retour Joueur (TRJ) est un indicateur qui mesure la marge du bookmaker sur un marché donné. 
                                Il se calcule en additionnant les probabilités implicites de toutes les issues possibles.
                            </p>
                            <div class="bg-white dark:bg-surface-800 rounded p-3 mb-3">
                                <strong class="text-blue-800 dark:text-blue-200">Formule :</strong>
                                <code class="ml-2 text-sm bg-surface-100 dark:bg-surface-700 px-2 py-1 rounded">
                                    TRJ = (1/Cote1 + 1/Cote2 + ... + 1/CoteN) × 100
                                </code>
                            </div>
                            <div class="space-y-2">
                                <h5 class="font-medium text-blue-800 dark:text-blue-200">Interprétation :</h5>
                                <ul class="space-y-1 text-sm text-blue-700 dark:text-blue-300">
                                    <li><span class="text-green-600 dark:text-green-400 font-medium">TRJ > 100% :</span> Value bet potentielle (cotes surévaluées)</li>
                                    <li><span class="text-orange-600 dark:text-orange-400 font-medium">95% < TRJ ≤ 100% :</span> Marge acceptable du bookmaker</li>
                                    <li><span class="text-red-600 dark:text-red-400 font-medium">TRJ ≤ 95% :</span> Marge élevée du bookmaker</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Section TRJ avec 2 cotes -->
            <Card>
                <template #title>
                    <div class="flex justify-between items-center">
                        <span>Avec 2 cotes</span>
                        <Button 
                            icon="pi pi-refresh" 
                            severity="secondary" 
                            text 
                            @click="resetTrj2Cotes"
                            v-tooltip="'Réinitialiser'"
                        />
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="flex flex-col gap-2">
                            <label for="cote1_2">Cote 1</label>
                            <InputText
                                id="cote1_2"
                                v-model="cote1_2"
                                type="text"
                                inputmode="decimal"
                                placeholder="1.00"
                                @input="(event) => {
                                    let value = event.target.value;
                                    if (typeof value === 'string' && value.includes(',')) {
                                        value = value.replace(',', '.');
                                    }
                                    cote1_2 = parseFloat(value) || null;
                                }"
                                class="w-full p-inputtext-sm"
                            />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="cote2_2">Cote 2</label>
                            <InputText
                                id="cote2_2"
                                v-model="cote2_2"
                                type="text"
                                inputmode="decimal"
                                placeholder="1.00"
                                @input="(event) => {
                                    let value = event.target.value;
                                    if (typeof value === 'string' && value.includes(',')) {
                                        value = value.replace(',', '.');
                                    }
                                    cote2_2 = parseFloat(value) || null;
                                }"
                                class="w-full p-inputtext-sm"
                            />
                        </div>
                    </div>
                    
                    <!-- Résultats -->
                    <div class="bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-lg p-4">
                        <h4 class="text-lg font-semibold mb-4">Résultats</h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="flex flex-col gap-2">
                                <label>TRJ</label>
                                <div class="flex items-center gap-2">
                                    <InputText
                                        :value="parseFloat(trj2Cotes).toFixed(2) + '%'"
                                        type="text"
                                        readonly
                                        :class="['w-full p-inputtext-sm', trjClass2]"
                                    />
                                    <Badge v-if="isValueBet2" value="VALUE" severity="success" />
                                </div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label>Marge Bookmaker</label>
                                <InputText
                                    :value="parseFloat(margeBookmaker2).toFixed(2) + '%'"
                                    type="text"
                                    readonly
                                    class="w-full p-inputtext-sm"
                                />
                            </div>
                        </div>
                        
                        <Divider />
                        
                        <h5 class="font-medium mb-3">Probabilités implicites</h5>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-2">
                                <label>Probabilité 1</label>
                                <InputText
                                    :value="parseFloat(probabilite1_2).toFixed(2) + '%'"
                                    type="text"
                                    readonly
                                    class="w-full p-inputtext-sm"
                                />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label>Probabilité 2</label>
                                <InputText
                                    :value="parseFloat(probabilite2_2).toFixed(2) + '%'"
                                    type="text"
                                    readonly
                                    class="w-full p-inputtext-sm"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Section TRJ avec 3 cotes -->
            <Card>
                <template #title>
                    <div class="flex justify-between items-center">
                        <span>Avec 3 cotes</span>
                        <Button 
                            icon="pi pi-refresh" 
                            severity="secondary" 
                            text 
                            @click="resetTrj3Cotes"
                            v-tooltip="'Réinitialiser'"
                        />
                    </div>
                </template>
                <template #content>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="flex flex-col gap-2">
                            <label for="cote1_3">Cote 1</label>
                            <InputText
                                id="cote1_3"
                                v-model="cote1_3"
                                type="text"
                                inputmode="decimal"
                                placeholder="1.00"
                                @input="(event) => {
                                    let value = event.target.value;
                                    if (typeof value === 'string' && value.includes(',')) {
                                        value = value.replace(',', '.');
                                    }
                                    cote1_3 = parseFloat(value) || null;
                                }"
                                class="w-full p-inputtext-sm"
                            />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="cote2_3">Cote 2</label>
                            <InputText
                                id="cote2_3"
                                v-model="cote2_3"
                                type="text"
                                inputmode="decimal"
                                placeholder="1.00"
                                @input="(event) => {
                                    let value = event.target.value;
                                    if (typeof value === 'string' && value.includes(',')) {
                                        value = value.replace(',', '.');
                                    }
                                    cote2_3 = parseFloat(value) || null;
                                }"
                                class="w-full p-inputtext-sm"
                            />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="cote3_3">Cote 3</label>
                            <InputText
                                id="cote3_3"
                                v-model="cote3_3"
                                type="text"
                                inputmode="decimal"
                                placeholder="1.00"
                                @input="(event) => {
                                    let value = event.target.value;
                                    if (typeof value === 'string' && value.includes(',')) {
                                        value = value.replace(',', '.');
                                    }
                                    cote3_3 = parseFloat(value) || null;
                                }"
                                class="w-full p-inputtext-sm"
                            />
                        </div>
                    </div>
                    
                    <!-- Résultats -->
                    <div class="bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-lg p-4">
                        <h4 class="text-lg font-semibold mb-4">Résultats</h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="flex flex-col gap-2">
                                <label>TRJ</label>
                                <div class="flex items-center gap-2">
                                    <InputText
                                        :value="parseFloat(trj3Cotes).toFixed(2) + '%'"
                                        type="text"
                                        readonly
                                        :class="['w-full p-inputtext-sm', trjClass3]"
                                    />
                                    <Badge v-if="isValueBet3" value="VALUE" severity="success" />
                                </div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label>Marge Bookmaker</label>
                                <InputText
                                    :value="parseFloat(margeBookmaker3).toFixed(2) + '%'"
                                    type="text"
                                    readonly
                                    class="w-full p-inputtext-sm"
                                />
                            </div>
                        </div>
                        
                        <Divider />
                        
                        <h5 class="font-medium mb-3">Probabilités implicites</h5>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="flex flex-col gap-2">
                                <label>Probabilité 1</label>
                                <InputText
                                    :value="parseFloat(probabilite1_3).toFixed(2) + '%'"
                                    type="text"
                                    readonly
                                    class="w-full p-inputtext-sm"
                                />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label>Probabilité 2</label>
                                <InputText
                                    :value="parseFloat(probabilite2_3).toFixed(2) + '%'"
                                    type="text"
                                    readonly
                                    class="w-full p-inputtext-sm"
                                />
                            </div>
                            <div class="flex flex-col gap-2">
                                <label>Probabilité 3</label>
                                <InputText
                                    :value="parseFloat(probabilite3_3).toFixed(2) + '%'"
                                    type="text"
                                    readonly
                                    class="w-full p-inputtext-sm"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Exemple concret -->
            <Card>
                <template #title>
                    <span>Exemple concret</span>
                </template>
                <template #content>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
                        <h4 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200 mb-3">Match de football 1N2</h4>
                        <div class="space-y-3 text-yellow-700 dark:text-yellow-300">
                            <p><strong>Cotes proposées :</strong> Victoire domicile: 2.10, Match nul: 3.40, Victoire extérieur: 3.20</p>
                            <p><strong>Calcul des probabilités :</strong></p>
                            <ul class="ml-4 space-y-1">
                                <li>• Victoire domicile: 1/2.10 = 47.62%</li>
                                <li>• Match nul: 1/3.40 = 29.41%</li>
                                <li>• Victoire extérieur: 1/3.20 = 31.25%</li>
                            </ul>
                            <p><strong>TRJ :</strong> 47.62% + 29.41% + 31.25% = 108.28%</p>
                            <p><strong>Marge du bookmaker :</strong> 108.28% - 100% = 8.28%</p>
                            <p class="text-sm italic">Cette marge de 8.28% indique que le bookmaker prélève environ 8% sur ce marché.</p>
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