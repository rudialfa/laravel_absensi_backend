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
        Schema::create('dormitory_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dormitory_id')
                ->constrained('dormitories')
                ->cascadeOnDelete();

            // Nama/nomor kamar, contoh: "Kamar 1", "A-101"
            $table->string('name');

            // Kapasitas maksimal santri di kamar ini — dipakai untuk
            // validasi saat penempatan (tidak boleh melebihi kapasitas)
            $table->unsignedTinyInteger('capacity')->default(4);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(
                ['dormitory_id', 'is_active'],
                'idx_rooms_dormitory_active'
            );

            // Nama kamar unik per asrama
            $table->unique(
                ['dormitory_id', 'name'],
                'uniq_rooms_dormitory_name'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dormitory_rooms');
    }
};
