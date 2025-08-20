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
        if (!Schema::hasTable('players')) {
            Schema::create('players', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('nickname')->nullable();
                $table->string('short_name')->nullable();
                $table->string('slug')->unique();
                $table->string('position')->nullable();
                $table->string('img')->nullable();
                $table->string('sofascore_id')->nullable();
                $table->foreignId('team_id')->constrained()->onDelete('cascade');
                $table->integer('user_count')->nullable();
                $table->json('field_translations')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
