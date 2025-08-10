<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    /**
     * Récupérer tous les événements avec filtres
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::with(['team1', 'team2', 'league']);
        
        // Filtres
        if ($request->has('league_id')) {
            $query->where('league_id', $request->league_id);
        }
        
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->has('market')) {
            $query->where('market', $request->market);
        }
        
        if ($request->has('date_from')) {
            $query->where('event_date', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->where('event_date', '<=', $request->date_to);
        }
        
        $events = $query->orderBy('event_date', 'desc')->paginate(50);
        
        return response()->json([
            'success' => true,
            'data' => $events->items(),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total()
            ]
        ]);
    }

    /**
     * Récupérer un événement spécifique
     */
    public function show(Event $event): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $event->load(['team1', 'team2', 'league'])
        ]);
    }

    /**
     * Créer un nouvel événement
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team1_id' => 'required|exists:teams,id',
            'team2_id' => 'required|exists:teams,id',
            'league_id' => 'required|exists:leagues,id',
            'type' => 'required|string|max:255',
            'market' => 'required|string|max:255',
            'odd' => 'required|numeric|min:0',
            'event_date' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $event = Event::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Événement créé avec succès',
            'data' => $event->load(['team1', 'team2', 'league'])
        ], 201);
    }

    /**
     * Mettre à jour un événement
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team1_id' => 'sometimes|exists:teams,id',
            'team2_id' => 'sometimes|exists:teams,id',
            'league_id' => 'sometimes|exists:leagues,id',
            'type' => 'sometimes|string|max:255',
            'market' => 'sometimes|string|max:255',
            'odd' => 'sometimes|numeric|min:0',
            'event_date' => 'sometimes|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $event->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Événement mis à jour avec succès',
            'data' => $event->load(['team1', 'team2', 'league'])
        ]);
    }

    /**
     * Supprimer un événement
     */
    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Événement supprimé avec succès'
        ]);
    }
} 