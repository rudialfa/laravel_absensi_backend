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
        Schema::table('loans', function (Blueprint $table) {

            $table->string('purpose_category')->after('amount');
            $table->text('purpose_note')->nullable()->after('purpose_category');

            // Tipe pembayaran
            // salary_deduction = potong gaji otomatis (pending sampai payroll aktif)
            // scheduled_date   = bayar sendiri tiap tanggal X
            // lump_sum         = sekali bayar lunas
            $table->enum('payment_type', ['salary_deduction', 'scheduled_date', 'lump_sum'])
                ->after('installments');

            // Hanya diisi jika payment_type = scheduled_date (nilai: 1-28)
            $table->unsignedTinyInteger('payment_date_of_month')
                ->nullable()
                ->after('payment_type');

            // Nominal cicilan per bulan (amount / installments)
            // HR bisa edit saat approval jika ada negosiasi
            $table->decimal('monthly_installment', 12, 2)
                ->default(0)
                ->after('payment_date_of_month');

            // Approval
            $table->foreignId('approved_by')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_note')->nullable()->after('approved_at');

            // Dokumen pendukung dari employee (opsional)
            $table->string('attachment')->nullable()->after('approval_note');

            // Index
            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['company_id', 'status']);
            $table->dropIndex(['user_id', 'status']);

            $table->dropColumn([
                'company_id',
                'purpose_category',
                'purpose_note',
                'payment_type',
                'payment_date_of_month',
                'monthly_installment',
                'approved_by',
                'approved_at',
                'approval_note',
                'attachment',
            ]);
        });
    }
};
