<?php

namespace App\Http\Controllers;

use App\Models\UserBankroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserBankrollController extends Controller
{
    /**
     * Affiche la liste des bankrolls de l'utilisateur connecté.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();
        $bankrolls = UserBankroll::where('user_id', $user->id)->get();

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
            'bankroll_actual_amount' => 'required|numeric|min:0',
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
            'bankroll_actual_amount' => 'sometimes|required|numeric|min:0',
            'bankroll_description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bankroll->update($request->all());

        return response()->json(['message' => 'Bankroll mise à jour avec succès', 'bankroll' => $bankroll]);
    }

    /**
     * Supprime une bankroll.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $bankroll = UserBankroll::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        // Vérifier si la bankroll a des bookmakers associés
        if ($bankroll->userBookmakers()->count() > 0) {
            return response()->json(['error' => 'Impossible de supprimer cette bankroll car elle a des bookmakers associés'], 422);
        }

        $bankroll->delete();

        return response()->json(['message' => 'Bankroll supprimée avec succès']);
    }
}