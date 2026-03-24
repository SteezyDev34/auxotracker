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
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('bankroll_id')->nullable()->comment('ID de la bankroll (AUXOBOT par défaut)');
            $table->decimal('montant_investi', 10, 2)->comment('Montant investi dans cette injection');
            $table->date('date_investissement')->comment('Date de l\'investissement');
            $table->enum('statut', ['actif', 'inactif', 'retire'])->default('actif');
            $table->text('commentaire')->nullable();
            $table->timestamps();

            // Clés étrangères et index
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'bankroll_id']);
            $table->index(['user_id', 'statut']);
            $table->index('date_investissement');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('investments');
    }
};
