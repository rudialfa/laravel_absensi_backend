<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // va_payments
        DB::statement("ALTER TABLE va_payments MODIFY bank VARCHAR(20) NOT NULL");

        // va_payment_logs
        DB::statement("ALTER TABLE va_payment_logs MODIFY bank VARCHAR(20) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE va_payments MODIFY bank ENUM('bca','mandiri') NOT NULL");
        DB::statement("ALTER TABLE va_payment_logs MODIFY bank ENUM('bca','mandiri') NOT NULL");
    }
};
