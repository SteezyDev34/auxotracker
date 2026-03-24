<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->integer('priority')->default(0)->after('sofascore_id');
        });
    }

    public function down()
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
