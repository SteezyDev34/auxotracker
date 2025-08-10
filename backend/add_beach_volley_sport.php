<?php

// Charger l'autoloader et l'application Laravel
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

use App\Models\Sport;

try {
    // Vérifier si le sport beach volley existe déjà
    $existingBeachVolley = Sport::where('name', 'LIKE', '%beach%')
                               ->orWhere('name', 'LIKE', '%volley%')
                               ->orWhere('slug', 'LIKE', '%beach%')
                               ->orWhere('slug', 'LIKE', '%volley%')
                               ->first();
    
    if ($existingBeachVolley) {
        echo "Le sport Beach Volley existe déjà avec l'ID: " . $existingBeachVolley->id . "\n";
        echo "Name: " . $existingBeachVolley->name . "\n";
        echo "Slug: " . $existingBeachVolley->slug . "\n";
        echo "Image: " . $existingBeachVolley->img . "\n";
    } else {
        // Créer le nouveau sport beach volley
        $beachVolley = Sport::create([
            'name' => 'Beach Volley',
            'slug' => 'beach-volley',
            'img' => 'beach-volley.svg',
            'description' => 'Sport de plage avec ballon'
        ]);
        
        echo "Sport Beach Volley ajouté avec succès!\n";
        echo "ID: " . $beachVolley->id . "\n";
        echo "Name: " . $beachVolley->name . "\n";
        echo "Slug: " . $beachVolley->slug . "\n";
        echo "Image: " . $beachVolley->img . "\n";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}