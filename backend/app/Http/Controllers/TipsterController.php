<?php

namespace App\Http\Controllers;

use App\Models\Tipster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TipsterController extends Controller
{
    /**
     * Affiche la liste des tipsters de l'utilisateur connecté.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();
        $tipsters = Tipster::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['tipsters' => $tipsters]);
    }

    /**
     * Crée un nouveau tipster pour l'utilisateur connecté.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'link' => 'nullable|url|max:500',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        
        $tipster = new Tipster($request->all());
        $tipster->user_id = $user->id;
        $tipster->save();

        return response()->json([
            'message' => 'Tipster créé avec succès',
            'tipster' => $tipster
        ], 201);
    }

    /**
     * Affiche un tipster spécifique.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();
        $tipster = Tipster::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$tipster) {
            return response()->json(['message' => 'Tipster non trouvé'], 404);
        }

        return response()->json(['tipster' => $tipster]);
    }

    /**
     * Met à jour un tipster existant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $tipster = Tipster::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$tipster) {
            return response()->json(['message' => 'Tipster non trouvé'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'link' => 'nullable|url|max:500',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tipster->update($request->all());

        return response()->json([
            'message' => 'Tipster mis à jour avec succès',
            'tipster' => $tipster
        ]);
    }

    /**
     * Supprime un tipster.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $tipster = Tipster::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$tipster) {
            return response()->json(['message' => 'Tipster non trouvé'], 404);
        }

        $tipster->delete();

        return response()->json(['message' => 'Tipster supprimé avec succès']);
    }
}
