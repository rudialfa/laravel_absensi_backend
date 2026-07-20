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
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();

            // Kategori bantuan, contoh: "Absensi", "Pembayaran", "Akun", "Umum"
            $table->string('category');

            // Judul / pertanyaan, contoh: "Bagaimana cara reset password?"
            $table->string('title');

            // Isi jawaban (rich text / html dari editor)
            $table->longText('content');

            // Superadmin yang menulis/mengelola artikel ini
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Urutan tampil dalam satu kategori
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Draft belum tayang ke tenant, published sudah bisa dilihat semua tenant
            $table->boolean('is_published')->default(true);

            // Counter seberapa sering artikel ini dibuka (opsional, buat lihat FAQ paling laku)
            $table->unsignedInteger('view_count')->default(0);

            $table->timestamps();

            $table->index(['category', 'is_published'], 'idx_help_category_published');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
