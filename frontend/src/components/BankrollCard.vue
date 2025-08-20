<template>
    <!-- Indicateur de chargement -->
    <div v-if="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-2 text-gray-600 dark:text-gray-300">Chargement des bankrolls...</span>
    </div>
    
    <!-- Message d'erreur -->
    <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
        <div class="flex items-center">
            <i class="pi pi-exclamation-triangle text-red-600 dark:text-red-400 mr-2"></i>
            <span class="text-red-700 dark:text-red-300">{{ error }}</span>
            <Button label="Réessayer" class="p-button-sm p-button-text ml-auto" @click="$emit('reload-bankrolls')" />
        </div>
    </div>
    
    <!-- Message si aucune bankroll -->
    <div v-else-if="bankrolls.length === 0" class="text-center text-gray-500 py-8">
        Aucune bankroll configurée. Cliquez sur "Ajouter une bankroll" pour commencer.
    </div>
    
    <!-- Liste des bankrolls -->
    <DataView v-else :value="bankrolls" layout="list">
        <template #list="slotProps">
            <div class="flex flex-col">
                <div v-for="(bankroll, index) in slotProps.items" :key="bankroll.id">
                    <div class="flex flex-col sm:flex-row sm:items-center p-6 gap-4" :class="{ 'border-t border-surface': index !== 0 }">
                        <div class="md:w-40 flex flex-col items-center justify-center gap-2">
                            <div class="text-xs text-surface-500">Capital actuel</div>
                            <Tag :value="getTotalAmount(bankroll) + '€'" :severity="getBankrollSeverity(bankroll)" class="text-lg font-bold px-4 py-2"></Tag>
                        </div>
                        <div class="flex flex-col md:flex-row justify-between md:items-center flex-1 gap-6">
                            <div class="flex flex-col gap-4 flex-1">
                                <div>
                                    <span class="font-medium text-surface-500 dark:text-surface-400 text-sm">Bankroll</span>
                                    <div class="text-lg font-medium mt-2">{{ bankroll.bankroll_name || bankroll.name }}</div>
                                    <p v-if="bankroll.bankroll_description || bankroll.comment" class="text-gray-600 text-sm mt-1">{{ bankroll.bankroll_description || bankroll.comment }}</p>
                                </div>
                                
                                <!-- Composant des bookmakers -->
                                <BookmakersList 
                                    :user-bookmakers="bankroll.user_bookmakers" 
                                    :api-base-url="apiBaseUrl" 
                                />
                            
                            </div>
                            <div class="flex flex-col md:items-end gap-4">
                                <div class="flex items-center gap-4">
                        
                                    <div class="text-right">
                                        <div class="text-xs text-surface-500">Montant de départ</div>
                                        <span class="text-xs font-semibold">{{ bankroll.bankroll_start_amount || 0 }}€</span>
                                    </div>
                                </div>
                                <div class="flex flex-row-reverse md:flex-row gap-2">
                                    <Button @click="$emit('edit-bankroll', bankroll)" icon="pi pi-pencil" outlined></Button>
                                    <Button @click="$emit('delete-bankroll', bankroll)" icon="pi pi-trash" outlined severity="danger"></Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template #grid="slotProps">
            <div class="grid grid-cols-12 gap-4">
                <div v-for="bankroll in slotProps.items" :key="bankroll.id" class="col-span-12 sm:col-span-6 lg:col-span-4 p-2">
                    <div class="p-6 border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 rounded flex flex-col">
                        <div class="bg-surface-50 flex justify-center rounded p-4 relative">
                            <div class="bg-primary-100 rounded-full p-6">
                                <i class="pi pi-wallet text-primary-600 text-3xl"></i>
                            </div>
                            <Tag :value="getTotalAmount(bankroll) + '€'" :severity="getBankrollSeverity(bankroll)" class="absolute dark:!bg-surface-900" style="left: 4px; top: 4px"></Tag>
                        </div>
                        <div class="pt-6">
                            <div class="flex flex-row justify-between items-start gap-2">
                                <div>
                                    <span class="font-medium text-surface-500 dark:text-surface-400 text-sm">Bankroll</span>
                                    <div class="text-lg font-medium mt-1">{{ bankroll.bankroll_name || bankroll.name }}</div>
                                    <p v-if="bankroll.bankroll_description || bankroll.comment" class="text-gray-600 text-sm mt-1">{{ bankroll.bankroll_description || bankroll.comment }}</p>
                                </div>
                                <div class="bg-surface-100 p-1" style="border-radius: 30px">
                                    <div class="bg-surface-0 flex items-center gap-2 justify-center py-1 px-2" style="border-radius: 30px; box-shadow: 0px 1px 2px 0px rgba(0, 0, 0, 0.04), 0px 1px 2px 0px rgba(0, 0, 0, 0.06);">
                                        <span class="text-surface-900 font-medium text-sm">{{ bankroll.user_bookmakers?.length || 0 }}</span>
                                        <i class="pi pi-building text-blue-500"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col gap-4 mt-4">
                                <div v-if="bankroll.user_bookmakers && bankroll.user_bookmakers.length > 0">
                                    <h4 class="font-medium text-sm mb-2">Bookmakers :</h4>
                                    <div class="space-y-1">
                                        <div v-for="userBookmaker in bankroll.user_bookmakers" :key="userBookmaker.id" class="flex justify-between text-sm">
                                            <span>{{ userBookmaker.bookmaker?.name || 'Bookmaker' }}</span>
                                             <span class="font-medium">{{ userBookmaker.bookmaker_actual_amount }}€</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-gray-500 text-sm">
                                    Aucun bookmaker configuré
                                </div>
                            </div>
                            <div class="flex flex-col gap-4 mt-6">
                                <span class="text-2xl font-semibold">{{ getTotalAmount(bankroll) }}€</span>
                                <div class="flex gap-2">
                                    <Button @click="$emit('edit-bankroll', bankroll)" icon="pi pi-pencil" label="Modifier" class="flex-auto whitespace-nowrap" outlined></Button>
                                    <Button @click="$emit('delete-bankroll', bankroll)" icon="pi pi-trash" outlined severity="danger"></Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        
        <template #empty>
            <div class="text-center py-8">
                <i class="pi pi-wallet text-4xl text-gray-400 dark:text-gray-600 mb-4"></i>
                <p class="text-gray-500 dark:text-gray-400 mb-4">Aucune bankroll trouvée</p>
                <Button label="Créer ma première bankroll" icon="pi pi-plus" @click="$emit('create-bankroll')" />
            </div>
        </template>
    </DataView>
</template>

<script setup>
import { computed } from 'vue'
import DataView from 'primevue/dataview'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import BookmakersList from '@/components/BookmakersList.vue'

// Props
const props = defineProps({
    bankrolls: {
        type: Array,
        default: () => []
    },
    apiBaseUrl: {
        type: String,
        required: true
    },
    loading: {
        type: Boolean,
        default: false
    },
    error: {
        type: String,
        default: null
    }
})

// Émissions d'événements
const emit = defineEmits(['edit-bankroll', 'delete-bankroll', 'create-bankroll', 'reload-bankrolls'])

/**
 * Calcule le montant total d'une bankroll (montant de départ + bénéfices)
 * @param {Object} bankroll - Objet bankroll
 * @returns {number} Montant total
 */
const getTotalAmount = (bankroll) => {
    const startAmount = parseFloat(bankroll.bankroll_start_amount) || 0
    const benefits = parseFloat(bankroll.bankroll_benefits) || 0
    return (startAmount + benefits).toFixed(2)
}

/**
 * Détermine la sévérité du tag selon le montant de la bankroll
 * @param {Object} bankroll - Objet bankroll
 * @returns {string} Sévérité du tag
 */
const getBankrollSeverity = (bankroll) => {
    const totalAmount = parseFloat(getTotalAmount(bankroll))
    const startAmount = parseFloat(bankroll.bankroll_start_amount) || 0
    
    if (totalAmount > startAmount) {
        return 'success'
    } else if (totalAmount < startAmount) {
        return 'danger'
    } else {
        return 'info'
    }
}
</script>

<style scoped>
/* Styles spécifiques au composant si nécessaire */
</style>