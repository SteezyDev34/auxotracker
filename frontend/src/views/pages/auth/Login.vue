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
const email = ref('');
const password = ref('');
const checked = ref(false);
const loading = ref(false);
const errorMessage = ref('');

const login = async () => {
    if (!email.value || !password.value) {
        errorMessage.value = "Veuillez remplir tous les champs.";
        return;
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.post('https://api.auxotracker.lan/api/login', {
            email: email.value,
            password: password.value,
        }, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            withCredentials: true,
        });

        if (response.status === 200) {
            localStorage.setItem('token', response.data.token);
            router.push('/');
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message || "Une erreur s'est produite.";
    } finally {
        loading.value = false;
    }
};

const goToRegister = () => {
    router.push('/auth/register');
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
                        <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-4">Bienvenue sur PrimeLand !</div>
                        <span class="text-muted-color font-medium">Connectez-vous pour continuer</span>
                    </div>

                    <div>
                        <label for="email1" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">E-mail</label>
                        <InputText id="email1" type="text" placeholder="Adresse e-mail" class="w-full md:w-[30rem] mb-8" v-model="email" />

                        <label for="password1" class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2">Mot de passe</label>
                        <Password id="password1" v-model="password" placeholder="Mot de passe" :toggleMask="true" class="mb-4" fluid :feedback="false"></Password>

                        <div class="flex items-center justify-between mt-2 mb-8 gap-8">
                            <div class="flex items-center">
                                <Checkbox v-model="checked" id="rememberme1" binary class="mr-2"></Checkbox>
                                <label for="rememberme1" class="text-surface-900 dark:text-surface-0">Se souvenir de moi</label>
                            </div>
                            <a href="#" class="text-primary hover:underline font-medium">Mot de passe oublié ?</a>
                        </div>

                        <Button label="Se connecter" class="w-full py-3 text-xl" :disabled="loading" @click="login" />
                        <p v-if="errorMessage" class="text-red-500 text-center mt-4">{{ errorMessage }}</p>

                        <div class="mt-4 text-center">
                            <span class="text-muted-color">Vous n'avez pas de compte ?</span>
                            <a @click="goToRegister" class="text-primary hover:underline font-medium cursor-pointer">S'inscrire</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
