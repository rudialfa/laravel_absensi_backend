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
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('shift_id')
                ->constrained('shifts')
                ->onDelete('cascade');

            // penugasan per tanggal (paling simpel & jelas)
            $table->date('date');

            // optional: catatan HR
            $table->string('note')->nullable();

            $table->timestamps();

            // biar 1 user hanya punya 1 shift per tanggal per company
            $table->unique(['company_id', 'user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
