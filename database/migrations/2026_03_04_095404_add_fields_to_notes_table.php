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
        Schema::table('notes', function (Blueprint $table) {
            if (!Schema::hasColumn('notes', 'is_read')) {
                $table->boolean('is_read')->default(false)->after('type');
            }
            if (!Schema::hasColumn('notes', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('is_read')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['is_read', 'created_by']);
        });
    }
};
