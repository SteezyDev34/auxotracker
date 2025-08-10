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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['deposit', 'withdraw'])->comment('Type de transaction: dépôt ou retrait');
            $table->decimal('amount', 10, 2)->comment('Montant de la transaction');
            $table->date('transaction_date')->comment('Date de la transaction');
            $table->string('description')->nullable()->comment('Description optionnelle');
            $table->string('method')->nullable()->comment('Méthode de paiement (carte, virement, etc.)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
