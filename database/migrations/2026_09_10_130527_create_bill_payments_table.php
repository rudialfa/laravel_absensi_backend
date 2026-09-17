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
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_bill_id')->constrained('student_bills')->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'transfer']);
            $table->date('paid_at');

            // Nomor kwitansi auto-generate, contoh: "KWT-2027-000123"
            $table->string('receipt_number')->unique();

            $table->text('notes')->nullable();

            // Admin yang mencatat pembayaran ini
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->index('student_bill_id', 'idx_payments_bill');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
    }
};
