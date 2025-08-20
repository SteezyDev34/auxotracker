<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécuter les migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_sports_preferences')) {
            Schema::create('user_sports_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('sport_id')->constrained('sports')->onDelete('cascade');
                $table->boolean('is_favorite')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                
                // Index unique pour éviter les doublons user_id + sport_id
                $table->unique(['user_id', 'sport_id']);
                
                // Index pour optimiser les requêtes de tri
                $table->index(['user_id', 'is_favorite', 'sort_order']);
            });
        }
    }

    /**
     * Annuler les migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sports_preferences');
    }
};