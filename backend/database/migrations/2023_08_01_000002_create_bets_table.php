<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('bets')) {
            Schema::create('bets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('bookmaker_id')->constrained()->onDelete('cascade');
                $table->string('bet_type');
                $table->decimal('odd', 8, 2);
                $table->decimal('stake', 8, 2);
                $table->decimal('profit', 8, 2)->nullable();
                $table->string('status')->default('pending');
                $table->text('comment')->nullable();
                $table->datetime('bet_date');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};