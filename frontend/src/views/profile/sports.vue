<script setup>
import { ref, onMounted, computed } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Checkbox from 'primevue/checkbox';
import DataView from 'primevue/dataview';
import Skeleton from 'primevue/skeleton';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { SportService } from '@/service/SportService';
import { UserService } from '@/service/UserService';
import Sortable from 'sortablejs';

const toast = useToast();

// États réactifs
const loading = ref(false);
const saving = ref(false);
const allSports = ref([]);
const userSports = ref([]);
const favoriteSports = ref([]);

// Référence pour le conteneur de tri
const favoriteSportsContainer = ref(null);
let sortableInstance = null;

/**
 * Charge tous les sports avec les préférences utilisateur depuis l'API
 */
const loadSports = async () => {
    loading.value = true;
    try {
        const response = await UserService.getUserSportsPreferences();
        allSports.value = response.data.map(sport => ({
            id: sport.id,
            name: sport.name,
            slug: sport.slug,
            img: sport.img,
            description: sport.description,
            selected: sport.is_favorite,
            order: sport.sort_order
        }));
        
        // Mettre à jour les sports favoris triés
        updateFavoriteSports();
    } catch (error) {
        console.error('Erreur lors du chargement des sports:', error);
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Impossible de charger les sports',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

/**
 * Charge les préférences sports de l'utilisateur (méthode dépréciée)
 */
const loadUserSportsPreferences = async () => {
    // Cette méthode n'est plus nécessaire car loadSports charge déjà les préférences
    // Conservée pour compatibilité
};

/**
 * Met à jour la liste des sports favoris triés
 */
const updateFavoriteSports = () => {
    favoriteSports.value = allSports.value
        .filter(sport => sport.selected)
        .sort((a, b) => a.order - b.order);
};

/**
 * Gère la sélection/désélection d'un sport
 */
const toggleSport = (sport) => {
    sport.selected = !sport.selected;
    
    if (sport.selected) {
        // Ajouter le sport avec un ordre par défaut
        sport.order = favoriteSports.value.length;
    } else {
        // Retirer le sport et réorganiser les ordres
        sport.order = 0;
        reorderSports();
    }
    
    updateFavoriteSports();
    initializeSortable();
};

/**
 * Réorganise les ordres des sports après suppression
 */
const reorderSports = () => {
    const selectedSports = allSports.value.filter(sport => sport.selected);
    selectedSports.forEach((sport, index) => {
        sport.order = index;
    });
};

/**
 * Initialise le système de glisser-déposer
 */
const initializeSortable = () => {
    if (sortableInstance) {
        sortableInstance.destroy();
    }
    
    if (favoriteSportsContainer.value && favoriteSports.value.length > 0) {
        sortableInstance = Sortable.create(favoriteSportsContainer.value, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: (evt) => {
                const oldIndex = evt.oldIndex;
                const newIndex = evt.newIndex;
                
                if (oldIndex !== newIndex) {
                    // Réorganiser l'ordre des sports
                    const movedSport = favoriteSports.value[oldIndex];
                    favoriteSports.value.splice(oldIndex, 1);
                    favoriteSports.value.splice(newIndex, 0, movedSport);
                    
                    // Mettre à jour les ordres
                    favoriteSports.value.forEach((sport, index) => {
                        sport.order = index;
                    });
                }
            }
        });
    }
};

/**
 * Sauvegarde les préférences sports de l'utilisateur
 */
const saveSportsPreferences = async () => {
    saving.value = true;
    try {
        const sportsPreferences = allSports.value.map(sport => ({
            sport_id: sport.id,
            is_favorite: sport.selected,
            sort_order: sport.selected ? sport.order : 999
        }));
        
        await UserService.updateUserSportsPreferences(sportsPreferences);
        
        toast.add({
            severity: 'success',
            summary: 'Succès',
            detail: 'Préférences sports sauvegardées avec succès',
            life: 3000
        });
    } catch (error) {
        console.error('Erreur lors de la sauvegarde:', error);
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Impossible de sauvegarder les préférences',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

// Sports disponibles (non sélectionnés)
const availableSports = computed(() => {
    return allSports.value.filter(sport => !sport.selected);
});

// Fonction d'initialisation asynchrone
async function initializeSports() {
    await loadSports();
    await loadUserSportsPreferences();
    initializeSortable();
}

// Initialisation au montage du composant
onMounted(() => {
    initializeSports();
});
</script>

<template>
    <div class="sports-preferences">
        <Toast />
        
        <!-- En-tête -->
        <div class="mb-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-surface-900 dark:text-surface-0 mb-2">
                        Préférences Sports
                    </h2>
                    <p class="text-surface-600 dark:text-surface-400">
                        Sélectionnez vos sports d'intérêt et triez-les par ordre de préférence
                    </p>
                </div>
                <Button 
                    label="Sauvegarder" 
                    icon="pi pi-save" 
                    :loading="saving"
                    @click="saveSportsPreferences"
                    :disabled="loading"
                    class="flex-shrink-0"
                />
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Sports disponibles -->
            <Card class="h-fit">
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-list text-primary"></i>
                        <span>Sports disponibles</span>
                    </div>
                </template>
                
                <template #content>
                    <div v-if="loading" class="space-y-3">
                        <div v-for="n in 6" :key="n" class="flex items-center gap-3">
                            <Skeleton width="1.5rem" height="1.5rem" />
                            <Skeleton width="3rem" height="3rem" class="rounded-lg" />
                            <Skeleton width="8rem" height="1.5rem" />
                        </div>
                    </div>
                    
                    <div v-else-if="availableSports.length === 0" class="text-center py-8">
                        <i class="pi pi-check-circle text-4xl text-green-500 mb-3"></i>
                        <p class="text-surface-600 dark:text-surface-400">
                            Tous les sports sont sélectionnés !
                        </p>
                    </div>
                    
                    <div v-else class="space-y-3">
                        <div 
                            v-for="sport in availableSports" 
                            :key="sport.id"
                            class="flex items-center gap-3 p-3 border border-surface-200 dark:border-surface-700 rounded-lg hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors cursor-pointer"
                            @click="toggleSport(sport)"
                        >
                            <Checkbox 
                                v-model="sport.selected" 
                                :binary="true"
                                @change="toggleSport(sport)"
                            />
                            <img 
                                v-if="sport.img" 
                                :src="`http://api.auxotracker.lan/storage/sport_icons/${sport.img}`" 
                                :alt="sport.name"
                                class="w-5 h-5 object-contain rounded-lg filter dark:brightness-0 dark:invert"
                            />
                            <div v-else class="w-8 h-8 bg-surface-200 dark:bg-surface-700 rounded-lg flex items-center justify-center">
                                <i class="pi pi-image text-surface-400"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-surface-900 dark:text-surface-0">
                                    {{ sport.name }}
                                </h4>
                                <p v-if="sport.description" class="text-sm text-surface-600 dark:text-surface-400">
                                    {{ sport.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Sports favoris -->
            <Card class="h-fit">
                <template #title>
                    <div class="flex items-center gap-2">
                        <i class="pi pi-star text-yellow-500"></i>
                        <span>Vos sports favoris</span>
                        <span v-if="favoriteSports.length > 0" class="text-sm text-surface-500">
                            ({{ favoriteSports.length }})
                        </span>
                    </div>
                </template>
                
                <template #content>
                    <div v-if="favoriteSports.length === 0" class="text-center py-8">
                        <i class="pi pi-star-fill text-4xl text-surface-300 dark:text-surface-600 mb-3"></i>
                        <p class="text-surface-600 dark:text-surface-400 mb-2">
                            Aucun sport sélectionné
                        </p>
                        <p class="text-sm text-surface-500 dark:text-surface-500">
                            Sélectionnez des sports dans la liste de gauche
                        </p>
                    </div>
                    
                    <div v-else>
                        <p class="text-sm text-surface-600 dark:text-surface-400 mb-4">
                            <i class="pi pi-arrows-v mr-1"></i>
                            Glissez-déposez pour réorganiser par ordre de préférence
                        </p>
                        
                        <div ref="favoriteSportsContainer" class="space-y-3">
                            <div 
                                v-for="(sport, index) in favoriteSports" 
                                :key="sport.id"
                                class="flex items-center gap-3 p-3 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 rounded-lg cursor-move hover:bg-primary-100 dark:hover:bg-primary-900/30 transition-colors"
                            >
                                <div class="flex items-center justify-center w-6 h-6 bg-primary-500 text-white rounded-full text-sm font-bold">
                                    {{ index + 1 }}
                                </div>
                                <i class="pi pi-bars text-surface-400 cursor-grab"></i>
                                <img 
                                    v-if="sport.img" 
                                    :src="`http://api.auxotracker.lan/storage/sport_icons/${sport.img}`" 
                                    :alt="sport.name"
                                    class="w-5 h-5 object-contain rounded-lg filter dark:brightness-0 dark:invert"
                                />
                                <div v-else class="w-8 h-8 bg-surface-200 dark:bg-surface-700 rounded-lg flex items-center justify-center">
                                    <i class="pi pi-image text-surface-400"></i>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-surface-900 dark:text-surface-0">
                                        {{ sport.name }}
                                    </h4>
                                </div>
                                <Button 
                                    icon="pi pi-times" 
                                    severity="danger" 
                                    text 
                                    rounded 
                                    size="small"
                                    @click="toggleSport(sport)"
                                    class="hover:bg-red-100 dark:hover:bg-red-900/20"
                                />
                            </div>
                        </div>
                    </div>
                </template>
            </Card>
        </div>


    </div>
</template>

<style scoped>
.sports-preferences {
    padding: 1.5rem;
}

/* Styles pour le glisser-déposer */
.sortable-ghost {
    opacity: 0.4;
}

.sortable-chosen {
    transform: scale(1.02);
}

.sortable-drag {
    transform: rotate(5deg);
}

/* Animation pour les transitions */
.sports-preferences .transition-colors {
    transition: background-color 0.2s ease, border-color 0.2s ease;
}
</style>