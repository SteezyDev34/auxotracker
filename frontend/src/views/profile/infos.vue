<script setup>
import { ref } from 'vue';
import settingsData from '@/data/settings.json';

const subscriptionOptions = ref(settingsData.subscriptionOptions);
const selectedOption = ref(null);

const userProfile = ref({
    avatar: 'https://via.placeholder.com/100',
    firstName: 'John',
    lastName: 'Doe',
    email: 'johndoe@example.com',
    username: 'johndoe',
    level: 'Expert'
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
</script>

<template>
    <Fluid>
        <div class="flex flex-col gap-8">
            <div class="card flex flex-col items-center gap-4 w-full p-6">
                <img :src="userProfile.avatar" alt="Avatar" class="w-24 h-24 rounded-full border-2 border-gray-300" />
                <div class="text-xl font-semibold">{{ userProfile.firstName }} {{ userProfile.lastName }}</div>
                <div class="text-gray-500">{{ userProfile.email }}</div>
                <div class="text-gray-500">@{{ userProfile.username }}</div>
                <div class="text-gray-700 font-semibold">Niveau: {{ userProfile.level }}</div>
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
                <div class="font-semibold text-xl">Paramètres</div>
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
            </div>
        </div>
    </Fluid>
</template>
