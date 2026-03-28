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
        Schema::create('mutabaah_yaumiyahs', function (Blueprint $table) {
            $table->id();
          // ── Relasi utama ──────────────────────────────────────────
 
            // Pesantren
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();
 
            // Santri yang ngaji
            $table->foreignId('santri_id')
                ->constrained('users')
                ->cascadeOnDelete();
 
            // Ustadz yang mengajar sesi ini
            // (4 ustadz tersedia, santri bebas dipanggil siapa saja)
            $table->foreignId('ustadz_id')
                ->constrained('users')
                ->cascadeOnDelete();
 
            // ── Paraf ─────────────────────────────────────────────────
 
            // Ustadz yang memberi paraf (tap "Paraf" di aplikasi)
            // Ditampilkan di frontend dengan font brush/kaligrafi
            // null = belum diparaf
            $table->foreignId('signed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
 
            // Waktu paraf diberikan
            $table->timestamp('signed_at')->nullable();
 
            // ── Posisi bacaan ─────────────────────────────────────────
 
            // Jenis kitab: iqro (jilid 1–6) atau quran (mushaf)
            $table->enum('kitab', ['iqro', 'quran'])->default('iqro');
 
            // Jilid: 1–6 untuk iqro, 7 untuk al-quran
            $table->unsignedTinyInteger('jilid');
 
            // Halaman yang dibaca
            // iqro  : 1–44 (per jilid, tergantung edisi)
            // quran : 1–604 (halaman mushaf standar)
            $table->unsignedSmallInteger('halaman_dari');
            $table->unsignedSmallInteger('halaman_sampai')->nullable(); // jika lebih dari 1 halaman
 
            // ── Tanggal & sesi ────────────────────────────────────────
 
            $table->date('tanggal');
 
            // 2 sesi per hari: pagi dan sore
            // Unique constraint di bawah memastikan max 1 record per sesi per hari
            $table->enum('sesi', ['pagi', 'sore'])->default('pagi');
 
            // ── Penilaian ─────────────────────────────────────────────
            //
            // Skala nilai (sistem akademik):
            //
            //   A+  = Sangat sempurna    ┐
            //   A   = Sangat baik        │ → is_lanjut = true
            //   A-  = Baik sekali        │   (naik halaman)
            //   B+  = Baik lebih         │
            //   B   = Baik               ┘
            //   ─────────────────────────── cut-off
            //   B-  = Cukup baik         ┐
            //   C+  = Cukup lebih        │ → is_lanjut = false
            //   C   = Cukup              │   (mengulang halaman)
            //   C-  = Kurang cukup       │
            //   D+  = Kurang lebih       │
            //   D   = Belum lancar       │
            //   D-  = Belum bisa         ┘
            //
            $table->enum('keterangan', [
                'A+', 'A', 'A-',
                'B+', 'B',
                'B-',
                'C+', 'C', 'C-',
                'D+', 'D', 'D-',
            ]);
 
            // Naik halaman atau mengulang
            // true  = lanjut ke halaman berikutnya (A+, A, A-, B+, B)
            // false = mengulang halaman yang sama   (B-, C+, C, C-, D+, D, D-)
            //
            // Di-set OTOMATIS oleh Model::booted() dari keterangan.
            // Ustadz bisa kirim is_lanjut=true/false untuk override.
            $table->boolean('is_lanjut')->default(true);
 
            // Catatan tambahan dari ustadz (opsional)
            $table->text('catatan')->nullable();
 
            $table->timestamps();
 
            // ── Index ─────────────────────────────────────────────────
 
            // Query utama: rekap per santri per tanggal
            $table->index(
                ['company_id', 'santri_id', 'tanggal'],
                'idx_mutabaah_santri_tanggal'
            );
 
            // Rekap ustadz: siapa mengajar siapa, kapan
            $table->index(
                ['company_id', 'ustadz_id', 'tanggal'],
                'idx_mutabaah_ustadz_tanggal'
            );
 
            // Progress santri: posisi terakhir per kitab & jilid
            // Dipakai untuk auto-fill halaman berikutnya saat input
            $table->index(
                ['santri_id', 'kitab', 'jilid'],
                'idx_mutabaah_progress'
            );
 
            // ── Unique constraint ─────────────────────────────────────
            // 1 santri hanya boleh 1 record per sesi per hari
            // (max 2 record per hari: 1 pagi + 1 sore)
            $table->unique(
                ['company_id', 'santri_id', 'tanggal', 'sesi'],
                'uniq_mutabaah_santri_sesi'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mutabaah_yaumiyahs');
    }
};
