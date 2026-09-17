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
        Schema::create('bill_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            // Nama jenis tagihan, contoh: "SPP", "Uang Buku", "Seragam",
            // "Kegiatan", atau khusus Pondok: "Asrama", "Makan", "Laundry"
            $table->string('name');

            // bulanan = ditagih rutin tiap bulan (SPP, asrama, makan),
            // sekali = ditagih 1x saja (buku, seragam, kegiatan)
            $table->enum('periode', ['bulanan', 'sekali']);

            $table->decimal('default_amount', 12, 2)->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['company_id', 'name'], 'uniq_bill_types_company_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_types');
    }
};
