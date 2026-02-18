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
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // identitas periode (YYYY-MM)
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1..12

            $table->text('target');
            $table->text('achievement');     // pencapaian
            $table->text('problem');         // permasalahan
            $table->text('solution');        // penyelesaian

            // pastikan 1 user hanya 1 laporan per bulan
            $table->unique(['company_id', 'user_id', 'year', 'month']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
    }
};
