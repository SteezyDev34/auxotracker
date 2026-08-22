<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('team1_id')->nullable()->change();
            $table->unsignedBigInteger('team2_id')->nullable()->change();
            if (!Schema::hasColumn('events', 'team1_name')) {
                $table->string('team1_name')->nullable()->after('team1_id');
            }
            if (!Schema::hasColumn('events', 'team2_name')) {
                $table->string('team2_name')->nullable()->after('team2_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['team1_name', 'team2_name']);
            $table->unsignedBigInteger('team1_id')->nullable(false)->change();
            $table->unsignedBigInteger('team2_id')->nullable(false)->change();
        });
    }
};
