import AppLayout from "@/layout/AppLayout.vue";
import { createRouter, createWebHistory } from "vue-router";

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: "/",
      component: AppLayout,
      children: [
        {
          path: "/",
          name: "dashboard",
          component: () => import("@/views/Dashboard.vue"),
          meta: { requiresAuth: true },
        },
        {
          path: "/profile/mes-informations",
          name: "profilInfos",
          component: () => import("@/views/profile/infos.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: [
              "user",
              "investor",
              "admin",
              "superadmin",
              "manager",
            ],
          },
        },
        {
          path: "/profile/sports",
          name: "profilSports",
          component: () => import("@/views/profile/sports.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: ["user", "admin", "superadmin", "manager"],
          },
        },
        {
          path: "/profil/inf",
          name: "profilInf",
          component: () => import("@/views/profile/infos.vue"),
          meta: { requiresAuth: true },
        },
        {
          path: "/uikit/formlayout",
          name: "formlayout",
          component: () => import("@/views/uikit/FormLayout.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/input",
          name: "input",
          component: () => import("@/views/uikit/InputDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/button",
          name: "button",
          component: () => import("@/views/uikit/ButtonDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/table",
          name: "table",
          component: () => import("@/views/uikit/TableDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/list",
          name: "list",
          component: () => import("@/views/uikit/ListDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/tree",
          name: "tree",
          component: () => import("@/views/uikit/TreeDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/panel",
          name: "panel",
          component: () => import("@/views/uikit/PanelsDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },

        {
          path: "/uikit/overlay",
          name: "overlay",
          component: () => import("@/views/uikit/OverlayDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/media",
          name: "media",
          component: () => import("@/views/uikit/MediaDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/message",
          name: "message",
          component: () => import("@/views/uikit/MessagesDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/file",
          name: "file",
          component: () => import("@/views/uikit/FileDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/menu",
          name: "menu",
          component: () => import("@/views/uikit/MenuDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/charts",
          name: "charts",
          component: () => import("@/views/uikit/ChartDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/misc",
          name: "misc",
          component: () => import("@/views/uikit/MiscDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/uikit/timeline",
          name: "timeline",
          component: () => import("@/views/uikit/TimelineDoc.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/gestion/ligues",
          name: "gestionLigues",
          component: () => import("@/views/admin/leagues.vue"),
          meta: { requiresAuth: true, requiresRole: ["admin", "superadmin"] },
        },
        {
          path: "/gestion/equipes-non-trouvees",
          name: "gestionEquipesNonTrouvees",
          component: () => import("@/views/admin/team-searches-not-found.vue"),
          // TODO: Remettre requiresAuth et requiresRole une fois les tests terminés
          // meta: { requiresAuth: true, requiresRole: ["superadmin"] },
        },
        {
          path: "/pages/empty",
          name: "empty",
          component: () => import("@/views/pages/Empty.vue"),
        },
        {
          path: "/pages/crud",
          name: "crud",
          component: () => import("@/views/pages/Crud.vue"),
        },
        {
          path: "/documentation",
          name: "documentation",
          component: () => import("@/views/pages/Documentation.vue"),
        },
        {
          path: "/ajouter-pari",
          name: "ajouterPari",
          component: () => import("@/components/add-bet/AddBetForm.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: ["user", "admin", "superadmin", "manager"],
          },
        },
        {
          path: "/mes-paris",
          name: "mesParis",
          component: () =>
            import("@/components/dashboard/BetsHistoryWidget.vue"),
          meta: { requiresAuth: true },
        },
        {
          path: "/profile/bookmakers",
          name: "profileBookmakers",
          component: () => import("@/views/profile/bookmakers.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: ["user", "admin", "superadmin", "manager"],
          },
        },
        {
          path: "/profile/bankrolls",
          name: "profileBankrolls",
          component: () => import("@/views/profile/bankrolls.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: ["user", "admin", "superadmin", "manager"],
          },
        },
        {
          path: "/profile/tipsters",
          name: "profileTipsters",
          component: () => import("@/views/profile/tipsters.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: ["user", "admin", "superadmin", "manager"],
          },
        },
        {
          path: "/profile/interets",
          name: "profileInterets",
          component: () => import("@/views/profile/interets.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: ["investor"],
          },
        },
        {
          path: "/mes-outils",
          name: "mesOutils",
          component: () => import("@/views/MesOutils.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: ["user", "admin", "superadmin", "manager"],
          },
        },
        {
          path: "/mes-outils/rembourse-si-nul",
          name: "rembourseSiNul",
          component: () =>
            import("@/components/calculators/RembourseSiNul.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: ["user", "admin", "superadmin", "manager"],
          },
        },
        {
          path: "/mes-outils/double-chance",
          name: "doubleChance",
          component: () => import("@/components/calculators/DoubleChance.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: ["user", "admin", "superadmin", "manager"],
          },
        },
        {
          path: "/mes-outils/taux-retour-joueur",
          name: "tauxRetourJoueur",
          component: () =>
            import("@/components/calculators/TauxRetourJoueur.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: ["user", "admin", "superadmin", "manager"],
          },
        },
        {
          path: "/mes-outils/dutching",
          name: "dutching",
          component: () => import("@/components/calculators/Dutching.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: ["user", "admin", "superadmin", "manager"],
          },
        },
        {
          path: "/simulateur/martingale",
          name: "martingale",
          component: () => import("@/views/Martingale.vue"),
          meta: {
            requiresAuth: true,
            requiresRole: ["user", "admin", "superadmin", "manager"],
          },
        },
      ],
    },
    {
      path: "/landing",
      name: "landing",
      component: () => import("@/views/pages/Landing.vue"),
    },
    {
      path: "/pages/notfound",
      name: "notfound",
      component: () => import("@/views/pages/NotFound.vue"),
    },

    {
      path: "/auth/login",
      name: "login",
      component: () => import("@/views/pages/auth/Login.vue"),
    },

    {
      path: "/auth/register",
      name: "register",
      component: () => import("@/views/pages/auth/Register.vue"),
    },

    {
      path: "/auth/access",
      name: "accessDenied",
      component: () => import("@/views/pages/auth/Access.vue"),
    },
    {
      path: "/auth/error",
      name: "error",
      component: () => import("@/views/pages/auth/Error.vue"),
    },
  ],
});
// ➤ Import du composable d'authentification
import { useAuth } from "@/composables/useAuth.js";

// ➤ Vérifier l'authentification et les rôles avant de charger une route
router.beforeEach(async (to, from, next) => {
  const { isAuthenticated, hasAnyRole, initAuth, user } = useAuth();
  const token = localStorage.getItem("token");

  // Si un token existe mais que l'utilisateur n'est pas encore chargé, on l'initialise
  if (token && !user.value) {
    try {
      await initAuth();
    } catch (error) {
      // Si l'init échoue, on supprime le token invalide et on redirige vers login
      localStorage.removeItem("token");
      if (to.meta.requiresAuth) {
        return next({ name: "login" });
      }
    }
  }

  // Vérification de l'authentification
  if (to.meta.requiresAuth && !isAuthenticated.value) {
    return next({ name: "login" });
  }

  // Vérification des rôles requis
  if (to.meta.requiresRole && isAuthenticated.value) {
    if (!hasAnyRole(to.meta.requiresRole)) {
      return next({ name: "accessDenied" });
    }
  }

  // Redirection des utilisateurs connectés depuis les pages guest-only
  if (to.meta.guestOnly && isAuthenticated.value) {
    return next({ name: "dashboard" });
  }

  next(); // Continuer normalement
});
export default router;
