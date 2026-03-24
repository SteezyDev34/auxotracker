/**
 * Composable pour la gestion de l'authentification et des rôles utilisateur
 */
import { ref, computed } from "vue";
import { UserService } from "@/service/UserService.js";

const user = ref(null);
const isLoading = ref(false);

export const useAuth = () => {
  /**
   * Récupère les informations de l'utilisateur connecté
   */
  const fetchUser = async () => {
    try {
      isLoading.value = true;
      const userData = await UserService.getUserInfo();
      user.value = userData.user || userData;
      return user.value;
    } catch (error) {
      console.error(
        "Erreur lors de la récupération des données utilisateur:",
        error
      );
      user.value = null;
      // Si l'utilisateur n'est pas authentifié, on peut le rediriger
      if (
        error.message.includes("401") ||
        error.message.includes("Unauthenticated")
      ) {
        localStorage.removeItem("token");
        window.location.href = "/auth/login";
      }
      throw error;
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Vérifie si l'utilisateur est connecté
   */
  const isAuthenticated = computed(() => {
    return !!localStorage.getItem("token") && !!user.value;
  });

  /**
   * Vérifie si l'utilisateur a un rôle spécifique
   * @param {string} role - Le rôle à vérifier
   * @returns {boolean}
   */
  const hasRole = (role) => {
    if (!user.value || !user.value.role) return false;
    return user.value.role === role;
  };

  /**
   * Vérifie si l'utilisateur a au moins un des rôles spécifiés
   * @param {string[]} roles - Les rôles à vérifier
   * @returns {boolean}
   */
  const hasAnyRole = (roles) => {
    if (!user.value || !user.value.role) return false;
    return roles.includes(user.value.role);
  };

  /**
   * Vérifie si l'utilisateur est administrateur
   * @returns {boolean}
   */
  const isAdmin = computed(() => {
    return hasAnyRole(["admin", "superadmin"]);
  });

  /**
   * Vérifie si l'utilisateur est super administrateur
   * @returns {boolean}
   */
  const isSuperAdmin = computed(() => {
    return hasRole("superadmin");
  });

  /**
   * Vérifie si l'utilisateur est manager
   * @returns {boolean}
   */
  const isManager = computed(() => {
    return hasAnyRole(["manager", "admin", "superadmin"]);
  });

  /**
   * Vérifie si l'utilisateur est investor
   * @returns {boolean}
   */
  const isInvestor = computed(() => {
    return hasRole("investor");
  });

  /**
   * Vérifie si l'utilisateur peut accéder aux fonctionnalités de base (user + investor - lecture seule)
   * @returns {boolean}
   */
  const canAccessBasicFeatures = computed(() => {
    return hasAnyRole(["user", "investor"]);
  });

  /**
   * Vérifie si l'utilisateur peut créer des paris (user seulement, pas investor)
   * @returns {boolean}
   */
  const canCreateBets = computed(() => {
    return hasAnyRole(["user", "admin", "superadmin", "manager"]);
  });

  /**
   * Vérifie si l'utilisateur peut accéder aux outils et simulateurs (user seulement, pas investor)
   * @returns {boolean}
   */
  const canAccessTools = computed(() => {
    return hasAnyRole(["user", "admin", "superadmin", "manager"]);
  });

  /**
   * Vérifie si l'utilisateur peut accéder aux fonctionnalités avancées (user + admin)
   * @returns {boolean}
   */
  const canAccessAdvancedFeatures = computed(() => {
    return hasAnyRole(["user", "admin", "superadmin"]);
  });

  /**
   * Vérifie si l'utilisateur peut accéder au profil (user uniquement, pas investor)
   * @returns {boolean}
   */
  const canAccessProfile = computed(() => {
    return hasAnyRole(["user", "admin", "superadmin", "manager"]);
  });

  /**
   * Vérifie si l'utilisateur peut accéder aux informations de profil (user + investor)
   * @returns {boolean}
   */
  const canAccessProfileInfo = computed(() => {
    return hasAnyRole(["user", "investor", "admin", "superadmin", "manager"]);
  });

  /**
   * Vérifie si l'utilisateur peut accéder aux sections avancées du profil (user seulement)
   * @returns {boolean}
   */
  const canAccessFullProfile = computed(() => {
    return hasAnyRole(["user", "admin", "superadmin", "manager"]);
  });

  /**
   * Vérifie si l'utilisateur peut accéder aux intérêts (investor seulement)
   * @returns {boolean}
   */
  const canAccessInterets = computed(() => {
    return hasRole("investor");
  });

  /**
   * Déconnecte l'utilisateur
   */
  const logout = () => {
    localStorage.removeItem("token");
    user.value = null;
    window.location.href = "/auth/login";
  };

  /**
   * Initialise l'authentification (à appeler au démarrage de l'app)
   */
  const initAuth = async () => {
    const token = localStorage.getItem("token");
    if (token && !user.value) {
      try {
        await fetchUser();
      } catch (error) {
        // Si l'erreur est liée à l'authentification, on ignore
        // L'utilisateur sera redirigé vers la page de connexion
        console.warn(
          "Impossible de récupérer les données utilisateur:",
          error.message
        );
      }
    }
  };

  /**
   * Fonction de debug pour vérifier l'état d'authentification
   */
  const debugAuth = () => {
    const token = localStorage.getItem("token");
    console.log("=== AUTH DEBUG ===");
    console.log("Token présent:", !!token);
    console.log("Token length:", token ? token.length : 0);
    console.log(
      "Token preview:",
      token ? token.substring(0, 50) + "..." : "null"
    );
    console.log("User data:", user.value);
    console.log("Is authenticated:", isAuthenticated.value);
    console.log("==================");
    return {
      hasToken: !!token,
      tokenLength: token ? token.length : 0,
      hasUser: !!user.value,
      isAuthenticated: isAuthenticated.value,
    };
  };

  return {
    // State
    user: computed(() => user.value),
    isLoading: computed(() => isLoading.value),

    // Computed
    isAuthenticated,
    isAdmin,
    isSuperAdmin,
    isManager,
    isInvestor,
    canAccessBasicFeatures,
    canCreateBets,
    canAccessTools,
    canAccessAdvancedFeatures,
    canAccessProfile,
    canAccessProfileInfo,
    canAccessFullProfile,
    canAccessInterets,

    // Methods
    fetchUser,
    hasRole,
    hasAnyRole,
    logout,
    initAuth,
    debugAuth,
  };
};
