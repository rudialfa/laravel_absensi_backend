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
        Schema::create('performance_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            $table->integer('total_targets')->default(0);
            $table->integer('targets_achieved')->default(0);

            $table->decimal('achievement_rate', 5, 2)->default(0);
            $table->decimal('final_score', 5, 2)->default(0);

            $table->timestamps();

            $table->unique(
                ['company_id', 'user_id', 'year', 'month'],
                'uniq_perf_company_user_month'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_scores');
    }
};
