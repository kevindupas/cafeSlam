<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * Crée les triggers de la table catégories.
         * DB::unprepared envoie le texte SQL tel quel (pas de paramètres liés :
         * le fichier ne contient aucune donnée saisie par l'utilisateur)
         */
        DB::unprepared(file_get_contents(database_path('sql/categories_triggers_mysql.sql')));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXIST trg_categories_updated_at');
    }
};
