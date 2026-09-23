<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 60)->index();
            $table->string('sport', 30)->nullable()->index();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_name')->nullable();
            $table->string('subject_img')->nullable();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->enum('level', ['info', 'success', 'warning', 'error'])->default('info');
            $table->string('command')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
