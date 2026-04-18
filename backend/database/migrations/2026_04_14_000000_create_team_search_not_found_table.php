<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('team_search_not_found')) {
            Schema::create('team_search_not_found', function (Blueprint $table) {
                $table->id();
                $table->string('search_term');
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
                $table->foreignId('sport_id')->nullable()->constrained()->onDelete('cascade');
                $table->boolean('resolved')->default(false);
                $table->foreignId('team_id')->nullable()->constrained()->onDelete('set null');
                $table->timestamps();

                $table->index(['resolved', 'sport_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_search_not_found');
    }
};
