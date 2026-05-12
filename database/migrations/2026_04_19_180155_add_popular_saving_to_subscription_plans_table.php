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
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Badge "Terpopuler" di UI pilih paket
            $table->boolean('is_popular')
                ->default(false)
                ->after('is_free');

            // Label hemat yang ditampilkan di UI, contoh: "Hemat ~16%"
            // Null = tidak tampil label hemat
            $table->string('saving_label', 50)
                ->nullable()
                ->after('is_popular');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['is_popular', 'saving_label']);
        });
    }
};
