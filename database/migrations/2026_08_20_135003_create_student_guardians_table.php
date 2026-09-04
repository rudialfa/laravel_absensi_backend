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
        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();


            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            // User dengan role = 'wali'
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('relationship', ['ayah', 'ibu', 'wali_lain'])
                ->default('wali_lain');

            // Kontak utama untuk notifikasi (kalau ada >1 wali per murid)
            $table->boolean('is_primary')->default(false);

            // Boleh mengajukan izin/sakit atas nama murid ini
            $table->boolean('can_submit_permission')->default(true);

            $table->timestamps();

            // Satu wali tidak boleh terdaftar dobel untuk murid yang sama
            $table->unique(
                ['student_id', 'user_id'],
                'uniq_guardian_student_user'
            );

            // Query utama: portal wali — semua anak dari 1 akun
            $table->index('user_id', 'idx_guardian_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
    }
};
