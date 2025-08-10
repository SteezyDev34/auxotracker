<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    /**
     * Récupérer les statistiques des transactions (dépôts/retraits)
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $query = Transaction::query();

            // Appliquer les filtres de période
            if ($request->has('period')) {
                $query->byPeriod($request->get('period'));
            }

            // Calculer les totaux des dépôts et retraits
            $totalDeposits = (clone $query)->deposits()->sum('amount');
            $totalWithdrawals = (clone $query)->withdrawals()->sum('amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_deposits' => round($totalDeposits, 2),
                    'total_withdrawals' => round($totalWithdrawals, 2),
                    'net_deposits' => round($totalDeposits - $totalWithdrawals, 2)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur transactionStats: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'error' => 'Une erreur est survenue lors du calcul des statistiques de transactions.',
            ], 500);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
