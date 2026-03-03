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
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('date')->index();

            // Target pagi
            $table->text('target');

            // Pencapaian sore
            $table->text('achievement')->nullable();

            $table->boolean('is_achieved')->default(false);

            $table->text('reason_not_achieved')->nullable();

            $table->string('attachment')->nullable(); // bukti foto optional

            $table->timestamps();

            $table->unique(
                ['company_id', 'user_id', 'date'],
                'uniq_daily_company_user_date'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
