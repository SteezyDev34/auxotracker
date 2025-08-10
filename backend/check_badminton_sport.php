<?php

// Charger l'autoloader et l'application Laravel
require '/var/www/html/vendor/autoload.php';
require '/var/www/html/bootstrap/app.php';

use App\Models\Sport;

try {
    // Rechercher le sport badminton
    $badminton = Sport::where('name', 'LIKE', '%badminton%')
                     ->orWhere('slug', 'LIKE', '%badminton%')
                     ->first();
    
    if ($badminton) {
        echo "Sport Badminton trouvé:\n";
        echo "ID: " . $badminton->id . "\n";
        echo "Name: " . $badminton->name . "\n";
        echo "Slug: " . $badminton->slug . "\n";
        echo "Image: " . $badminton->img . "\n";
    } else {
        echo "Sport Badminton non trouvé dans la base de données\n";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}