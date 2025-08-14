<?php

namespace App\Http\Controllers;

use App\Models\Bookmaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookmakerController extends Controller
{
    /**
     * Affiche la liste de tous les bookmakers.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $bookmakers = Bookmaker::all();
        return response()->json(['bookmakers' => $bookmakers]);
    }

    /**
     * Enregistre un nouveau bookmaker.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bookmaker_name' => 'required|string|max:255|unique:bookmakers',
            'bookmaker_img' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bookmaker = Bookmaker::create($request->all());

        return response()->json(['message' => 'Bookmaker créé avec succès', 'bookmaker' => $bookmaker], 201);
    }

    /**
     * Affiche les détails d'un bookmaker spécifique.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $bookmaker = Bookmaker::findOrFail($id);
        return response()->json(['bookmaker' => $bookmaker]);
    }

    /**
     * Met à jour un bookmaker existant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $bookmaker = Bookmaker::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'bookmaker_name' => 'required|string|max:255|unique:bookmakers,bookmaker_name,' . $id,
            'bookmaker_img' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bookmaker->update($request->all());

        return response()->json(['message' => 'Bookmaker mis à jour avec succès', 'bookmaker' => $bookmaker]);
    }

    /**
     * Supprime un bookmaker.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $bookmaker = Bookmaker::findOrFail($id);
        $bookmaker->delete();

        return response()->json(['message' => 'Bookmaker supprimé avec succès']);
    }
}