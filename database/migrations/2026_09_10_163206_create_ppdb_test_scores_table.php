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
        Schema::create('ppdb_test_scores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ppdb_applicant_id')->constrained('ppdb_applicants')->cascadeOnDelete();

            // akademik = tes umum, baca_quran & hafalan & wawancara = khusus Pondok
            $table->enum('jenis', ['akademik', 'baca_quran', 'hafalan', 'wawancara']);

            $table->decimal('score', 5, 2)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('tested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('test_date')->nullable();

            $table->timestamps();

            $table->unique(['ppdb_applicant_id', 'jenis'], 'uniq_ppdb_score_applicant_jenis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_test_scores');
    }
};
