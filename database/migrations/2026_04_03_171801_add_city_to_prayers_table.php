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
        DB::statement('
        DELETE FROM prayers
        WHERE id NOT IN (
            SELECT min_id FROM (
                SELECT MIN(id) as min_id
                FROM prayers
                GROUP BY city, date
            ) as keep
        )
    ');
        Schema::table('prayers', function (Blueprint $table) {
            $table->string('city')->nullable(false)->change(); // jadikan NOT NULL
            $table->unique(['city', 'date'], 'prayers_city_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prayers', function (Blueprint $table) {
            $table->dropUnique('prayers_city_date_unique');
        });
    }
};
