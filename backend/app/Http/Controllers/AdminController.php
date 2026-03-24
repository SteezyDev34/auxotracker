<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * Constructeur - Applique le middleware d'authentification et de rôle
     */
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:admin,superadmin']);
    }

    /**
     * Liste tous les utilisateurs (accès admin uniquement)
     */
    public function getUsers()
    {
        $users = User::select('id', 'username', 'email', 'role', 'created_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    /**
     * Met à jour le rôle d'un utilisateur (accès superadmin uniquement)
     */
    public function updateUserRole(Request $request, $userId)
    {
        // Vérification supplémentaire pour les rôles sensibles
        if (!Auth::user()->role === 'superadmin') {
            return response()->json([
                'error' => 'Seul un super administrateur peut modifier les rôles'
            ], 403);
        }

        $request->validate([
            'role' => 'required|in:user,admin,manager,superadmin'
        ]);

        $user = User::findOrFail($userId);

        // Empêcher la modification de son propre rôle
        if ($user->id === Auth::id()) {
            return response()->json([
                'error' => 'Vous ne pouvez pas modifier votre propre rôle'
            ], 422);
        }

        $user->role = $request->role;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Rôle utilisateur mis à jour avec succès',
            'user' => $user->only(['id', 'username', 'email', 'role'])
        ]);
    }

    /**
     * Statistiques du système (accès admin uniquement)
     */
    public function getSystemStats()
    {
        $stats = [
            'total_users' => User::count(),
            'admin_users' => User::whereIn('role', ['admin', 'superadmin'])->count(),
            'regular_users' => User::where('role', 'user')->count(),
            'recent_registrations' => User::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }
}
