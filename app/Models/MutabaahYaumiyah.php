<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MutabaahYaumiyah extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $fillable = [
        'company_id',
        'santri_id',
        'ustadz_id',
        'signed_by',
        'signed_at',
        'kitab',
        'jilid',
        'halaman_dari',
        'halaman_sampai',
        'tanggal',
        'sesi',
        'keterangan',
        'is_lanjut',
        'catatan',
    ];

    protected $casts = [
        'tanggal'        => 'date',
        'signed_at'      => 'datetime',
        'is_lanjut'      => 'boolean',
        'jilid'          => 'integer',
        'halaman_dari'   => 'integer',
        'halaman_sampai' => 'integer',
    ];

    // ═══════════════════════════════════════════════════════════════════
    // KONSTANTA
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Nilai yang berarti LANJUT — naik ke halaman berikutnya.
     */
    const NILAI_LANJUT = ['A+', 'A', 'A-', 'B+', 'B'];

    /**
     * Nilai yang berarti ULANG — tetap di halaman yang sama.
     */
    const NILAI_ULANG = ['B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-'];

    /**
     * Semua nilai yang valid (urutan terbaik ke terburuk).
     */
    const SEMUA_NILAI = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-'];

    /**
     * Sesi ngaji yang tersedia.
     */
    const SEMUA_SESI = ['pagi', 'sore'];

    /**
     * Jenis kitab yang tersedia.
     */
    const SEMUA_KITAB = ['iqro', 'quran'];

    // ═══════════════════════════════════════════════════════════════════
    // BOOT — auto-set is_lanjut dari keterangan
    // ═══════════════════════════════════════════════════════════════════

    protected static function booted(): void
    {
        static::saving(function (MutabaahYaumiyah $model) {
            // Hitung is_lanjut otomatis dari keterangan,
            // KECUALI jika ustadz sengaja override is_lanjut secara eksplisit
            // (isDirty('is_lanjut') = true berarti di-set manual dari request)
            if (! $model->isDirty('is_lanjut') && $model->keterangan) {
                $model->is_lanjut = self::hitungIsLanjut($model->keterangan);
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPER STATIS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Hitung apakah nilai ini berarti lanjut atau ulang.
     *
     * Contoh:
     *   MutabaahYaumiyah::hitungIsLanjut('B+');  // → true
     *   MutabaahYaumiyah::hitungIsLanjut('C');   // → false
     */
    public static function hitungIsLanjut(string $keterangan): bool
    {
        return in_array($keterangan, self::NILAI_LANJUT);
    }

    /**
     * Hitung halaman berikutnya berdasarkan record terakhir santri.
     * Jika is_lanjut = true  → halaman naik (halaman_sampai + 1 atau halaman_dari + 1)
     * Jika is_lanjut = false → halaman tetap (halaman_dari tidak berubah)
     *
     * Contoh:
     *   MutabaahYaumiyah::halamanBerikutnya($record); // → 16
     */
    public static function halamanBerikutnya(self $record): int
    {
        if (! $record->is_lanjut) {
            return $record->halaman_dari;
        }

        return ($record->halaman_sampai ?? $record->halaman_dari) + 1;
    }

    // ═══════════════════════════════════════════════════════════════════
    // RELASI
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Pesantren tempat sesi ini berlangsung.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Santri yang ngaji.
     */
    public function santri()
    {
        return $this->belongsTo(User::class, 'santri_id');
    }

    /**
     * Ustadz yang mengajar sesi ini.
     */
    public function ustadz()
    {
        return $this->belongsTo(User::class, 'ustadz_id');
    }

    /**
     * Ustadz yang memberi paraf.
     * null = belum diparaf.
     */
    public function penandatangan()
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    // ═══════════════════════════════════════════════════════════════════
    // SCOPE
    // ═══════════════════════════════════════════════════════════════════

    /** Filter by company */
    public function scopeOfCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /** Filter by santri */
    public function scopeOfSantri(Builder $query, int $santriId): Builder
    {
        return $query->where('santri_id', $santriId);
    }

    /** Filter by ustadz */
    public function scopeOfUstadz(Builder $query, int $ustadzId): Builder
    {
        return $query->where('ustadz_id', $ustadzId);
    }

    /** Hanya yang sudah diparaf */
    public function scopeSudahParaf(Builder $query): Builder
    {
        return $query->whereNotNull('signed_by');
    }

    /** Hanya yang belum diparaf */
    public function scopeBelumParaf(Builder $query): Builder
    {
        return $query->whereNull('signed_by');
    }

    /** Filter by kitab */
    public function scopeKitab(Builder $query, string $kitab): Builder
    {
        return $query->where('kitab', $kitab);
    }

    /** Filter by jilid */
    public function scopeJilid(Builder $query, int $jilid): Builder
    {
        return $query->where('jilid', $jilid);
    }

    /** Filter by tanggal hari ini */
    public function scopeHariIni(Builder $query): Builder
    {
        return $query->whereDate('tanggal', today());
    }

    /** Filter by bulan & tahun */
    public function scopeBulan(Builder $query, int $bulan, int $tahun): Builder
    {
        return $query->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun);
    }

    /** Filter by range tanggal */
    public function scopeTanggalAntara(Builder $query, string $dari, string $sampai): Builder
    {
        return $query->whereBetween('tanggal', [$dari, $sampai]);
    }

    /** Hanya yang lanjut (naik halaman) */
    public function scopeLanjut(Builder $query): Builder
    {
        return $query->where('is_lanjut', true);
    }

    /** Hanya yang mengulang */
    public function scopeUlang(Builder $query): Builder
    {
        return $query->where('is_lanjut', false);
    }

    /** Filter by sesi */
    public function scopeSesi(Builder $query, string $sesi): Builder
    {
        return $query->where('sesi', $sesi);
    }

    // ═══════════════════════════════════════════════════════════════════
    // ACCESSOR
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Apakah sesi ini sudah diparaf?
     *
     * Contoh: $record->sudah_diparaf // → true / false
     */
    public function getSudahDiparafAttribute(): bool
    {
        return ! is_null($this->signed_by);
    }

    /**
     * Label halaman yang dibaca.
     *
     * Contoh:
     *   halaman_dari=5, halaman_sampai=null → "Hal. 5"
     *   halaman_dari=5, halaman_sampai=7   → "Hal. 5–7"
     */
    public function getLabelHalamanAttribute(): string
    {
        if ($this->halaman_sampai && $this->halaman_sampai !== $this->halaman_dari) {
            return "Hal. {$this->halaman_dari}–{$this->halaman_sampai}";
        }

        return "Hal. {$this->halaman_dari}";
    }

    /**
     * Label lengkap posisi bacaan.
     *
     * Contoh:
     *   kitab=iqro, jilid=2, hal 5–7 → "Iqro Jilid 2 – Hal. 5–7"
     *   kitab=quran, jilid=7, hal 15 → "Quran Jilid 7 – Hal. 15"
     */
    public function getLabelPosisiAttribute(): string
    {
        $kitab = ucfirst($this->kitab);

        return "{$kitab} Jilid {$this->jilid} – {$this->label_halaman}";
    }

    /**
     * Nama sesi dengan format kapital.
     *
     * Contoh: 'pagi' → 'Pagi'
     */
    public function getLabelSesiAttribute(): string
    {
        return ucfirst($this->sesi);
    }

    /**
     * Warna badge untuk keterangan (untuk kebutuhan frontend).
     *
     * Contoh: $record->warna_keterangan // → 'green' / 'yellow' / 'red'
     */
    public function getWarnaKeteranganAttribute(): string
    {
        return match (true) {
            in_array($this->keterangan, ['A+', 'A', 'A-'])        => 'green',
            in_array($this->keterangan, ['B+', 'B'])              => 'blue',
            in_array($this->keterangan, ['B-', 'C+', 'C', 'C-']) => 'yellow',
            default                                                => 'red',
        };
    }
}
