<?php

namespace App\Http\Controllers;

use App\Models\UserBankroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserBankrollController extends Controller
{
    /**
     * Affiche la liste des bankrolls de l'utilisateur connecté avec leurs bookmakers.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();
        $bankrolls = UserBankroll::with(['userBookmakers.bookmaker'])
            ->where('user_id', $user->id)
            ->get();

        return response()->json(['bankrolls' => $bankrolls]);
    }

    /**
     * Crée une nouvelle bankroll pour l'utilisateur connecté.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bankroll_name' => 'required|string|max:255',
            'bankroll_start_amount' => 'required|numeric|min:0',
            'bankroll_benefits' => 'sometimes|numeric',
            'bankroll_description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();

        $bankroll = new UserBankroll($request->all());
        $bankroll->user_id = $user->id;
        $bankroll->save();

        return response()->json(['message' => 'Bankroll créée avec succès', 'bankroll' => $bankroll], 201);
    }

    /**
     * Affiche les détails d'une bankroll spécifique.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();
        $bankroll = UserBankroll::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json(['bankroll' => $bankroll]);
    }

    /**
     * Met à jour une bankroll existante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $bankroll = UserBankroll::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'bankroll_name' => 'sometimes|required|string|max:255',
            'bankroll_start_amount' => 'sometimes|required|numeric|min:0',
            'bankroll_benefits' => 'sometimes|numeric',
            'bankroll_description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bankroll->update($request->all());

        return response()->json(['message' => 'Bankroll mise à jour avec succès', 'bankroll' => $bankroll]);
    }

    /**
     * Supprime une bankroll et toutes ses données associées.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        $user = Auth::user();
        $bankroll = UserBankroll::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        // Validation du nom de la bankroll pour sécuriser la suppression
        $validator = Validator::make($request->all(), [
            'bankroll_name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Le nom de la bankroll est requis pour confirmer la suppression'], 422);
        }

        // Vérifier que le nom saisi correspond au nom de la bankroll
        if ($request->bankroll_name !== $bankroll->bankroll_name) {
            return response()->json(['error' => 'Le nom saisi ne correspond pas au nom de la bankroll'], 422);
        }

        try {
            DB::transaction(function () use ($bankroll) {
                // 1. Récupérer tous les paris associés à cette bankroll
                $bets = $bankroll->bets()->with('events')->get();

                foreach ($bets as $bet) {
                    // 2. Supprimer les relations bet_events (table pivot)
                    $bet->events()->detach();

                    // 3. Supprimer les événements orphelins (optionnel - seulement s'ils ne sont liés à aucun autre pari)
                    foreach ($bet->events as $event) {
                        // Vérifier si l'événement n'est lié à aucun autre pari
                        if ($event->bets()->count() <= 1) { // <= 1 car on va supprimer ce pari
                            $event->delete();
                        }
                    }

                    // 4. Supprimer le pari lui-même
                    $bet->delete();
                }

                // 5. Supprimer tous les bookmakers associés à cette bankroll
                $bankroll->userBookmakers()->delete();

                // 6. Supprimer la bankroll
                $bankroll->delete();
            });

            return response()->json(['message' => 'Bankroll et toutes ses données associées supprimées avec succès']);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de la bankroll: ' . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de la suppression de la bankroll'], 500);
        }
    }
}
