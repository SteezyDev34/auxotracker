<script setup>
import { ref, onMounted } from 'vue';
import settingsData from '@/data/settings.json';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Fluid from 'primevue/fluid'
import Menu from 'primevue/menu';
import Toast from 'primevue/toast';
import Skeleton from 'primevue/skeleton';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import InputNumber from 'primevue/inputnumber';
import Dialog from 'primevue/dialog';
import DataView from 'primevue/dataview';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { UserService } from '@/service/UserService';
import { BankrollService } from '@/service/BankrollService.js';
import { BookmakerService } from '@/service/BookmakerService.js';
import BookmakersList from '@/components/BookmakersList.vue';
import BankrollCard from '@/components/BankrollCard.vue';

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || 'https://api.auxotracker.lan';
const toast = useToast();
const subscriptionOptions = ref(settingsData.subscriptionOptions);
const selectedOption = ref(null);

// Initialisation du profil utilisateur avec des valeurs par défaut
const userProfile = ref({
    avatar: '',
    firstName: '',
    lastName: '',
    email: '',
    username: '',
    level: 'Débutant',
    id: null
});

// Fonction pour récupérer les informations de l'utilisateur depuis l'API
async function fetchUserInfo() {
    try {
        const userData = await UserService.getUserInfo();
        userProfile.value = {
            ...userProfile.value,
            id: userData.id,
            email: userData.email,
            username: userData.username,
            firstName: userData.user_firstname || '',
            lastName: userData.user_lastname || '',
            level: userData.user_level || 'Débutant',
            avatar: userData.user_profile_picture || '',
            // Ajouter les paramètres utilisateur
            user_language: userData.user_language,
            user_currency: userData.user_currency,
            user_welcome_page: userData.user_welcome_page,
            user_timezone: userData.user_timezone,
            user_sort_bets_by: userData.user_sort_bets_by,
            user_display_dashboard: userData.user_display_dashboard,
            user_duplicate_bet_date: userData.user_duplicate_bet_date
        };
        // Mettre à jour l'URL de l'avatar après avoir récupéré les données
        updateAvatarUrl();
        // Charger les paramètres utilisateur après avoir récupéré les données
        loadUserSettings(userProfile.value);
    } catch (error) {
        console.error('Erreur lors de la récupération des informations utilisateur:', error);
    }
}

// Référence réactive pour l'URL de l'avatar
const avatarUrl = ref('');

// Fonction pour mettre à jour l'URL de l'avatar
const updateAvatarUrl = () => {
    // Si l'utilisateur a un avatar personnalisé, l'utiliser
    if (userProfile.value.avatar) {
        // Vérifier si l'avatar est déjà une URL complète
        if (userProfile.value.avatar.startsWith('http')) {
            avatarUrl.value = userProfile.value.avatar;
        } else {
            // Construire l'URL complète avec l'URL de l'API
            avatarUrl.value = `http://api.auxotracker.lan/storage/avatar/${userProfile.value.avatar}`;
        }
    } else {
        // Avatar par défaut
        avatarUrl.value = `http://api.auxotracker.lan/storage/avatar/user.jpg`;
    }
};

// Fonction pour obtenir l'URL de l'avatar (utilisée dans le template)
const getAvatarUrl = () => {
    // Ne retourner une valeur que si les informations utilisateur ont été chargées
    if (userProfile.value.id) {
        // S'assurer que l'URL de l'avatar est définie
        if (!avatarUrl.value && userProfile.value.avatar) {
            updateAvatarUrl();
        }
        return avatarUrl.value || `http://api.auxotracker.lan/storage/avatar/user.jpg`;
    }
    // Retourner null si les informations utilisateur ne sont pas encore chargées
    return null;
};

// Fonction d'initialisation asynchrone
async function initializeProfile() {
    await fetchUserInfo();
    // Mettre à jour l'URL de l'avatar après avoir récupéré les données
    updateAvatarUrl();
    // Charger les bankrolls
    await loadBankrolls();
    // Charger les bookmakers disponibles
    await loadAvailableBookmakers();
}

// Charger les informations utilisateur au montage du composant
onMounted(() => {
    initializeProfile();
});

const languageOptions = ref(settingsData.languageOptions);
const selectedLanguage = ref(settingsData.defaultLanguage);

const currencyOptions = ref(settingsData.currencyOptions);
const selectedCurrency = ref(settingsData.defaultCurrency);

const homepageOptions = ref([
    { name: 'Gestion des paris', slug: 'bet-management' },
    { name: 'Trouver mon pari', slug: 'find-my-bet' },
    { name: 'Tableau de bord', slug: 'dashboard' },
    { name: 'Scores en direct', slug: 'live-scores' },
    { name: 'Profil', slug: 'profile' },
    ...settingsData.homepageOptions
]);
const selectedHomepage = ref(settingsData.defaultHomepage);

const timezoneOptions = ref(settingsData.timezoneOptions);
const selectedTimezone = ref(settingsData.defaultTimezone);

const displayBetViewOptions = ref(settingsData.displayBetViewOptions);
const selectedDisplayBetView = ref(settingsData.defaultDisplayBetView);

const displayDashboardOptions = ref(settingsData.displayDashboardOptions);
const selectedDisplayDashboard = ref(settingsData.defaultDisplayDashboard);

const duplicateBetDateOptions = ref(settingsData.duplicateBetDateOptions);
const selectedDuplicateBetDate = ref(settingsData.defaultDuplicateBetDate);

// État pour indiquer si les paramètres sont en cours de sauvegarde
const isSavingSettings = ref(false);

// Gestion des bankrolls
const bankrolls = ref([]);
const loading = ref(false);
const error = ref(null);
const showBankrollDialog = ref(false);
const editingBankroll = ref(null);
const originalBookmakers = ref([]); // Pour stocker les bookmakers originaux lors de l'édition
const newBankroll = ref({
    name: '',
    comment: '',
    bookmakers: []
});

/**
 * Charge les bankrolls depuis l'API
 */
const loadBankrolls = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        const data = await BankrollService.getBankrolls();
        bankrolls.value = data || [];
    } catch (err) {
        error.value = 'Erreur lors du chargement des bankrolls';
        console.error('Erreur lors du chargement des bankrolls:', err);
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Impossible de charger les bankrolls',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Options d'affichage pour les bankrolls (fixé en mode liste)
const bankrollLayout = ref('list');

// Liste des bookmakers disponibles
const availableBookmakers = ref([]);

// Fonction pour charger les bookmakers disponibles
async function loadAvailableBookmakers() {
    try {
        const bookmakers = await BookmakerService.getBookmakers();
        console.log('Bookmakers récupérés depuis l\'API:', bookmakers);
        availableBookmakers.value = bookmakers.map(bookmaker => ({
            id: bookmaker.id,
            name: bookmaker.bookmaker_name,
            slug: bookmaker.bookmaker_name.toLowerCase().replace(/\s+/g, '-')
        }));
        console.log('Bookmakers disponibles formatés:', availableBookmakers.value);
    } catch (error) {
        console.error('Erreur lors du chargement des bookmakers:', error);
        toast.add({ 
            severity: 'error', 
            summary: 'Erreur', 
            detail: 'Impossible de charger les bookmakers', 
            life: 3000 
        });
    }
}

// Fonction pour charger les paramètres utilisateur depuis les données récupérées
function loadUserSettings(userData) {
    if (userData.user_language) {
        selectedLanguage.value = userData.user_language;
    }
    if (userData.user_currency) {
        selectedCurrency.value = userData.user_currency;
    }
    if (userData.user_welcome_page) {
        selectedHomepage.value = userData.user_welcome_page;
    }
    if (userData.user_timezone) {
        selectedTimezone.value = userData.user_timezone;
    }
    if (userData.user_sort_bets_by) {
        selectedDisplayBetView.value = userData.user_sort_bets_by;
    }
    if (userData.user_display_dashboard) {
        selectedDisplayDashboard.value = userData.user_display_dashboard;
    }
    if (userData.user_duplicate_bet_date) {
        selectedDuplicateBetDate.value = userData.user_duplicate_bet_date;
    }
}

// Fonction pour sauvegarder les paramètres utilisateur
async function saveUserSettings() {
    isSavingSettings.value = true;
    
    try {
        const settingsData = {
            user_language: selectedLanguage.value,
            user_currency: selectedCurrency.value,
            user_welcome_page: selectedHomepage.value,
            user_timezone: selectedTimezone.value,
            user_sort_bets_by: selectedDisplayBetView.value,
            user_display_dashboard: selectedDisplayDashboard.value,
            user_duplicate_bet_date: selectedDuplicateBetDate.value
        };
        
        const data = await UserService.updateUserSettings(settingsData);
        
        if (data.success) {
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Paramètres mis à jour avec succès', life: 3000 });
            // Mettre à jour les informations utilisateur si nécessaire
            if (data.user) {
                userProfile.value = {
                    ...userProfile.value,
                    ...data.user
                };
            }
        } else {
            toast.add({ severity: 'error', summary: 'Erreur', detail: data.message || 'Échec de la mise à jour des paramètres', life: 3000 });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error.message || 'Erreur lors de la mise à jour des paramètres', life: 3000 });
        console.error('Erreur lors de la mise à jour des paramètres:', error);
    } finally {
        isSavingSettings.value = false;
    }
}

const menu = ref();
const menuItems = [
    {
        label: 'Modifier l\'avatar',
        icon: 'pi pi-pencil',
        command: () => handleEditAvatar()
    },
    {
        label: 'Supprimer l\'avatar',
        icon: 'pi pi-trash',
        command: () => handleDeleteAvatar()
    }
];

function showMenu(event) {
    menu.value.toggle(event);
    document.addEventListener('click', hideMenuOnClickOutside);
}

function hideMenuOnClickOutside(e) {
    if (!menu.value.$el.contains(e.target) && e.target !== document.getElementById('avatarImg')) {
        menu.value.hide();
        document.removeEventListener('click', hideMenuOnClickOutside);
    }
}

function handleEditAvatar() {
    // Déclenche l'ouverture du gestionnaire de fichiers
    document.getElementById('avatarInput').click();
}

async function handleDeleteAvatar() {
    try {
        const data = await UserService.deleteAvatar();
        
        if (data.success) {
            userProfile.value.avatar = '';
            // Rafraîchir les informations utilisateur
            await fetchUserInfo();
            // Mettre à jour l'URL de l'avatar
            updateAvatarUrl();
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Avatar supprimé avec succès', life: 3000 });
        } else {
            toast.add({ severity: 'error', summary: 'Erreur', detail: data.message || 'Échec de la suppression de l\'avatar', life: 3000 });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error.message || 'Erreur lors de la suppression de l\'avatar', life: 3000 });
        console.error('Erreur lors de la suppression de l\'avatar:', error);
    }
}

async function onAvatarSelected(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    try {
        toast.add({ severity: 'info', summary: 'En cours', detail: 'Téléchargement de l\'avatar en cours...', life: 2000 });
        
        const data = await UserService.updateAvatar(file);
        
        if (data.success) {
            userProfile.value.avatar = data.avatar_url;
            // Rafraîchir les informations utilisateur pour s'assurer que tout est à jour
            await fetchUserInfo();
            // Mettre à jour l'URL de l'avatar
            updateAvatarUrl();
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Avatar mis à jour avec succès', life: 3000 });
        } else {
            toast.add({ severity: 'error', summary: 'Erreur', detail: data.message || 'Échec de l\'upload d\'avatar', life: 3000 });
        }
    } catch (error) {
        // Gérer l'erreur d'upload
        toast.add({ severity: 'error', summary: 'Erreur', detail: error.message || 'Erreur lors du téléchargement de l\'avatar', life: 3000 });
        console.error('Erreur upload avatar:', error);
    }
}

function onAvatarError(e) {
    e.target.src = `http://api.auxotracker.lan/storage/avatar/user.jpg`;
}

// Fonctions pour la gestion des bankrolls
async function openBankrollDialog(bankroll = null) {
    if (bankroll) {
        editingBankroll.value = bankroll;
        
        // Charger les bookmakers existants pour cette bankroll
        let existingBookmakers = [];
        try {
            const bankrollBookmakers = await BankrollService.getBankrollBookmakers(bankroll.id);
            console.log('Bookmakers existants pour la bankroll:', bankrollBookmakers);
            
            // Stocker les bookmakers originaux pour détecter les suppressions
            originalBookmakers.value = bankrollBookmakers.map(userBookmaker => ({
                id: userBookmaker.id,
                bookmakers_id: userBookmaker.bookmakers_id
            }));
            
            // Formater les bookmakers existants pour le formulaire
            existingBookmakers = bankrollBookmakers.map(userBookmaker => {
                // Trouver le bookmaker correspondant dans la liste disponible
                const availableBookmaker = availableBookmakers.value.find(bm => bm.id === userBookmaker.bookmakers_id);
                return {
                    bookmaker: availableBookmaker ? availableBookmaker.slug : '',
                    initialAmount: parseFloat(userBookmaker.bookmaker_start_amount) || 0,
                    userBookmakerId: userBookmaker.id // Garder l'ID pour les mises à jour
                };
            });
            console.log('Bookmakers formatés pour le formulaire:', existingBookmakers);
        } catch (error) {
            console.error('Erreur lors du chargement des bookmakers existants:', error);
            toast.add({
                severity: 'error',
                summary: 'Erreur',
                detail: 'Impossible de charger les bookmakers existants',
                life: 3000
            });
        }
        
        newBankroll.value = {
            name: bankroll.bankroll_name || bankroll.name || '',
            comment: bankroll.bankroll_description || bankroll.comment || '',
            bookmakers: existingBookmakers
        };
    } else {
        editingBankroll.value = null;
        newBankroll.value = {
            name: '',
            comment: '',
            bookmakers: []
        };
    }
    showBankrollDialog.value = true;
}

function closeBankrollDialog() {
    showBankrollDialog.value = false;
    editingBankroll.value = null;
    originalBookmakers.value = []; // Réinitialiser les bookmakers originaux
    newBankroll.value = {
        name: '',
        comment: '',
        bookmakers: []
    };
}

function addBookmakerToBankroll() {
    newBankroll.value.bookmakers.push({
        bookmaker: '',
        initialAmount: 0
    });
}

function removeBookmakerFromBankroll(index) {
    newBankroll.value.bookmakers.splice(index, 1);
}

async function saveBankroll() {
    if (!newBankroll.value.name.trim()) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Le nom de la bankroll est requis', life: 3000 });
        return;
    }
    
    // Calculer le montant total initial basé sur les bookmakers
    const totalInitialAmount = newBankroll.value.bookmakers.reduce((total, bm) => {
        return total + (bm.initialAmount || 0);
    }, 0);
    
    // Vérifier s'il y a de nouveaux bookmakers (sans userBookmakerId)
    const hasNewBookmakers = newBankroll.value.bookmakers.some(bm => !bm.userBookmakerId && bm.bookmaker && bm.initialAmount > 0);
    
    // Mapper les champs du formulaire vers les champs attendus par l'API
    const bankrollData = {
        bankroll_name: newBankroll.value.name,
        bankroll_description: newBankroll.value.comment || '',
        bankroll_start_amount: totalInitialAmount
        // bankroll_benefits reste à 0 par défaut et n'est pas géré lors de l'ajout/modification
    };
    
    try {
        let bankrollId;
        
        if (editingBankroll.value) {
            // Modification d'une bankroll existante
            await BankrollService.updateBankroll(editingBankroll.value.id, bankrollData);
            bankrollId = editingBankroll.value.id;
            
            // Gérer les associations bookmaker-bankroll pour la modification
            console.log('Modification - Bookmakers à traiter:', newBankroll.value.bookmakers);
            const bookmakerErrors = [];
            
            // Identifier et supprimer les bookmakers qui ont été retirés du formulaire
            const currentBookmakerIds = newBankroll.value.bookmakers
                .filter(bm => bm.userBookmakerId)
                .map(bm => bm.userBookmakerId);
            
            const bookmarkersToDelete = originalBookmakers.value.filter(original => 
                !currentBookmakerIds.includes(original.id)
            );
            
            console.log('Bookmakers à supprimer:', bookmarkersToDelete);
            
            // Supprimer les bookmakers retirés
            for (const bookmakerToDelete of bookmarkersToDelete) {
                try {
                    await BankrollService.deleteUserBookmaker(bookmakerToDelete.id);
                    console.log('Bookmaker supprimé avec succès:', bookmakerToDelete.id);
                } catch (deleteError) {
                    console.error('Erreur lors de la suppression du bookmaker:', bookmakerToDelete.id, deleteError);
                    bookmakerErrors.push(`Erreur de suppression: ${deleteError.message}`);
                }
            }
            for (const bookmaker of newBankroll.value.bookmakers) {
                console.log('Modification - Traitement du bookmaker:', bookmaker);
                if (bookmaker.bookmaker && bookmaker.initialAmount > 0) {
                    // Trouver l'ID du bookmaker par son slug
                    const selectedBookmaker = availableBookmakers.value.find(bm => bm.slug === bookmaker.bookmaker);
                    console.log('Modification - Bookmaker sélectionné pour le slug', bookmaker.bookmaker, ':', selectedBookmaker);
                    if (selectedBookmaker) {
                        const userBookmakerData = {
                            bookmaker_start_amount: bookmaker.initialAmount,
                            bookmaker_actual_amount: bookmaker.initialAmount,
                            bookmaker_comment: ''
                        };
                        console.log('Modification - Données à envoyer:', userBookmakerData);
                        
                        try {
                            if (bookmaker.userBookmakerId) {
                                // Mettre à jour un bookmaker existant
                                await BankrollService.updateUserBookmaker(bookmaker.userBookmakerId, userBookmakerData);
                                console.log('Modification - Association bookmaker mise à jour avec succès:', selectedBookmaker.name);
                            } else {
                                // Créer une nouvelle association
                                const createData = {
                                    ...userBookmakerData,
                                    bookmakers_id: selectedBookmaker.id,
                                    users_bankrolls_id: bankrollId
                                };
                                await BankrollService.createUserBookmaker(createData);
                                console.log('Modification - Nouvelle association bookmaker créée avec succès:', selectedBookmaker.name);
                            }
                        } catch (bookmakerError) {
                            console.error('Modification - Erreur lors du traitement du bookmaker:', selectedBookmaker.name, bookmakerError);
                            bookmakerErrors.push(`${selectedBookmaker.name}: ${bookmakerError.message}`);
                        }
                    } else {
                        console.error('Modification - Bookmaker non trouvé pour le slug:', bookmaker.bookmaker);
                        bookmakerErrors.push(`Bookmaker non trouvé: ${bookmaker.bookmaker}`);
                    }
                }
            }
            
            // Afficher les erreurs de bookmakers s'il y en a
            if (bookmakerErrors.length > 0) {
                toast.add({
                    severity: 'warn',
                    summary: 'Attention',
                    detail: `Certains bookmakers n'ont pas pu être associés: ${bookmakerErrors.join(', ')}`,
                    life: 5000
                });
            }
            
            toast.add({ 
                severity: 'success', 
                summary: 'Succès', 
                detail: 'Bankroll modifiée avec succès', 
                life: 3000 
            });
        } else {
            // Ajout d'une nouvelle bankroll
            const response = await BankrollService.createBankroll(bankrollData);
            bankrollId = response.bankroll.id;
            
            // Enregistrer les associations bookmaker-bankroll
            console.log('Bookmakers à associer:', newBankroll.value.bookmakers);
            console.log('Bookmakers disponibles:', availableBookmakers.value);
            const bookmakerErrors = [];
            for (const bookmaker of newBankroll.value.bookmakers) {
                console.log('Traitement du bookmaker:', bookmaker);
                if (bookmaker.bookmaker && bookmaker.initialAmount > 0) {
                    // Trouver l'ID du bookmaker par son slug
                    const selectedBookmaker = availableBookmakers.value.find(bm => bm.slug === bookmaker.bookmaker);
                    console.log('Bookmaker sélectionné pour le slug', bookmaker.bookmaker, ':', selectedBookmaker);
                    if (selectedBookmaker) {
                        const userBookmakerData = {
                            bookmakers_id: selectedBookmaker.id,
                            users_bankrolls_id: bankrollId,
                            bookmaker_start_amount: bookmaker.initialAmount,
                            bookmaker_actual_amount: bookmaker.initialAmount,
                            bookmaker_comment: ''
                        };
                        console.log('Données à envoyer pour l\'association:', userBookmakerData);
                        
                        try {
                            await BankrollService.createUserBookmaker(userBookmakerData);
                            console.log('Association bookmaker créée avec succès:', selectedBookmaker.name);
                        } catch (bookmakerError) {
                            console.error('Erreur lors de l\'association du bookmaker:', selectedBookmaker.name, bookmakerError);
                            bookmakerErrors.push(`${selectedBookmaker.name}: ${bookmakerError.message}`);
                        }
                    } else {
                        console.error('Bookmaker non trouvé pour le slug:', bookmaker.bookmaker);
                        bookmakerErrors.push(`Bookmaker non trouvé: ${bookmaker.bookmaker}`);
                    }
                }
            }
            
            // Afficher les erreurs de bookmakers s'il y en a
            if (bookmakerErrors.length > 0) {
                toast.add({
                    severity: 'warn',
                    summary: 'Attention',
                    detail: `Certains bookmakers n'ont pas pu être associés: ${bookmakerErrors.join(', ')}`,
                    life: 5000
                });
            }
            
            toast.add({ 
                severity: 'success', 
                summary: 'Succès', 
                detail: 'Bankroll créée avec succès', 
                life: 3000 
            });
        }
        
        // Recharger les bankrolls
        await loadBankrolls();
        closeBankrollDialog();
    } catch (err) {
        console.error('Erreur lors de la sauvegarde:', err);
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: err.message || 'Erreur lors de la sauvegarde de la bankroll',
            life: 3000
        });
    }
}

async function deleteBankroll(bankroll) {
    try {
        await BankrollService.deleteBankroll(bankroll.id);
        toast.add({ severity: 'success', summary: 'Succès', detail: 'Bankroll supprimée avec succès', life: 3000 });
        // Recharger les bankrolls
        await loadBankrolls();
    } catch (err) {
        console.error('Erreur lors de la suppression:', err);
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: err.message || 'Erreur lors de la suppression de la bankroll',
            life: 3000
        });
    }
}


</script>

<template>
    <Toast />
    <Fluid>
        <div class="flex flex-col gap-8">
            <div class="card flex flex-col items-center gap-4 w-full p-6">
                <!-- Afficher un skeleton si les informations utilisateur ne sont pas encore chargées -->
                <div v-if="!userProfile.id">
                    <Skeleton shape="circle" size="6rem" class="mb-2"></Skeleton>
                </div>
                <!-- Afficher l'avatar une fois que les informations utilisateur sont chargées -->
                <img v-else :src="getAvatarUrl()" alt="Avatar" class="w-24 h-24 rounded-full border-2 border-gray-300 cursor-pointer" id="avatarImg" @click="showMenu($event)" @error="onAvatarError" />
                <input type="file" id="avatarInput" accept="image/*" @change="onAvatarSelected" class="hidden" />
                <Menu :model="menuItems" ref="menu" :popup="true" />
                <!-- Afficher des skeletons pour les informations textuelles si elles ne sont pas encore chargées -->
                <template v-if="!userProfile.id">
                    <Skeleton width="15rem" class="mb-2"></Skeleton>
                    <Skeleton width="8rem" class="mb-2"></Skeleton>
                    <Skeleton width="12rem"></Skeleton>
                </template>
                <!-- Afficher les informations textuelles une fois chargées -->
                <template v-else>
                    <div class="text-xl font-semibold">{{ userProfile.firstName }} {{ userProfile.lastName }}</div>
                    <div class="text-gray-500">{{ userProfile.email }}</div>
                    <div class="text-gray-500">@{{ userProfile.username }}</div>
                    <div class="text-gray-700 font-semibold">Niveau: {{ userProfile.level }}</div>
                </template>
            </div>

            <div class="card flex flex-col gap-4 w-full">
                <div class="font-semibold text-xl">Abonnement</div>
                <!-- Afficher des skeletons si les informations utilisateur ne sont pas encore chargées -->
                <div v-if="!userProfile.id" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Skeleton height="3rem" class="w-full"></Skeleton>
                    <Skeleton height="3rem" class="w-full"></Skeleton>
                    <Skeleton height="3rem" class="w-full"></Skeleton>
                </div>
                <!-- Afficher les boutons d'abonnement une fois que les informations utilisateur sont chargées -->
                <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Button v-for="option in subscriptionOptions" :key="option.slug" @click="selectedOption = option.slug"
                            :class="{'bg-blue-500 text-white': selectedOption === option.slug}"
                            class="w-full p-2 border rounded-md">
                        {{ option.name }}
                    </Button>
                </div>
            </div>
            <div class="card flex flex-col gap-4 w-full">
                <div class="flex justify-between items-center">
                    <div class="font-semibold text-xl">Paramètres</div>
                </div>
                <!-- Afficher des skeletons si les informations utilisateur ne sont pas encore chargées -->
                <template v-if="!userProfile.id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Skeleton width="8rem" height="1rem" class="mb-2"></Skeleton>
                            <Skeleton height="2.5rem" class="w-full"></Skeleton>
                        </div>
                        <div>
                            <Skeleton width="8rem" height="1rem" class="mb-2"></Skeleton>
                            <Skeleton height="2.5rem" class="w-full"></Skeleton>
                        </div>
                        <div>
                            <Skeleton width="8rem" height="1rem" class="mb-2"></Skeleton>
                            <Skeleton height="2.5rem" class="w-full"></Skeleton>
                        </div>
                        <div>
                            <Skeleton width="8rem" height="1rem" class="mb-2"></Skeleton>
                            <Skeleton height="2.5rem" class="w-full"></Skeleton>
                        </div>
                        <div>
                            <Skeleton width="8rem" height="1rem" class="mb-2"></Skeleton>
                            <Skeleton height="2.5rem" class="w-full"></Skeleton>
                        </div>
                        <div>
                            <Skeleton width="8rem" height="1rem" class="mb-2"></Skeleton>
                            <Skeleton height="2.5rem" class="w-full"></Skeleton>
                        </div>
                        <div>
                            <Skeleton width="8rem" height="1rem" class="mb-2"></Skeleton>
                            <Skeleton height="2.5rem" class="w-full"></Skeleton>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <Skeleton width="8rem" height="2.5rem"></Skeleton>
                    </div>
                </template>
                <!-- Afficher les paramètres une fois que les informations utilisateur sont chargées -->
                <template v-else>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label>Choisissez votre langue</label>
                            <Select v-model="selectedLanguage" :options="languageOptions" optionLabel="name" optionValue="slug" class="w-full"></Select>
                        </div>
                        <div>
                            <label>Choisissez votre devise globale</label>
                            <Select v-model="selectedCurrency" :options="currencyOptions" optionLabel="name" optionValue="slug" class="w-full"></Select>
                        </div>
                        <div>
                            <label>Choisissez votre page d'accueil</label>
                            <Select v-model="selectedHomepage" :options="homepageOptions" optionLabel="name" optionValue="slug" class="w-full"></Select>
                        </div>
                        <div>
                            <label>Fuseau horaire</label>
                            <Select v-model="selectedTimezone" :options="timezoneOptions" optionLabel="name" optionValue="slug" class="w-full"></Select>
                        </div>
                        <div>
                            <label>Affichage par défaut des paris</label>
                            <Select v-model="selectedDisplayBetView" :options="displayBetViewOptions" optionLabel="name" optionValue="slug" class="w-full"></Select>
                        </div>
                        <div>
                            <label>Affichage par défaut du tableau de bord</label>
                            <Select v-model="selectedDisplayDashboard" :options="displayDashboardOptions" optionLabel="name" optionValue="slug" class="w-full"></Select>
                        </div>
                        <div>
                            <label>Date de duplication d'un pari</label>
                            <Select v-model="selectedDuplicateBetDate" :options="duplicateBetDateOptions" optionLabel="name" optionValue="slug" class="w-full"></Select>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <Button @click="saveUserSettings" :loading="isSavingSettings" icon="pi pi-save" label="Enregistrer" class="p-button-primary" style="width: auto" />
                    </div>
                </template>
            </div>

            <!-- Section Bankrolls -->
            <div class="card flex flex-col gap-4 w-full">
                <div class="flex justify-between items-center">
                    <div class="font-semibold text-xl">Gestion des Bankrolls</div>
                    <Button @click="openBankrollDialog()" icon="pi pi-plus" label="Ajouter une bankroll" class="p-button-primary" />
                </div>
                
                <!-- Afficher des skeletons si les informations utilisateur ne sont pas encore chargées -->
                <template v-if="!userProfile.id">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <Skeleton height="8rem" class="w-full"></Skeleton>
                        <Skeleton height="8rem" class="w-full"></Skeleton>
                        <Skeleton height="8rem" class="w-full"></Skeleton>
                    </div>
                </template>
                
                <!-- Afficher les bankrolls une fois que les informations utilisateur sont chargées -->
                <template v-else>
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
                </template>
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
                <label class="block text-sm font-medium mb-2">Commentaire</label>
                <Textarea v-model="newBankroll.comment" placeholder="Description ou notes sur cette bankroll..." rows="3" class="w-full" />
            </div>
            
            <div>
                <div class="flex justify-between items-center mb-3">
                    <label class="block text-sm font-medium">Bookmakers</label>
                    <Button @click="addBookmakerToBankroll" icon="pi pi-plus" label="Ajouter un bookmaker" class="p-button-sm" />
                </div>
                
                <div v-if="newBankroll.bookmakers.length === 0" class="text-gray-500 text-sm text-center py-4">
                    Aucun bookmaker ajouté
                </div>
                
                <div v-else class="space-y-3">
                    <div v-for="(bookmaker, index) in newBankroll.bookmakers" :key="index" class="flex gap-3 items-end">
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-1">Bookmaker</label>
                            <Select v-model="bookmaker.bookmaker" :options="availableBookmakers" optionLabel="name" optionValue="slug" placeholder="Sélectionner un bookmaker" class="w-full" />
                        </div>
                        <div class="w-32">
                            <label class="block text-sm font-medium mb-1">Montant initial (€)</label>
                            <InputNumber v-model="bookmaker.initialAmount" :min="0" :maxFractionDigits="2" placeholder="0.00" class="w-full" />
                        </div>
                        <Button @click="removeBookmakerFromBankroll(index)" icon="pi pi-trash" class="p-button-danger p-button-sm" />
                    </div>
                </div>
            </div>
        </div>
        
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button @click="closeBankrollDialog" label="Annuler" class="p-button-text" />
                <Button @click="saveBankroll" label="Enregistrer" class="p-button-primary" />
            </div>
        </template>
    </Dialog>
</template>
