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
        Schema::create('shift_group_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('shift_group_id')->index();
            $table->unsignedBigInteger('shift_id')->index();

            // periode berlaku
            $table->date('start_date')->index();
            $table->date('end_date')->nullable()->index();

            // siapa yang set (HR/Admin)
            $table->unsignedBigInteger('created_by')->nullable()->index(); // users.id (HR)
            $table->text('note')->nullable();

            // rotasi (opsional, belum dipakai juga nggak apa)
            $table->boolean('is_rotation')->default(false);
            $table->unsignedInteger('rotation_cycle_days')->nullable(); // contoh: 14

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('shift_group_id')->references('id')->on('shift_groups')->cascadeOnDelete();
            $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_group_assignments');
    }
};
