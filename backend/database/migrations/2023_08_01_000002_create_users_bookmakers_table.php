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
        if (!Schema::hasTable('users_bookmakers')) {
            Schema::create('users_bookmakers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('users_bankrolls_id')->constrained('users_bankrolls')->onDelete('cascade');
                $table->foreignId('bookmakers_id')->constrained('bookmakers')->onDelete('cascade');
                $table->decimal('bookmaker_start_amount', 10, 2)->default(0.00);
                $table->decimal('bookmaker_actual_amount', 10, 2)->default(0.00);
                $table->text('bookmaker_comment')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users_bookmakers');
    }
};