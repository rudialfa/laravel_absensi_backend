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
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // optional: terkait attendance
            $table->foreignId('attendance_id')
                ->nullable()
                ->constrained('attendances')
                ->nullOnDelete();

            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');

            $table->unsignedInteger('minutes')->default(0);

            $table->text('reason')->nullable();
            $table->string('evidence_image')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'canceled'])
                ->default('pending');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'date'], 'idx_ot_company_date');
            $table->index(['user_id', 'date'], 'idx_ot_user_date');
            $table->index(['status', 'date'], 'idx_ot_status_date');

            // jika kamu mau multiple lembur dalam 1 hari, hapus unique ini
            $table->unique(['company_id', 'user_id', 'date'], 'uniq_ot_company_user_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
