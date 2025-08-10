<script setup>
import { useRouter } from 'vue-router';
import FloatingConfigurator from '@/components/FloatingConfigurator.vue';
import { ref } from 'vue';
import axios from 'axios';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
const router = useRouter();
const username = ref('');
const email = ref('');
const password = ref('');
const confirmPassword = ref('');
const checked = ref(false);
const loading = ref(false);
const errorMessage = ref('');
const errorDetails = ref('');

const register = async () => {
    if (!checked.value) {
        errorMessage.value = "Vous devez accepter les conditions d'utilisation.";
        return;
    }

    if (password.value !== confirmPassword.value) {
        errorMessage.value = "Les mots de passe ne correspondent pas.";
        return;
    }

    loading.value = true;
    errorMessage.value = '';
    errorDetails.value = '';

    try {
        const response = await axios.post('https://api.auxotracker.lan/api/register', {
            username: username.value,
            email: email.value,
            password: password.value,
            password_confirmation: confirmPassword.value,
        }, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            withCredentials: true,
        });

        if (response.status === 201) {
            
            router.push('/auth/login');
        }
    } catch (error) {
        console.error("Erreur API:", error);
        errorMessage.value = error.response?.data?.message || "Une erreur s'est produite.";
        errorDetails.value = error.response ? JSON.stringify(error.response.data, null, 2) : error.message;
    } finally {
        loading.value = false;
    }
};

const goToLogin = () => {
    router.push('/auth/login');
};
</script>

<template>
    <FloatingConfigurator />
    <div class="bg-surface-50 dark:bg-surface-950 flex items-center justify-center min-h-screen min-w-[100vw] overflow-hidden">
        <div class="flex flex-col items-center justify-center">
            <div style="border-radius: 56px; padding: 0.3rem; background: linear-gradient(180deg, var(--primary-color) 10%, rgba(33, 150, 243, 0) 30%)">
                <div class="w-full bg-surface-0 dark:bg-surface-900 py-20 px-8 sm:px-20" style="border-radius: 53px">
                    <div class="text-center mb-8">
                        <svg viewBox="0 0 54 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="mb-8 w-16 shrink-0 mx-auto">
                            <!-- Icône -->
                        </svg>
                        <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-4">Créez un compte</div>
                        <span class="text-muted-color font-medium">Inscrivez-vous pour continuer</span>
                    </div>

                    <div>
                        <label for="username" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Nom d'utilisateur</label>
                        <InputText id="username" type="text" placeholder="Nom d'utilisateur" class="w-full md:w-[30rem] mb-8" v-model="username" />

                        <label for="email" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">E-mail</label>
                        <InputText id="email" type="text" placeholder="Adresse e-mail" class="w-full md:w-[30rem] mb-8" v-model="email" />

                        <label for="password" class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2">Mot de passe</label>
                        <Password id="password" v-model="password" placeholder="Mot de passe" :toggle-mask="true" class="w-full md:w-[30rem] mb-4"></Password>

                        <label for="confirmPassword" class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2">Confirmer le mot de passe</label>
                        <Password id="confirmPassword" v-model="confirmPassword" placeholder="Confirmer le mot de passe" :toggle-mask="true" class="w-full md:w-[30rem] mb-4"></Password>

                        <div class="flex items-center mt-2 mb-8 gap-8">
                            <Checkbox v-model="checked" id="terms" binary class="mr-2"></Checkbox>
                            <label for="terms" class="text-surface-900 dark:text-surface-0">J'accepte les conditions d'utilisation</label>
                        </div>

                        <Button label="S'inscrire" class="w-full py-3 text-xl" :disabled="loading" @click="register" />
                        <p v-if="errorMessage" class="text-red-500 text-center mt-4">{{ errorMessage }}</p>
                        <pre v-if="errorDetails" class="text-gray-500 bg-gray-100 p-2 rounded text-xs mt-2 overflow-auto">{{ errorDetails }}</pre>

                        <div class="mt-4 text-center">
                            <span class="text-muted-color">Déjà un compte ?</span>
                            <a @click="goToLogin" class="text-primary hover:underline font-medium cursor-pointer">Se connecter</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
