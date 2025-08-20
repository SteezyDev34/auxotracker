<script setup>
import { ref } from 'vue';
import Card from 'primevue/card';
import Fluid from 'primevue/fluid';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';

// Import des composants calculateurs
import RembourseSiNul from '@/components/calculators/RembourseSiNul.vue';
import DoubleChance from '@/components/calculators/DoubleChance.vue';
import TauxRetourJoueur from '@/components/calculators/TauxRetourJoueur.vue';
import Dutching from '@/components/calculators/Dutching.vue';

// Onglet actif par défaut
const activeTab = ref(0);
</script>

<template>
    <Fluid>
        <div class="flex flex-col gap-8">
            <!-- En-tête -->
            <Card>
                <template #content>
                    <div class="font-semibold text-2xl mb-4">Mes Outils de Calcul</div>
                    <p class="text-surface-600 dark:text-surface-400">
                        Découvrez nos outils de calcul pour optimiser vos stratégies de paris sportifs.
                        Chaque outil vous aide à prendre des décisions éclairées et à maximiser vos gains.
                    </p>
                </template>
            </Card>

            <!-- Onglets pour les différents calculateurs -->
            <Card>
                <template #content>
                    <TabView v-model:activeIndex="activeTab">
                        <TabPanel header="Remboursé si Nul">
                            <RembourseSiNul />
                        </TabPanel>
                        
                        <TabPanel header="Double Chance">
                            <DoubleChance />
                        </TabPanel>
                        
                        <TabPanel header="Taux de Retour Joueur">
                            <TauxRetourJoueur />
                        </TabPanel>
                        
                        <TabPanel header="Dutching">
                            <Dutching />
                        </TabPanel>
                    </TabView>
                </template>
            </Card>
        </div>
    </Fluid>
</template>

<style scoped>
.card {
    @apply bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-lg p-6;
}
</style>