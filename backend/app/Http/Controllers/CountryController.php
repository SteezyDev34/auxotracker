<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * Récupérer la liste de tous les pays
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $countries = Country::select('id', 'name', 'code', 'slug')
                              ->orderBy('name')
                              ->get();
            
            return response()->json([
                'success' => true,
                'data' => $countries
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des pays',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechercher des pays par nom avec pagination
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search', '');
            $page = (int) $request->get('page', 1);
            $limit = min((int) $request->get('limit', 30), 50); // Limiter à 50 max, défaut 30
            
            $query = Country::select('id', 'name', 'code', 'slug')
                           ->orderBy('name');
            
            // Appliquer le filtre de recherche si fourni
            if (!empty($search)) {
                $query->where('name', 'LIKE', '%' . $search . '%');
            }
            
            // Calculer l'offset
            $offset = ($page - 1) * $limit;
            
            // Récupérer le total pour la pagination
            $total = $query->count();
            
            // Récupérer les résultats avec pagination
            $countries = $query->skip($offset)
                              ->take($limit)
                              ->get();
            
            // Déterminer s'il y a plus de résultats
            $hasMore = ($offset + $limit) < $total;
            
            return response()->json([
                'success' => true,
                'data' => $countries,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'has_more' => $hasMore
                ],
                'hasMore' => $hasMore // Pour compatibilité avec le frontend
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche des pays',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}