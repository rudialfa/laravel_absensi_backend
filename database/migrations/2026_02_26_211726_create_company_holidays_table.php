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
        Schema::create('company_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->date('date');
            $table->string('name'); // contoh: "Idul Fitri", "Libur Perusahaan"
            $table->enum('type', ['national', 'company'])->default('company');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'date'], 'uniq_company_holiday_date');
            $table->index(['company_id', 'date'], 'idx_company_holiday_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_holidays');
    }
};
