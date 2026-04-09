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
        Schema::table('bets', function (Blueprint $table) {
            if (!Schema::hasColumn('bets', 'tipster_id')) {
                $table->unsignedBigInteger('tipster_id')->nullable()->after('bankroll_id');
                $table->foreign('tipster_id')
                    ->references('id')
                    ->on('tipsters')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bets', function (Blueprint $table) {
            if (Schema::hasColumn('bets', 'tipster_id')) {
                $table->dropForeign(['tipster_id']);
                $table->dropColumn('tipster_id');
            }
        });
    }
};
