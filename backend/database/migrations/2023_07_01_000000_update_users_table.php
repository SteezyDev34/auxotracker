<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mise à jour de la table users pour correspondre à la structure fournie.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Suppression de la colonne 'name' si elle existe
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }
            
            // Ajout des nouvelles colonnes si elles n'existent pas
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->after('id');
            }
            
            if (!Schema::hasColumn('users', 'user_lastname')) {
                $table->string('user_lastname')->nullable()->after('email_verified_at');
            }
            
            if (!Schema::hasColumn('users', 'user_firstname')) {
                $table->string('user_firstname')->nullable()->after('user_lastname');
            }
            
            if (!Schema::hasColumn('users', 'user_profile_picture')) {
                $table->string('user_profile_picture')->default('user.png')->after('user_firstname');
            }
            
            if (!Schema::hasColumn('users', 'user_banner_picture')) {
                $table->string('user_banner_picture')->default('cover.jpg')->after('user_profile_picture');
            }
            
            if (!Schema::hasColumn('users', 'user_level')) {
                $table->string('user_level')->default('debutant')->after('user_banner_picture');
            }
            
            if (!Schema::hasColumn('users', 'user_birthdate')) {
                $table->date('user_birthdate')->nullable()->after('user_level');
            }
            
            if (!Schema::hasColumn('users', 'user_language')) {
                $table->string('user_language')->nullable()->after('user_birthdate');
            }
            
            if (!Schema::hasColumn('users', 'user_currency')) {
                $table->string('user_currency')->nullable()->after('user_language');
            }
            
            if (!Schema::hasColumn('users', 'user_sort_bets_by')) {
                $table->string('user_sort_bets_by')->nullable()->after('user_currency');
            }
            
            if (!Schema::hasColumn('users', 'user_welcome_page')) {
                $table->string('user_welcome_page')->default('1')->after('user_sort_bets_by');
            }
            
            if (!Schema::hasColumn('users', 'user_bookmaker_list')) {
                $table->text('user_bookmaker_list')->nullable()->after('user_welcome_page');
            }
            
            if (!Schema::hasColumn('users', 'user_sport_list')) {
                $table->text('user_sport_list')->nullable()->after('user_bookmaker_list');
            }
            
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['user', 'superadmin', 'manager', 'admin'])->default('user')->after('user_sport_list');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restauration de la colonne 'name' si elle a été supprimée
            if (!Schema::hasColumn('users', 'name')) {
                $table->string('name')->after('id');
            }
            
            // Suppression des colonnes ajoutées
            $columns = [
                'username',
                'user_lastname',
                'user_firstname',
                'user_profile_picture',
                'user_banner_picture',
                'user_level',
                'user_birthdate',
                'user_language',
                'user_currency',
                'user_sort_bets_by',
                'user_welcome_page',
                'user_bookmaker_list',
                'user_sport_list',
                'role'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};