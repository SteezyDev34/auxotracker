<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserBankroll;
use App\Models\User;

class ListBankrolls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bankroll:list {--user= : ID de l\'utilisateur}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Liste toutes les bankrolls disponibles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user');

        $query = UserBankroll::with('user', 'bets');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $bankrolls = $query->get();

        if ($bankrolls->isEmpty()) {
            $this->warn('Aucune bankroll trouvée.');
            return 0;
        }

        $this->info("=== Liste des Bankrolls ===\n");

        $headers = ['ID', 'Nom', 'Utilisateur', 'Capital Initial', 'Bénéfices', 'Nb Paris', 'Créée le'];
        $rows = [];

        foreach ($bankrolls as $bankroll) {
            $rows[] = [
                $bankroll->id,
                $bankroll->bankroll_name,
                $bankroll->user->name ?? 'N/A',
                number_format($bankroll->bankroll_start_amount, 2) . ' €',
                number_format($bankroll->bankroll_benefits, 2) . ' €',
                $bankroll->bets->count(),
                $bankroll->created_at->format('Y-m-d H:i')
            ];
        }

        $this->table($headers, $rows);

        $this->info("\nPour ajuster les stakes d'une bankroll, utilisez :");
        $this->line("php artisan bankroll:adjust-stakes {ID} {strategy} [--dry-run]");
        $this->line("Stratégies disponibles : recovery, martingale");

        return 0;
    }
}
