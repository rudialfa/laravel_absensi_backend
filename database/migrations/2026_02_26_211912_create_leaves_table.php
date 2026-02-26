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
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // periode cuti
            $table->date('start_date');
            $table->date('end_date');

            // jenis cuti (bisa kamu tambah)
            $table->enum('type', ['annual', 'sick', 'maternity', 'important', 'other'])
                ->default('annual');

            $table->text('reason')->nullable();
            $table->string('attachment')->nullable(); // bukti file (opsional)

            // workflow approval
            $table->enum('status', ['pending', 'approved', 'rejected', 'canceled'])
                ->default('pending');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status'], 'idx_leave_company_status');
            $table->index(['user_id', 'start_date'], 'idx_leave_user_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
