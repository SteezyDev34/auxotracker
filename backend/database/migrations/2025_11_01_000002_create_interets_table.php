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
        Schema::create('interets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('bankroll_id')->nullable()->comment('ID de la bankroll (AUXOBOT par défaut)');
            $table->decimal('montant_total_investi_date_versement', 10, 2)->comment('Montant total investi à la date de versement');
            $table->decimal('montant_interet', 10, 2)->comment('Montant de l\'intérêt versé (10%)');
            $table->decimal('taux_interet', 5, 2)->default(10.00)->comment('Taux d\'intérêt appliqué (%)');
            $table->enum('moyen_paiement', ['paypal', 'virement_bancaire', 'autre'])->default('paypal');
            $table->string('detail_paiement')->nullable()->comment('Email PayPal ou IBAN masqué');
            $table->date('date_versement')->comment('Date du versement des intérêts');
            $table->text('commentaire')->nullable();
            $table->timestamps();

            // Clés étrangères et index
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'bankroll_id']);
            $table->index(['user_id', 'date_versement']);
            $table->index('date_versement');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('interets');
    }
};
