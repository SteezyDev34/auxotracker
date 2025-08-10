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
        Schema::create('bets', function (Blueprint $table) {
            $table->id();
            $table->date('bet_date');
            $table->decimal('global_odds', 8, 2);
            $table->string('bet_code');
            $table->enum('result', ['won', 'lost', 'void', 'pending'])->default('pending');
            $table->foreignId('sport_id')->constrained()->onDelete('cascade');
            $table->decimal('stake', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};
