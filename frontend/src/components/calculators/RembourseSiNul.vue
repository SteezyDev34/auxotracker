<script setup>
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Fluid from 'primevue/fluid';
import Tooltip from 'primevue/tooltip';
import InputText from 'primevue/inputtext';

// Enregistrement de la directive tooltip
const vTooltip = Tooltip;

// États réactifs pour le calcul remboursé si nul
const miseGlobale = ref(null);
const cotePrincipale = ref(null);
const coteSecondaire = ref(null);

// Les composants InputNumber de PrimeVue gèrent automatiquement les décimaux

/**
 * Gère le remplacement automatique des virgules par des points pour les cotes
 */
const handleCoteInput = (event, coteRef) => {
    let value = event.target.value;
    if (typeof value === 'string' && value.includes(',')) {
        value = value.replace(',', '.');
    }
    coteRef.value = parseFloat(value) || null;
};

/**
 * Calcule la mise de couverture (sur le nul) pour le remboursé si nul
 */
const miseCouverture = computed(() => {
    if (!miseGlobale.value || !coteSecondaire.value) return 0;
    // Pour récupérer la mise totale en cas de nul: miseCouverture * coteSecondaire = miseGlobale
    const miseCouvertureCalculee = miseGlobale.value / coteSecondaire.value;
    return miseCouvertureCalculee.toFixed(2);
});

/**
 * Calcule la mise principale (sur l'issue souhaitée)
 */
const misePrincipale = computed(() => {
    if (!miseGlobale.value || !miseCouverture.value) return 0;
    const misePrincipaleCalculee = miseGlobale.value - parseFloat(miseCouverture.value);
    return misePrincipaleCalculee.toFixed(2);
});

/**
 * Calcule la mise secondaire (alias pour miseCouverture pour compatibilité)
 */
const miseSecondaire = computed(() => {
    return miseCouverture.value;
});

/**
 * Vérifie si tous les champs requis sont remplis
 */
const calculDisponible = computed(() => {
    return miseGlobale.value && cotePrincipale.value && coteSecondaire.value;
});

/**
 * Calcule le gain si la cote principale gagne
 */
const gainCotePrincipale = computed(() => {
    if (!misePrincipale.value || !cotePrincipale.value) return 0;
    const gain = parseFloat(misePrincipale.value) * cotePrincipale.value;
    return gain.toFixed(2);
});

/**
 * Calcule le gain si la cote de couverture gagne
 */
const gainCoteCouverture = computed(() => {
    if (!miseSecondaire.value || !coteSecondaire.value) return 0;
    const gain = parseFloat(miseSecondaire.value) * coteSecondaire.value;
    return gain.toFixed(2);
});

/**
 * Calcule le bénéfice net si la cote principale gagne
 */
const beneficeCotePrincipale = computed(() => {
    if (!gainCotePrincipale.value || !miseGlobale.value) return 0;
    const benefice = parseFloat(gainCotePrincipale.value) - miseGlobale.value;
    return benefice.toFixed(2);
});

/**
 * Calcule le bénéfice net si la cote de couverture gagne
 */
const beneficeCoteCouverture = computed(() => {
    if (!gainCoteCouverture.value || !miseGlobale.value) return 0;
    const benefice = parseFloat(gainCoteCouverture.value) - miseGlobale.value;
    return benefice.toFixed(2);
});

/**
 * Calcule le pourcentage de retour sur investissement
 */
const roiPourcentage = computed(() => {
    if (!miseGlobale.value || !beneficeCotePrincipale.value) return 0;
    const roi = (parseFloat(beneficeCotePrincipale.value) / miseGlobale.value) * 100;
    return roi.toFixed(2);
});

/**
 * Réinitialise tous les champs du calcul remboursé si nul
 */
const resetRembourseSiNul = () => {
    miseGlobale.value = null;
    cotePrincipale.value = null;
    coteSecondaire.value = null;
};
</script>

<template>
    <Fluid>
        <div class="flex flex-col gap-8">
            <!-- Explication du Remboursé si Nul -->
            <Card>
                <template #title>
                    <span>Stratégie Remboursé si Nul/Victoire</span>
                </template>
                <template #content>
                    <div class="space-y-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-3">Qu'est-ce que le Remboursé si Nul ?</h4>
                            <p class="text-blue-700 dark:text-blue-300 mb-3">
                                Le "Remboursé si Nul" est une stratégie de paris sportifs qui permet de sécuriser un pari en couvrant 
                                le risque de match nul (ou autre issue spécifique). Cette technique garantit soit un bénéfice, 
                                soit un remboursement total de la mise.
                            </p>
                            <div class="bg-white dark:bg-surface-800 rounded p-3 mb-3">
                                <strong class="text-blue-800 dark:text-blue-200">Principe :</strong>
                                <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                    On mise sur l'issue principale (ex: victoire équipe A) et on couvre avec une mise sur 
                                    l'issue de sécurité (ex: match nul) pour récupérer la mise totale en cas de nul.
                                </p>
                            </div>
                            <div class="bg-white dark:bg-surface-800 rounded p-3 mb-3">
                                <strong class="text-blue-800 dark:text-blue-200">Formules de répartition :</strong>
                                <div class="mt-2 space-y-1">
                                    <code class="block text-sm bg-surface-100 dark:bg-surface-700 px-2 py-1 rounded">
                                        Mise couverture = Mise totale ÷ Cote couverture
                                    </code>
                                    <code class="block text-sm bg-surface-100 dark:bg-surface-700 px-2 py-1 rounded">
                                        Mise principale = Mise totale - Mise couverture
                                    </code>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <h5 class="font-medium text-blue-800 dark:text-blue-200">Avantages :</h5>
                                <ul class="space-y-1 text-sm text-blue-700 dark:text-blue-300">
                                    <li><span class="text-green-600 dark:text-green-400 font-medium">• Sécurité :</span> Aucune perte en cas d'issue de couverture</li>
                                    <li><span class="text-green-600 dark:text-green-400 font-medium">• Bénéfice garanti :</span> Gain assuré si l'issue principale se réalise</li>
                                    <li><span class="text-orange-600 dark:text-orange-400 font-medium">• Gestion du risque :</span> Idéal pour les matchs incertains</li>
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
                        <span>Calcul remboursé si nul/victoire</span>
                        <Button 
                            icon="pi pi-refresh" 
                            severity="secondary" 
                            text 
                            @click="resetRembourseSiNul"
                            v-tooltip="'Réinitialiser'"
                        />
                    </div>
                </template>
            <template #content>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="flex flex-col gap-2">
                        <label for="miseGlobale">Mise globale</label>
                        <InputText 
                            id="miseGlobale" 
                            v-model="miseGlobale" 
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="cotePrincipale">Cote principale</label>
                        <InputText 
                            id="cotePrincipale" 
                            v-model="cotePrincipale" 
                            type="number"
                            step="0.01"
                            min="1"
                            placeholder="1.00"
                            @input="(event) => {
                                let value = event.target.value;
                                if (typeof value === 'string' && value.includes(',')) {
                                    value = value.replace(',', '.');
                                }
                                cotePrincipale = parseFloat(value) || null;
                            }"
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="coteSecondaire">Cote de Couverture</label>
                        <InputText 
                            id="coteSecondaire" 
                            v-model="coteSecondaire" 
                            type="number"
                            step="0.01"
                            min="1"
                            placeholder="1.00"
                            @input="(event) => {
                                let value = event.target.value;
                                if (typeof value === 'string' && value.includes(',')) {
                                    value = value.replace(',', '.');
                                }
                                coteSecondaire = parseFloat(value) || null;
                            }"
                        />
                    </div>
                </div>
                
                <!-- Résultats calculés automatiquement -->
                <div v-if="calculDisponible" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4">
                    <h4 class="text-lg font-semibold text-green-800 dark:text-green-200 mb-3">Résultats du calcul</h4>
                    
                    <!-- Répartition des mises -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-green-700 dark:text-green-300">Mise principale</label>
                            <InputText 
                                :value="parseFloat(misePrincipale).toFixed(2) + ' €'" 
                                type="text"
                                readonly
                                class="bg-surface-50 dark:bg-surface-800"
                            />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-orange-700 dark:text-orange-300">Mise de couverture</label>
                            <InputText 
                                :value="parseFloat(miseSecondaire).toFixed(2) + ' €'" 
                                type="text"
                                readonly
                                class="bg-surface-50 dark:bg-surface-800"
                            />
                        </div>
                    </div>

                    <div class="border-t border-green-200 dark:border-green-700 pt-4">
                        <h5 class="text-md font-medium text-green-800 dark:text-green-200 mb-3">Détail des gains par situation</h5>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Si cote principale gagne -->
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                                <h6 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-3">Si cote principale ({{ cotePrincipale }})</h6>
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-blue-700 dark:text-blue-300 text-sm">Gain brut :</span>
                                        <span class="font-medium text-blue-800 dark:text-blue-200">{{ gainCotePrincipale }}€</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-blue-700 dark:text-blue-300 text-sm">Bénéfice net :</span>
                                        <span class="font-medium text-blue-800 dark:text-blue-200">{{ beneficeCotePrincipale }}€</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-blue-700 dark:text-blue-300 text-sm">ROI :</span>
                                        <span class="font-medium text-blue-800 dark:text-blue-200">{{ roiPourcentage }}%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Si cote de couverture gagne -->
                            <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-700 rounded-lg p-4">
                                <h6 class="text-sm font-semibold text-orange-800 dark:text-orange-200 mb-3">Si cote de couverture ({{ coteSecondaire }})</h6>
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-orange-700 dark:text-orange-300 text-sm">Gain brut :</span>
                                        <span class="font-medium text-orange-800 dark:text-orange-200">{{ gainCoteCouverture }}€</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-orange-700 dark:text-orange-300 text-sm">Bénéfice net :</span>
                                        <span class="font-medium text-orange-800 dark:text-orange-200">{{ beneficeCoteCouverture }}€</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-orange-700 dark:text-orange-300 text-sm">ROI :</span>
                                        <span class="font-medium text-orange-800 dark:text-orange-200">0.00%</span>
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