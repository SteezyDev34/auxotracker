<template>
    <Toast />
    <Fluid>
        <div class="flex flex-col gap-8">
            <!-- En-tête de la page -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Mes Tipsters</h1>
                    <p class="text-gray-600 dark:text-gray-300 mt-2">Gérez vos tipsters favoris et suivez leurs performances</p>
                </div>
                <Button 
                    label="Ajouter un tipster" 
                    icon="pi pi-plus" 
                    @click="showTipsterDialog = true" 
                    class="w-full md:w-auto"
                />
            </div>

            <!-- Composant des tipsters -->
            <div class="bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-lg">
                <div class="p-6">

                <!-- Message d'état -->
                <div v-if="loading" class="text-center p-4">
                    <ProgressSpinner />
                    <p class="mt-2">Chargement des tipsters...</p>
                </div>

                <div v-else-if="error" class="p-4">
                    <Message severity="error" :closable="false">
                        {{ error }}
                    </Message>
                </div>

                <!-- Liste des tipsters -->
                <div v-else-if="tipsters.length === 0" class="text-center p-4">
                    <i class="pi pi-users" style="font-size: 3rem; color: var(--surface-400)"></i>
                    <h6 class="mt-3 mb-2">Aucun tipster trouvé</h6>
                    <p class="text-surface-600 dark:text-surface-200">Commencez par ajouter votre premier tipster.</p>
                </div>

                <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div v-for="tipster in tipsters" :key="tipster.id" class="col-span-1">
                        <div class="card border-1 surface-border h-full p-4">
                            <div class="flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="m-0 text-900 mb-1">{{ tipster.name }}</h6>
                                    <span class="text-600 text-sm">{{ formatDate(tipster.created_at) }}</span>
                                </div>
                                <div class="flex gap-1">
                                    <Button 
                                        icon="pi pi-pencil" 
                                        class="p-button-rounded p-button-text p-button-sm"
                                        @click="editTipster(tipster)"
                                        v-tooltip.top="'Modifier'"
                                    />
                                    <Button 
                                        icon="pi pi-trash" 
                                        class="p-button-rounded p-button-text p-button-sm p-button-danger"
                                        @click="confirmDeleteTipster(tipster)"
                                        v-tooltip.top="'Supprimer'"
                                    />
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <p class="text-600 text-sm mb-2" v-if="tipster.description">{{ tipster.description }}</p>
                                <div v-if="tipster.link" class="flex align-items-center gap-2">
                                    <i class="pi pi-link text-primary text-xs"></i>
                                    <a :href="tipster.link" target="_blank" class="text-primary text-sm no-underline hover:underline">
                                        Voir le profil
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </Fluid>

    <!-- Dialog d'ajout/modification -->
    <Dialog 
        v-model:visible="showTipsterDialog" 
        :style="{ width: '450px' }" 
        header="Tipster" 
        :modal="true" 
        class="p-fluid"
    >
        <div class="field">
            <label for="name" class="block mb-2">Nom du tipster *</label>
            <InputText 
                id="name" 
                v-model.trim="newTipster.name" 
                required="true" 
                autofocus 
                :class="{ 'p-invalid': submitted && !newTipster.name }"
                placeholder="Ex: ProTipster123"
                class="w-full"
            />
            <small class="p-invalid" v-if="submitted && !newTipster.name">Le nom est requis.</small>
        </div>
        
        <div class="field">
            <label for="link" class="block mb-2">Lien</label>
            <InputText 
                id="link" 
                v-model.trim="newTipster.link" 
                placeholder="https://exemple.com/tipster"
                class="w-full"
            />
        </div>
        
        <div class="field">
            <label for="description" class="block mb-2">Description</label>
            <Textarea 
                id="description" 
                v-model="newTipster.description" 
                rows="3" 
                placeholder="Description optionnelle du tipster..."
                class="w-full"
            />
        </div>
        
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Annuler" severity="secondary" @click="hideDialog" :disabled="saving" />
                <Button :label="editingTipster ? 'Modifier' : 'Ajouter'" @click="saveTipster" :loading="saving" />
            </div>
        </template>
    </Dialog>

    <!-- Dialog de confirmation de suppression -->
    <Dialog 
        v-model:visible="showDeleteDialog" 
        :style="{ width: '450px' }" 
        header="Confirmer la suppression" 
        :modal="true"
    >
        <div class="flex align-items-center justify-content-center">
            <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
            <span v-if="tipsterToDelete">
                Êtes-vous sûr de vouloir supprimer le tipster <b>{{ tipsterToDelete.name }}</b> ?
            </span>
        </div>
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Annuler" severity="secondary" @click="showDeleteDialog = false" :disabled="deleting" />
                <Button label="Supprimer" severity="danger" @click="deleteTipster" :loading="deleting" />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import TipsterService from '@/service/TipsterService';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';
import Fluid from 'primevue/fluid';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

import ProgressSpinner from 'primevue/progressspinner';
import Message from 'primevue/message';
import Tooltip from 'primevue/tooltip';

// Enregistrement de la directive tooltip
const vTooltip = Tooltip;

const toast = useToast();

// États réactifs
const tipsters = ref([]);
const loading = ref(false);
const error = ref(null);
const saving = ref(false);
const deleting = ref(false);
const submitted = ref(false);

// États des dialogues
const showTipsterDialog = ref(false);
const showDeleteDialog = ref(false);
const editingTipster = ref(null);
const tipsterToDelete = ref(null);

// Formulaire de tipster
const newTipster = ref({
    name: '',
    link: '',
    description: ''
});

/**
 * Charge la liste des tipsters depuis l'API
 */
const loadTipsters = async () => {
    try {
        loading.value = true;
        error.value = null;
        const response = await TipsterService.getTipsters();
        tipsters.value = response || [];
    } catch (err) {
        console.error('Erreur lors du chargement des tipsters:', err);
        error.value = 'Erreur lors du chargement des tipsters';
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Impossible de charger les tipsters',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

/**
 * Sauvegarde un tipster (création ou modification)
 */
const saveTipster = async () => {
    submitted.value = true;
    
    if (!newTipster.value.name) {
        return;
    }
    
    try {
        saving.value = true;
        
        if (editingTipster.value) {
            // Modification
            await TipsterService.updateTipster(editingTipster.value.id, newTipster.value);
            toast.add({
                severity: 'success',
                summary: 'Succès',
                detail: 'Tipster modifié avec succès',
                life: 3000
            });
        } else {
            // Création
            await TipsterService.createTipster(newTipster.value);
            toast.add({
                severity: 'success',
                summary: 'Succès',
                detail: 'Tipster ajouté avec succès',
                life: 3000
            });
        }
        
        hideDialog();
        await loadTipsters();
    } catch (err) {
        console.error('Erreur lors de la sauvegarde:', err);
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Erreur lors de la sauvegarde du tipster',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

/**
 * Prépare l'édition d'un tipster
 */
const editTipster = (tipster) => {
    editingTipster.value = { ...tipster };
    newTipster.value = {
        name: tipster.name,
        link: tipster.link || '',
        description: tipster.description || ''
    };
    showTipsterDialog.value = true;
};

/**
 * Confirme la suppression d'un tipster
 */
const confirmDeleteTipster = (tipster) => {
    tipsterToDelete.value = tipster;
    showDeleteDialog.value = true;
};

/**
 * Supprime un tipster
 */
const deleteTipster = async () => {
    try {
        deleting.value = true;
        await TipsterService.deleteTipster(tipsterToDelete.value.id);
        
        toast.add({
            severity: 'success',
            summary: 'Succès',
            detail: 'Tipster supprimé avec succès',
            life: 3000
        });
        
        showDeleteDialog.value = false;
        await loadTipsters();
    } catch (err) {
        console.error('Erreur lors de la suppression:', err);
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Erreur lors de la suppression du tipster',
            life: 3000
        });
    } finally {
        deleting.value = false;
    }
};

/**
 * Ferme le dialogue et remet à zéro le formulaire
 */
const hideDialog = () => {
    showTipsterDialog.value = false;
    submitted.value = false;
    editingTipster.value = null;
    newTipster.value = {
        name: '',
        link: '',
        description: ''
    };
};

/**
 * Formate une date
 */
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

// Chargement initial
onMounted(() => {
    loadTipsters();
});
</script>

<style scoped>
.card {
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.no-underline {
    text-decoration: none;
}

.hover\:underline:hover {
    text-decoration: underline;
}
</style>