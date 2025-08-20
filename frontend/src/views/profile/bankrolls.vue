<template>
    <Toast />
    <Fluid>
        <div class="flex flex-col gap-8">
            <!-- En-tête de la page -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Mes Bankrolls</h1>
                    <p class="text-gray-600 dark:text-gray-300 mt-2">Gérez vos bankrolls et suivez vos performances</p>
                </div>
                <Button 
                    label="Ajouter une bankroll" 
                    icon="pi pi-plus" 
                    @click="openBankrollDialog()" 
                    class="w-full md:w-auto"
                />
            </div>

            <!-- Composant des bankrolls -->
            <div class="bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-lg">
                <div class="p-6">
                    <BankrollCard 
                        :bankrolls="bankrolls" 
                        :api-base-url="apiBaseUrl"
                        :loading="loading"
                        :error="error"
                        @edit-bankroll="openBankrollDialog"
                        @delete-bankroll="deleteBankroll"
                        @create-bankroll="openBankrollDialog"
                        @reload-bankrolls="loadBankrolls"
                    />
                </div>
            </div>
        </div>
    </Fluid>

    <!-- Dialog pour ajouter/modifier une bankroll -->
    <Dialog v-model:visible="showBankrollDialog" :header="editingBankroll ? 'Modifier la bankroll' : 'Ajouter une bankroll'" :modal="true" class="w-full max-w-2xl">
        <div class="flex flex-col gap-4">
            <div>
                <label class="block text-sm font-medium mb-2">Nom de la bankroll *</label>
                <InputText v-model="newBankroll.name" placeholder="Ex: Bankroll Principale" class="w-full" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">Description</label>
                <Textarea v-model="newBankroll.description" placeholder="Description de votre bankroll" class="w-full" rows="3" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">Montant de départ *</label>
                <InputNumber v-model="newBankroll.startAmount" mode="currency" currency="EUR" locale="fr-FR" class="w-full" />
            </div>
        </div>
        
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Annuler" severity="secondary" @click="closeBankrollDialog" />
                <Button :label="editingBankroll ? 'Modifier' : 'Créer'" @click="saveBankroll" :loading="saving" />
            </div>
        </template>
    </Dialog>

    <!-- Dialog de confirmation de suppression -->
    <Dialog v-model:visible="showDeleteDialog" header="Confirmer la suppression" :modal="true" class="w-full max-w-md">
        <div class="flex items-center gap-4">
            <i class="pi pi-exclamation-triangle text-orange-500 text-2xl"></i>
            <div>
                <p class="font-medium">Êtes-vous sûr de vouloir supprimer cette bankroll ?</p>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Cette action est irréversible.</p>
            </div>
        </div>
        
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Annuler" severity="secondary" @click="showDeleteDialog = false" />
                <Button label="Supprimer" severity="danger" @click="confirmDeleteBankroll" :loading="deleting" />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import Toast from 'primevue/toast'
import Fluid from 'primevue/fluid'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import BankrollCard from '@/components/BankrollCard.vue'
import { BankrollService } from '@/service/BankrollService.js'

// Configuration de l'API
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'https://api.auxotracker.lan'
const toast = useToast()

// États réactifs
const bankrolls = ref([])
const loading = ref(false)
const error = ref(null)
const saving = ref(false)
const deleting = ref(false)

// États des dialogs
const showBankrollDialog = ref(false)
const showDeleteDialog = ref(false)
const editingBankroll = ref(null)
const bankrollToDelete = ref(null)

// Formulaire de bankroll
const newBankroll = ref({
    name: '',
    description: '',
    startAmount: 0
})

/**
 * Charge la liste des bankrolls depuis l'API
 */
const loadBankrolls = async () => {
    loading.value = true
    error.value = null
    
    try {
        const response = await BankrollService.getBankrolls()
        bankrolls.value = response || []
    } catch (err) {
        console.error('Erreur lors du chargement des bankrolls:', err)
        error.value = 'Impossible de charger les bankrolls. Veuillez réessayer.'
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Impossible de charger les bankrolls',
            life: 3000
        })
    } finally {
        loading.value = false
    }
}

/**
 * Ouvre le dialog pour créer ou modifier une bankroll
 * @param {Object|null} bankroll - Bankroll à modifier (null pour création)
 */
const openBankrollDialog = (bankroll = null) => {
    editingBankroll.value = bankroll
    
    if (bankroll) {
        // Mode édition
        newBankroll.value = {
            name: bankroll.bankroll_name || bankroll.name || '',
            description: bankroll.bankroll_description || bankroll.comment || '',
            startAmount: parseFloat(bankroll.bankroll_start_amount) || 0
        }
    } else {
        // Mode création
        newBankroll.value = {
            name: '',
            description: '',
            startAmount: 0
        }
    }
    
    showBankrollDialog.value = true
}

/**
 * Ferme le dialog de bankroll
 */
const closeBankrollDialog = () => {
    showBankrollDialog.value = false
    editingBankroll.value = null
    newBankroll.value = {
        name: '',
        description: '',
        startAmount: 0
    }
}

/**
 * Sauvegarde une bankroll (création ou modification)
 */
const saveBankroll = async () => {
    if (!newBankroll.value.name || newBankroll.value.startAmount <= 0) {
        toast.add({
            severity: 'warn',
            summary: 'Attention',
            detail: 'Veuillez remplir tous les champs obligatoires',
            life: 3000
        })
        return
    }
    
    saving.value = true
    
    try {
        const bankrollData = {
            bankroll_name: newBankroll.value.name,
            bankroll_description: newBankroll.value.description,
            bankroll_start_amount: newBankroll.value.startAmount
        }
        
        if (editingBankroll.value) {
            // Modification
            await BankrollService.updateBankroll(editingBankroll.value.id, bankrollData)
            toast.add({
                severity: 'success',
                summary: 'Succès',
                detail: 'Bankroll modifiée avec succès',
                life: 3000
            })
        } else {
            // Création
            await BankrollService.createBankroll(bankrollData)
            toast.add({
                severity: 'success',
                summary: 'Succès',
                detail: 'Bankroll créée avec succès',
                life: 3000
            })
        }
        
        closeBankrollDialog()
        await loadBankrolls()
    } catch (err) {
        console.error('Erreur lors de la sauvegarde:', err)
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Impossible de sauvegarder la bankroll',
            life: 3000
        })
    } finally {
        saving.value = false
    }
}

/**
 * Ouvre le dialog de confirmation de suppression
 * @param {Object} bankroll - Bankroll à supprimer
 */
const deleteBankroll = (bankroll) => {
    bankrollToDelete.value = bankroll
    showDeleteDialog.value = true
}

/**
 * Confirme et exécute la suppression d'une bankroll
 */
const confirmDeleteBankroll = async () => {
    if (!bankrollToDelete.value) return
    
    deleting.value = true
    
    try {
        await BankrollService.deleteBankroll(bankrollToDelete.value.id)
        toast.add({
            severity: 'success',
            summary: 'Succès',
            detail: 'Bankroll supprimée avec succès',
            life: 3000
        })
        
        showDeleteDialog.value = false
        bankrollToDelete.value = null
        await loadBankrolls()
    } catch (err) {
        console.error('Erreur lors de la suppression:', err)
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Impossible de supprimer la bankroll',
            life: 3000
        })
    } finally {
        deleting.value = false
    }
}

// Chargement initial des données
onMounted(() => {
    loadBankrolls()
})
</script>

<style scoped>
/* Styles spécifiques à la vue si nécessaire */
</style>