<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Controller & model Note memakai kolom created_by dan is_read,
     * tetapi kolom itu TIDAK PERNAH dibuat di migration tabel notes.
     * Akibatnya:
     *  - HR simpan catatan  → error "Unknown column 'created_by'"
     *  - Karyawan buka / tandai dibaca → error "Unknown column 'is_read'"
     *  - Summary catatan    → error
     *
     * Kalau kolom ternyata sudah ada di database Anda, migration ini otomatis melewatinya.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            if (!Schema::hasColumn('notes', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('notes', 'is_read')) {
                $table->boolean('is_read')->default(false)->after('type');
            }

            if (!Schema::hasColumn('notes', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('is_read');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['is_read', 'read_at']);
        });
    }
};
