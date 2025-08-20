<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécute les migrations.
     */
    public function up(): void
    {
        Schema::table('tipsters', function (Blueprint $table) {
            $table->string('link')->nullable()->change(); // Rendre le lien optionnel
        });
    }

    /**
     * Annule les migrations.
     */
    public function down(): void
    {
        Schema::table('tipsters', function (Blueprint $table) {
            $table->string('link')->nullable(false)->change(); // Remettre le lien obligatoire
        });
    }
};