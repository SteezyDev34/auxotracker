<template>
    <!-- Affichage détaillé des bookmakers -->
    <div v-if="userBookmakers && userBookmakers.length > 0" class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <span class="font-medium text-surface-500 dark:text-surface-400 text-sm">Bookmakers associés</span>
            <Tag :value="userBookmakers.length + ' bookmaker' + (userBookmakers.length > 1 ? 's' : '')" severity="info" class="text-xs"></Tag>
        </div>
        
        <!-- Liste détaillée des bookmakers -->
        <div class="grid gap-3">
            <div v-for="userBookmaker in userBookmakers" :key="userBookmaker.id" 
                 class="bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-lg p-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <!-- Logo du bookmaker si disponible -->
                        <div v-if="userBookmaker.bookmaker?.bookmaker_img" class="rounded-full flex items-center justify-center" style="width: 40px; height: 40px;">
                            <img 
                                :src="`${apiBaseUrl}/storage/bookmakers_logos/${userBookmaker.bookmaker.bookmaker_img}`" 
                                :alt="userBookmaker.bookmaker.bookmaker_name"
                                class="w-8 h-8 rounded-full object-contain"
                                @error="$event.target.style.display='none'"
                            />
                        </div>
                        <!-- Icône par défaut avec fond si pas de logo -->
                        <div v-else class="bg-primary-100 dark:bg-primary-900 rounded-full p-2 flex items-center justify-center" style="width: 40px; height: 40px;">
                            <i class="pi pi-building text-primary-600 dark:text-primary-400 text-sm"></i>
                        </div>
                        <div>
                            <div class="font-medium text-surface-900 dark:text-surface-100 text-sm">
                                {{ userBookmaker.bookmaker?.bookmaker_name || 'Bookmaker inconnu' }}
                            </div>
                            <div class="text-xs text-surface-600 dark:text-surface-400 mt-1">
                                Créé le {{ formatDate(userBookmaker.created_at) }}
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold text-surface-900 dark:text-surface-100">
                            {{ userBookmaker.bookmaker_actual_amount }}€
                        </div>
                        <div class="text-xs text-surface-600 dark:text-surface-400">
                            Initial: {{ userBookmaker.bookmaker_start_amount }}€
                        </div>
                        <div v-if="userBookmaker.bookmaker_actual_amount !== userBookmaker.bookmaker_start_amount" 
                             class="text-xs mt-1">
                            <span :class="userBookmaker.bookmaker_actual_amount > userBookmaker.bookmaker_start_amount ? 'text-green-600' : 'text-red-600'">
                                {{ userBookmaker.bookmaker_actual_amount > userBookmaker.bookmaker_start_amount ? '+' : '' }}{{ (userBookmaker.bookmaker_actual_amount - userBookmaker.bookmaker_start_amount).toFixed(2) }}€
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Commentaire du bookmaker s'il existe -->
                <div v-if="userBookmaker.bookmaker_comment" class="mt-2 pt-2 border-t border-surface-200 dark:border-surface-600">
                    <p class="text-xs text-surface-600 dark:text-surface-400 italic">
                        "{{ userBookmaker.bookmaker_comment }}"
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Message si aucun bookmaker -->
    <div v-else class="flex flex-col gap-2">
        <span class="font-medium text-surface-500 dark:text-surface-400 text-sm">Bookmakers</span>
        <div class="bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-lg p-3 text-center">
            <i class="pi pi-info-circle text-surface-400 dark:text-surface-500 text-lg mb-2"></i>
            <p class="text-sm text-surface-600 dark:text-surface-400">
                Aucun bookmaker associé à cette bankroll
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import Tag from 'primevue/tag'

// Props
const props = defineProps({
    userBookmakers: {
        type: Array,
        default: () => []
    },
    apiBaseUrl: {
        type: String,
        required: true
    }
})

/**
 * Formate une date au format français
 * @param {string} dateString - Date à formater
 * @returns {string} Date formatée
 */
const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('fr-FR')
}
</script>

<style scoped>
/* Styles spécifiques au composant si nécessaire */
</style>