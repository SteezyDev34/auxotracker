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
        // Datée avant create_tipsters_table (2025_08_17) par erreur — sur une
        // install fraîche, la table n'existe pas encore à ce stade (elle est
        // déjà créée nullable directement). No-op dans ce cas, inchangé pour
        // les environnements existants où la table existe déjà.
        if (!Schema::hasTable('tipsters')) {
            return;
        }
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