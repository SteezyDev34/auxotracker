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
        if (!Schema::hasTable('users_bankrolls')) {
            Schema::create('users_bankrolls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('bankroll_name');
                $table->decimal('bankroll_start_amount', 10, 2)->default(0.00);
                $table->decimal('bankroll_actual_amount', 10, 2)->default(0.00);
                $table->text('bankroll_description')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_bankrolls');
    }
};