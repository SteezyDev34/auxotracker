<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->unsignedBigInteger('bankroll_id')->nullable()->after('sport_id');
            $table->foreign('bankroll_id')
                ->references('id')
                ->on('users_bankrolls')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->dropForeign(['bankroll_id']);
            $table->dropColumn('bankroll_id');
        });
    }
};
