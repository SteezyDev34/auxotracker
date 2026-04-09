<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates pivot table league_team and migrates existing teams.league_id values.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('league_team', function (Blueprint $table) {
            $table->unsignedBigInteger('league_id');
            $table->unsignedBigInteger('team_id');
            $table->timestamps();

            $table->primary(['league_id', 'team_id']);
            $table->index('team_id');
        });

        // Migrate existing league_id values into pivot in chunks to avoid PHP timeouts
        if (Schema::hasTable('teams') && Schema::hasTable('league_team')) {
            // only migrate when pivot table is empty to avoid duplicate inserts
            $existing = DB::table('league_team')->limit(1)->exists();
            if (! $existing) {
                DB::table('teams')
                    ->whereNotNull('league_id')
                    ->orderBy('id')
                    ->chunk(500, function ($rows) {
                        $inserts = [];
                        foreach ($rows as $r) {
                            $inserts[] = [
                                'league_id' => $r->league_id,
                                'team_id' => $r->id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                        if (!empty($inserts)) {
                            // insertOrIgnore to be safe against race/duplicate key
                            DB::table('league_team')->insertOrIgnore($inserts);
                        }
                    });
            }
        }
    }

    /**
     * Reverse the migrations.
     * Drops the pivot table but leaves teams.league_id intact for rollback safety.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('league_team');
    }
};
