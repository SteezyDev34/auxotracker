<?php

namespace App\Http\Controllers;

use App\Models\UserSportPreference;
use App\Models\Sport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserSportPreferenceController extends Controller
{
    /**
     * Récupérer les préférences sportives de l'utilisateur connecté.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Récupérer tous les sports avec les préférences de l'utilisateur
            $sports = Sport::leftJoin('user_sports_preferences', function ($join) use ($user) {
                $join->on('sports.id', '=', 'user_sports_preferences.sport_id')
                     ->where('user_sports_preferences.user_id', '=', $user->id);
            })
            ->select(
                'sports.id',
                'sports.name',
                'sports.slug',
                'sports.img',
                DB::raw('COALESCE(user_sports_preferences.is_favorite, false) as is_favorite'),
                DB::raw('COALESCE(user_sports_preferences.sort_order, 999) as sort_order')
            )
            ->orderBy('sort_order')
            ->orderBy('sports.name')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $sports
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des préférences sportives',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour les préférences sportives de l'utilisateur.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'sports_preferences' => 'required|array',
                'sports_preferences.*.sport_id' => 'required|integer|exists:sports,id',
                'sports_preferences.*.is_favorite' => 'required|boolean',
                'sports_preferences.*.sort_order' => 'required|integer|min:0'
            ]);

            $user = Auth::user();
            $sportsPreferences = $request->input('sports_preferences');

            DB::beginTransaction();

            // Supprimer toutes les préférences existantes de l'utilisateur
            UserSportPreference::where('user_id', $user->id)->delete();

            // Créer les nouvelles préférences
            foreach ($sportsPreferences as $preference) {
                UserSportPreference::create([
                    'user_id' => $user->id,
                    'sport_id' => $preference['sport_id'],
                    'is_favorite' => $preference['is_favorite'],
                    'sort_order' => $preference['sort_order']
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Préférences sportives mises à jour avec succès'
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Données de validation invalides',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des préférences sportives',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer uniquement les sports favoris de l'utilisateur.
     *
     * @return JsonResponse
     */
    public function favorites(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $favoriteSports = $user->favoriteSports;

            return response()->json([
                'success' => true,
                'data' => $favoriteSports
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des sports favoris',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Basculer le statut favori d'un sport.
     *
     * @param Request $request
     * @param int $sportId
     * @return JsonResponse
     */
    public function toggleFavorite(Request $request, int $sportId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Vérifier que le sport existe
            $sport = Sport::findOrFail($sportId);
            
            $preference = UserSportPreference::where('user_id', $user->id)
                ->where('sport_id', $sportId)
                ->first();

            if ($preference) {
                // Basculer le statut favori
                $preference->is_favorite = !$preference->is_favorite;
                $preference->save();
            } else {
                // Créer une nouvelle préférence
                $maxOrder = UserSportPreference::where('user_id', $user->id)->max('sort_order') ?? 0;
                UserSportPreference::create([
                    'user_id' => $user->id,
                    'sport_id' => $sportId,
                    'is_favorite' => true,
                    'sort_order' => $maxOrder + 1
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Statut favori mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut favori',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}