<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modifier l'ENUM pour ajouter le rôle 'investor'
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'investor', 'manager', 'admin', 'superadmin') DEFAULT 'user'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remettre l'ENUM à son état précédent (sans 'investor')
        // D'abord, s'assurer qu'aucun utilisateur n'a le rôle 'investor'
        DB::table('users')->where('role', 'investor')->update(['role' => 'user']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'manager', 'admin', 'superadmin') DEFAULT 'user'");
    }
};
