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
        Schema::create('dormitories', function (Blueprint $table) {
            $table->id();

            // Sekolah pemilik asrama ini
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Nama asrama, contoh: "Asrama Putra 1", "Asrama Az-Zahra"
            $table->string('name');

            // Asrama khusus putra atau putri
            $table->enum('gender', ['L', 'P']);

            // Pengasuh/penanggung jawab asrama — reuse tabel users yang sudah
            // ada (role guru/admin), bukan role baru, biar tidak nambah
            // kompleksitas role di tahap ini
            $table->foreignId('pengasuh_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('address')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(
                ['company_id', 'gender', 'is_active'],
                'idx_dormitories_company_gender_active'
            );

            // Nama asrama unik per sekolah
            $table->unique(
                ['company_id', 'name'],
                'uniq_dormitories_company_name'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dormitories');
    }
};
