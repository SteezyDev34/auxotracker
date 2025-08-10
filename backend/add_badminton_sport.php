<?php

// Charger l'autoloader et l'application Laravel
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Sport;

try {
    // Vérifier si le sport badminton existe déjà
    $existingBadminton = Sport::where('name', 'LIKE', '%badminton%')
                             ->orWhere('slug', 'LIKE', '%badminton%')
                             ->first();
    
    if ($existingBadminton) {
        echo "Le sport Badminton existe déjà avec l'ID: " . $existingBadminton->id . "\n";
        echo "Name: " . $existingBadminton->name . "\n";
        echo "Slug: " . $existingBadminton->slug . "\n";
        echo "Image: " . $existingBadminton->img . "\n";
    } else {
        // Créer le nouveau sport badminton
        $badminton = Sport::create([
            'name' => 'Badminton',
            'slug' => 'badminton',
            'img' => 'badminton.svg',
            'description' => 'Sport de raquette'
        ]);
        
        echo "Sport Badminton ajouté avec succès!\n";
        echo "ID: " . $badminton->id . "\n";
        echo "Name: " . $badminton->name . "\n";
        echo "Slug: " . $badminton->slug . "\n";
        echo "Image: " . $badminton->img . "\n";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}