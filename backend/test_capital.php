<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Simuler un utilisateur connecté (ID 1)
$user = App\Models\User::find(1);
if (!$user) {
    echo "Utilisateur non trouvé\n";
    exit(1);
}

// Récupérer les bankrolls de l'utilisateur
$userBankrolls = App\Models\UserBankroll::where('user_id', $user->id)->get();
echo "Utilisateur: {$user->name} (ID: {$user->id})\n";
echo "Nombre de bankrolls: " . $userBankrolls->count() . "\n\n";

foreach ($userBankrolls as $bankroll) {
    echo "Bankroll ID: {$bankroll->id}\n";
    echo "Nom: {$bankroll->bankroll_name}\n";
    echo "Capital initial: {$bankroll->bankroll_start_amount}€\n";
    echo "Bénéfices: {$bankroll->bankroll_benefits}€\n\n";
}

$totalCapital = $userBankrolls->sum('bankroll_start_amount');
echo "Capital total de toutes les bankrolls: {$totalCapital}€\n";
