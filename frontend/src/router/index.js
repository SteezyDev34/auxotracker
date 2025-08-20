import AppLayout from '@/layout/AppLayout.vue';
import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            component: AppLayout,
            children: [
                {
                    path: '/',
                    name: 'dashboard',
                    component: () => import('@/views/Dashboard.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/profile/mes-informations',
                    name: 'profilInfos',
                    component: () => import('@/views/profile/infos.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/profile/sports',
                    name: 'profilSports',
                    component: () => import('@/views/profile/sports.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/profil/inf',
                    name: 'profilInf',
                    component: () => import('@/views/profile/infos.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/uikit/formlayout',
                    name: 'formlayout',
                    component: () => import('@/views/uikit/FormLayout.vue')
                },
                {
                    path: '/uikit/input',
                    name: 'input',
                    component: () => import('@/views/uikit/InputDoc.vue')
                },
                {
                    path: '/uikit/button',
                    name: 'button',
                    component: () => import('@/views/uikit/ButtonDoc.vue')
                },
                {
                    path: '/uikit/table',
                    name: 'table',
                    component: () => import('@/views/uikit/TableDoc.vue')
                },
                {
                    path: '/uikit/list',
                    name: 'list',
                    component: () => import('@/views/uikit/ListDoc.vue')
                },
                {
                    path: '/uikit/tree',
                    name: 'tree',
                    component: () => import('@/views/uikit/TreeDoc.vue')
                },
                {
                    path: '/uikit/panel',
                    name: 'panel',
                    component: () => import('@/views/uikit/PanelsDoc.vue')
                },

                {
                    path: '/uikit/overlay',
                    name: 'overlay',
                    component: () => import('@/views/uikit/OverlayDoc.vue')
                },
                {
                    path: '/uikit/media',
                    name: 'media',
                    component: () => import('@/views/uikit/MediaDoc.vue')
                },
                {
                    path: '/uikit/message',
                    name: 'message',
                    component: () => import('@/views/uikit/MessagesDoc.vue')
                },
                {
                    path: '/uikit/file',
                    name: 'file',
                    component: () => import('@/views/uikit/FileDoc.vue')
                },
                {
                    path: '/uikit/menu',
                    name: 'menu',
                    component: () => import('@/views/uikit/MenuDoc.vue')
                },
                {
                    path: '/uikit/charts',
                    name: 'charts',
                    component: () => import('@/views/uikit/ChartDoc.vue')
                },
                {
                    path: '/uikit/misc',
                    name: 'misc',
                    component: () => import('@/views/uikit/MiscDoc.vue')
                },
                {
                    path: '/uikit/timeline',
                    name: 'timeline',
                    component: () => import('@/views/uikit/TimelineDoc.vue')
                },
                {
                    path: '/pages/empty',
                    name: 'empty',
                    component: () => import('@/views/pages/Empty.vue')
                },
                {
                    path: '/pages/crud',
                    name: 'crud',
                    component: () => import('@/views/pages/Crud.vue')
                },
                {
                    path: '/documentation',
                    name: 'documentation',
                    component: () => import('@/views/pages/Documentation.vue')
                },
                {
                    path: '/ajouter-pari',
                    name: 'ajouterPari',
                    component: () => import('@/components/add-bet/AddBetForm.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/mes-paris',
                    name: 'mesParis',
                    component: () => import('@/components/dashboard/BetsHistoryWidget.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/profile/bookmakers',
                    name: 'profileBookmakers',
                    component: () => import('@/views/profile/bookmakers.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/profile/bankrolls',
                    name: 'profileBankrolls',
                    component: () => import('@/views/profile/bankrolls.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/profile/tipsters',
                    name: 'profileTipsters',
                    component: () => import('@/views/profile/tipsters.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/mes-outils',
                    name: 'mesOutils',
                    component: () => import('@/views/MesOutils.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/mes-outils/rembourse-si-nul',
                    name: 'rembourseSiNul',
                    component: () => import('@/components/calculators/RembourseSiNul.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/mes-outils/double-chance',
                    name: 'doubleChance',
                    component: () => import('@/components/calculators/DoubleChance.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/mes-outils/taux-retour-joueur',
                    name: 'tauxRetourJoueur',
                    component: () => import('@/components/calculators/TauxRetourJoueur.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/mes-outils/dutching',
                    name: 'dutching',
                    component: () => import('@/components/calculators/Dutching.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/simulateur/martingale',
                    name: 'martingale',
                    component: () => import('@/views/Martingale.vue'),
                    meta: { requiresAuth: true }
                },
            ]
        },
        {
            path: '/landing',
            name: 'landing',
            component: () => import('@/views/pages/Landing.vue')
        },
        {
            path: '/pages/notfound',
            name: 'notfound',
            component: () => import('@/views/pages/NotFound.vue')
        },

        {
            path: '/auth/login',
            name: 'login',
            component: () => import('@/views/pages/auth/Login.vue')
        },

        {
            path: '/auth/register',
            name: 'register',
            component: () => import('@/views/pages/auth/Register.vue')
        },

        {
            path: '/auth/access',
            name: 'accessDenied',
            component: () => import('@/views/pages/auth/Access.vue')
        },
        {
            path: '/auth/error',
            name: 'error',
            component: () => import('@/views/pages/auth/Error.vue')
        }
    ]
});
// ➤ Vérifier l'authentification avant de charger une route
/*  router.beforeEach((to, from, next) => {
    const isAuthenticated = !!localStorage.getItem('token'); // Vérifie si un token est stocké

    if (to.meta.requiresAuth && !isAuthenticated) {
        next({ name: 'login' }); // Rediriger vers la connexion si l'utilisateur n'est pas connecté
    } else if (to.meta.guestOnly && isAuthenticated) {
        next({ name: 'dashboard' }); // Rediriger un utilisateur connecté vers le dashboard
    } else {
        next(); // Continuer normalement
    }
}); */
export default router;
