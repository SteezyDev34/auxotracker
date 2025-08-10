<script setup>
import AppMenu from './AppMenu.vue';
import { useRouter } from 'vue-router';

const router = useRouter();

const logout = () => {
    localStorage.removeItem('token'); // Supprime le token de l'utilisateur
    router.push('/auth/login'); // Redirige vers la page de connexion
};
</script>
<template>
    <div class="layout-sidebar">
        <app-menu></app-menu>
    </div>

</template>

<style lang="scss" scoped></style>
