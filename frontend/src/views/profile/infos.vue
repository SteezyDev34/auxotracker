<script setup>
import { ref, onMounted } from 'vue';
import settingsData from '@/data/settings.json';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Fluid from 'primevue/fluid'
import Menu from 'primevue/menu';
import Toast from 'primevue/toast';
import Skeleton from 'primevue/skeleton';
import { useToast } from 'primevue/usetoast';
import { UserService } from '@/service/UserService';

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
            avatar: userData.user_profile_picture || ''
        };
        // Mettre à jour l'URL de l'avatar après avoir récupéré les données
        updateAvatarUrl();
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

// Charger les informations utilisateur au montage du composant
onMounted(async () => {
    await fetchUserInfo();
    // Mettre à jour l'URL de l'avatar après avoir récupéré les données
    updateAvatarUrl();
    // Charger les paramètres utilisateur si disponibles
    if (userProfile.value.id) {
        loadUserSettings(userProfile.value);
    }
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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

                
            </div>
            <input type="file" id="avatarInput" style="display:none" @change="onAvatarSelected" />
        </div>
    </Fluid>
</template>
