<?php

namespace App\Http\Controllers;

use App\Models\League;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class AdminLeagueController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'role:admin,superadmin']);
    }

    /**
     * Retourne la liste complète des ligues (admin)
     */
    public function index(Request $request): JsonResponse
    {
        $sportId = $request->get('sport_id');
        $countryId = $request->get('country_id');
        $search = $request->get('search');

        $query = League::with('country:id,name');

        if (!empty($sportId)) {
            $query->where('sport_id', $sportId);
        }

        if (!empty($countryId)) {
            $query->where('country_id', $countryId);
        }

        if (!empty($search)) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where('name', 'LIKE', '%' . $escaped . '%');
        }

        $leagues = $query->orderByDesc('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'img', 'country_id', 'sport_id', 'priority', 'nickname']);

        return response()->json(['success' => true, 'data' => $leagues]);
    }

    /**
     * Met à jour les priorités à partir d'un tableau {id, priority}
     */
    public function updatePriorities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'priorities' => 'required|array',
            'priorities.*.id' => 'required|integer|exists:leagues,id',
            'priorities.*.priority' => 'required|integer'
        ]);

        DB::beginTransaction();
        try {
            foreach ($data['priorities'] as $item) {
                League::where('id', $item['id'])->update(['priority' => (int)$item['priority']]);
            }
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Priorités mises à jour']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur lors de la mise à jour', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Met à jour une ligue (nickname par exemple)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $league = League::findOrFail($id);

        $validated = $request->validate([
            'nickname' => 'nullable|string|max:191',
            'priority' => 'nullable|integer'
        ]);

        if (array_key_exists('nickname', $validated)) {
            $league->nickname = $validated['nickname'];
        }

        if (array_key_exists('priority', $validated)) {
            $league->priority = (int)$validated['priority'];
        }

        $league->save();

        return response()->json(['success' => true, 'league' => $league]);
    }
}
