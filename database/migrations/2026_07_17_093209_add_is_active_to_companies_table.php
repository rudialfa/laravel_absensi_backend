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
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('type');
            }
            if (!Schema::hasColumn('companies', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('companies', 'suspend_reason')) {
                $table->string('suspend_reason')->nullable()->after('suspended_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'suspended_at', 'suspend_reason']);
        });
    }
};
