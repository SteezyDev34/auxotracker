<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('sport')) {
            $query->where('sport', $request->sport);
        }
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sq) use ($q) {
                $sq->where('description', 'like', "%{$q}%")
                   ->orWhere('subject_name', 'like', "%{$q}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 50), 200);
        $logs = $query->paginate($perPage);

        // Statistiques globales
        $stats = [
            'total'    => ActivityLog::count(),
            'today'    => ActivityLog::whereDate('created_at', now()->toDateString())->count(),
            'errors'   => ActivityLog::where('level', 'error')->whereDate('created_at', now()->toDateString())->count(),
            'by_type'  => ActivityLog::selectRaw('type, count(*) as count')
                            ->groupBy('type')->orderByDesc('count')->limit(10)
                            ->pluck('count', 'type'),
            'by_sport' => ActivityLog::selectRaw('sport, count(*) as count')
                            ->whereNotNull('sport')->groupBy('sport')->orderByDesc('count')
                            ->pluck('count', 'sport'),
        ];

        return response()->json([
            'data'  => $logs,
            'stats' => $stats,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        ActivityLog::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function clear(Request $request): JsonResponse
    {
        $query = ActivityLog::query();
        if ($request->filled('before')) {
            $query->where('created_at', '<', $request->before);
        }
        $count = $query->count();
        $query->delete();
        return response()->json(['success' => true, 'deleted' => $count]);
    }
}
