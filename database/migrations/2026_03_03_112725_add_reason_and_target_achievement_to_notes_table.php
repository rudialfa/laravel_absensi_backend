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
        Schema::table('notes', function (Blueprint $table) {
            // Tambah kolom reason (opsional)
            $table->text('reason')
                ->nullable()
                ->after('note');

            // Tambah kolom target achievement (opsional)
            $table->text('target_achievement')
                ->nullable()
                ->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('reason');
            $table->dropColumn('target_achievement');
        });
    }
};
