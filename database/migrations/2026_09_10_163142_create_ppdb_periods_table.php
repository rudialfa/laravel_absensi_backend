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
        Schema::create('ppdb_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('name'); // "PPDB 2027/2028"
            $table->string('academic_year');

            $table->date('registration_start');
            $table->date('registration_end');

            $table->unsignedInteger('quota')->nullable(); // null = tidak dibatasi

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['company_id', 'is_active'], 'idx_ppdb_periods_company_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_periods');
    }
};
