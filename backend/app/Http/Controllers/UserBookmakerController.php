<?php

namespace App\Http\Controllers;

use App\Models\Bookmaker;
use App\Models\User;
use App\Models\UserBankroll;
use App\Models\UserBookmaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserBookmakerController extends Controller
{
    /**
     * Affiche la liste des bookmakers de l'utilisateur connecté.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();
        $userBookmakers = UserBookmaker::with('bookmaker', 'bankroll')
            ->where('user_id', $user->id)
            ->get();

        return response()->json(['user_bookmakers' => $userBookmakers]);
    }

    /**
     * Associe un bookmaker à l'utilisateur connecté.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bookmakers_id' => 'required|exists:bookmakers,id',
            'users_bankrolls_id' => 'required|exists:users_bankrolls,id',
            'bookmaker_start_amount' => 'required|numeric|min:0',
            'bookmaker_actual_amount' => 'required|numeric|min:0',
            'bookmaker_comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        
        // Vérifier que la bankroll appartient bien à l'utilisateur
        $bankroll = UserBankroll::where('id', $request->users_bankrolls_id)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$bankroll) {
            return response()->json(['error' => 'La bankroll spécifiée n\'appartient pas à l\'utilisateur'], 403);
        }
        
        // Vérifier si l'association existe déjà
        $existingAssociation = UserBookmaker::where('user_id', $user->id)
            ->where('bookmakers_id', $request->bookmakers_id)
            ->where('users_bankrolls_id', $request->users_bankrolls_id)
            ->first();
            
        if ($existingAssociation) {
            return response()->json(['error' => 'Ce bookmaker est déjà associé à cette bankroll'], 422);
        }

        $userBookmaker = new UserBookmaker($request->all());
        $userBookmaker->user_id = $user->id;
        $userBookmaker->save();

        return response()->json([
            'message' => 'Bookmaker associé avec succès', 
            'user_bookmaker' => $userBookmaker->load('bookmaker', 'bankroll')
        ], 201);
    }

    /**
     * Affiche les détails d'une association utilisateur-bookmaker spécifique.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();
        $userBookmaker = UserBookmaker::with('bookmaker', 'bankroll')
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json(['user_bookmaker' => $userBookmaker]);
    }

    /**
     * Met à jour une association utilisateur-bookmaker existante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $userBookmaker = UserBookmaker::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'users_bankrolls_id' => 'sometimes|required|exists:users_bankrolls,id',
            'bookmaker_start_amount' => 'sometimes|required|numeric|min:0',
            'bookmaker_actual_amount' => 'sometimes|required|numeric|min:0',
            'bookmaker_comment' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Si la bankroll est modifiée, vérifier qu'elle appartient à l'utilisateur
        if ($request->has('users_bankrolls_id')) {
            $bankroll = UserBankroll::where('id', $request->users_bankrolls_id)
                ->where('user_id', $user->id)
                ->first();
                
            if (!$bankroll) {
                return response()->json(['error' => 'La bankroll spécifiée n\'appartient pas à l\'utilisateur'], 403);
            }
        }

        $userBookmaker->update($request->all());

        return response()->json([
            'message' => 'Association mise à jour avec succès', 
            'user_bookmaker' => $userBookmaker->load('bookmaker', 'bankroll')
        ]);
    }

    /**
     * Supprime une association utilisateur-bookmaker.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $userBookmaker = UserBookmaker::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $userBookmaker->delete();

        return response()->json(['message' => 'Association supprimée avec succès']);
    }
}