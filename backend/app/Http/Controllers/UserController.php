<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        $file = $request->file('avatar');
        $filename = $user->id . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('public/avatar', $filename);

        // Supprimer l'ancien avatar si ce n'est pas user.jpg
        if ($user->user_profile_picture && $user->user_profile_picture !== 'user.jpg') {
            Storage::delete('public/avatar/' . $user->user_profile_picture);
        }

        // Mise à jour du champ user_profile_picture
        User::where('id', $user->id)->update([
            'user_profile_picture' => $filename
        ]);

        return response()->json([
            'success' => true,
            'avatar_url' => asset('storage/avatar/' . $filename)
        ]);
    }
    
    public function deleteAvatar(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }
        
        // Supprimer l'avatar actuel si ce n'est pas user.jpg
        if ($user->user_profile_picture && $user->user_profile_picture !== 'user.jpg') {
            Storage::delete('public/avatar/' . $user->user_profile_picture);
            
            // Réinitialiser l'avatar à l'image par défaut
            User::where('id', $user->id)->update([
                'user_profile_picture' => 'user.jpg'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Avatar supprimé avec succès',
                'avatar_url' => asset('storage/avatar/user.jpg')
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Aucun avatar personnalisé à supprimer'
        ]);
    }
    
    /**
     * Met à jour les paramètres de l'utilisateur
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSettings(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }
        
        // Validation des données
        $validator = Validator::make($request->all(), [
            'user_language' => 'nullable|string|max:10',
            'user_currency' => 'nullable|string|max:10',
            'user_welcome_page' => 'nullable|string|max:50',
            'user_timezone' => 'nullable|string|max:50',
            'user_sort_bets_by' => 'nullable|string|max:50',
            'user_display_dashboard' => 'nullable|string|max:50',
            'user_duplicate_bet_date' => 'nullable|string|max:50',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Récupération des champs à mettre à jour
        $fieldsToUpdate = $request->only([
            'user_language',
            'user_currency',
            'user_welcome_page',
            'user_timezone',
            'user_sort_bets_by',
            'user_display_dashboard',
            'user_duplicate_bet_date',
        ]);
        
        // Filtrer les champs null ou vides
        $fieldsToUpdate = array_filter($fieldsToUpdate, function ($value) {
            return $value !== null && $value !== '';
        });
        
        // Mise à jour des paramètres utilisateur
        if (!empty($fieldsToUpdate)) {
            User::where('id', $user->id)->update($fieldsToUpdate);
            
            // Récupérer l'utilisateur mis à jour
            $updatedUser = User::find($user->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Paramètres mis à jour avec succès',
                'user' => $updatedUser
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Aucun paramètre à mettre à jour'
        ]);
    }
}

