<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Transaction;
use Carbon\Carbon;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer des transactions de test
        $transactions = [
            [
                'type' => 'deposit',
                'amount' => 1000.00,
                'transaction_date' => Carbon::now()->subDays(30),
                'description' => 'Dépôt initial',
                'method' => 'Virement bancaire'
            ],
            [
                'type' => 'deposit',
                'amount' => 500.00,
                'transaction_date' => Carbon::now()->subDays(20),
                'description' => 'Rechargement compte',
                'method' => 'Carte bancaire'
            ],
            [
                'type' => 'withdraw',
                'amount' => 200.00,
                'transaction_date' => Carbon::now()->subDays(10),
                'description' => 'Retrait gains',
                'method' => 'Virement bancaire'
            ],
            [
                'type' => 'deposit',
                'amount' => 300.00,
                'transaction_date' => Carbon::now()->subDays(5),
                'description' => 'Nouveau dépôt',
                'method' => 'PayPal'
            ]
        ];

        foreach ($transactions as $transaction) {
            Transaction::create($transaction);
        }
    }
}
