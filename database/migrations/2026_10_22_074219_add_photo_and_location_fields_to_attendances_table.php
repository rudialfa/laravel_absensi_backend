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
        Schema::table('attendances', function (Blueprint $table) {
            $table->time('time_in')->nullable()->change();
            $table->string('latlon_in')->nullable()->change();

            if (!Schema::hasColumn('attendances', 'photo_in')) {
                $table->string('photo_in')->nullable()->after('latlon_out');
            }
            if (!Schema::hasColumn('attendances', 'photo_out')) {
                $table->string('photo_out')->nullable()->after('photo_in');
            }

            if (!Schema::hasColumn('attendances', 'distance_in_m')) {
                $table->unsignedInteger('distance_in_m')->nullable()->after('photo_out');
            }
            if (!Schema::hasColumn('attendances', 'distance_out_m')) {
                $table->unsignedInteger('distance_out_m')->nullable()->after('distance_in_m');
            }

            if (!Schema::hasColumn('attendances', 'is_outside_radius_in')) {
                $table->boolean('is_outside_radius_in')->default(false)->after('distance_out_m');
            }
            if (!Schema::hasColumn('attendances', 'is_outside_radius_out')) {
                $table->boolean('is_outside_radius_out')->default(false)->after('is_outside_radius_in');
            }

            // Izin yang memengaruhi absen ini (datang terlambat / dinas luar / pulang cepat)
            if (!Schema::hasColumn('attendances', 'permission_id')) {
                $table->foreignId('permission_id')
                    ->nullable()
                    ->after('is_outside_radius_out')
                    ->constrained('permissions')
                    ->nullOnDelete();
            }

            // Catatan otomatis, contoh: "Izin datang terlambat s/d 10:00 — antar anak ke RS"
            if (!Schema::hasColumn('attendances', 'note')) {
                $table->text('note')->nullable()->after('permission_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('permission_id');
            $table->dropColumn([
                'photo_in',
                'photo_out',
                'distance_in_m',
                'distance_out_m',
                'is_outside_radius_in',
                'is_outside_radius_out',
                'note',
            ]);
        });
    }
};
