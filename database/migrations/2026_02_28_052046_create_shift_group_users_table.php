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
        Schema::create('shift_group_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_group_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            // opsional: masa berlaku membership group (kalau user pindah departemen sementara)
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamps();

            $table->unique(['shift_group_id', 'user_id']);

            $table->foreign('shift_group_id')->references('id')->on('shift_groups')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_group_users');
    }
};
