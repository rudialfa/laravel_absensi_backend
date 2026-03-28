<?php

namespace App\Models;

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
 
    // ── Konstanta nilai ───────────────────────────────────────────────
 
    // Nilai yang berarti LANJUT (naik halaman)
    const NILAI_LANJUT = ['A+', 'A', 'A-', 'B+', 'B'];
 
    // Nilai yang berarti ULANG (tetap di halaman yang sama)
    const NILAI_ULANG  = ['B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-'];
 
    const SEMUA_NILAI  = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-'];
 
    const SEMUA_SESI   = ['pagi', 'sore'];
 
    const SEMUA_KITAB  = ['iqro', 'quran'];
 
    // ── Helper: hitung is_lanjut dari keterangan ──────────────────────
 
    public static function hitungIsLanjut(string $keterangan): bool
    {
        return in_array($keterangan, self::NILAI_LANJUT);
    }
 
    // ── Boot: auto-set is_lanjut saat create/update ───────────────────
 
    protected static function booted(): void
    {
        static::saving(function (MutabaahYaumiyah $model) {
            // Jika is_lanjut tidak di-override secara eksplisit,
            // hitung otomatis dari keterangan
            if (! $model->isDirty('is_lanjut') && $model->keterangan) {
                $model->is_lanjut = self::hitungIsLanjut($model->keterangan);
            }
        });
    }
 
    // ── Relasi ────────────────────────────────────────────────────────
 
    /**
     * Pesantren (company) tempat ngaji berlangsung.
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
     * (dipilih saat input, bisa beda tiap sesi)
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
 
    // ── Scope ─────────────────────────────────────────────────────────
 
    /** Filter by company */
    public function scopeOfCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
 
    /** Filter by santri */
    public function scopeOfSantri($query, int $santriId)
    {
        return $query->where('santri_id', $santriId);
    }
 
    /** Filter by ustadz */
    public function scopeOfUstadz($query, int $ustadzId)
    {
        return $query->where('ustadz_id', $ustadzId);
    }
 
    /** Hanya yang sudah diparaf */
    public function scopeSudahParaf($query)
    {
        return $query->whereNotNull('signed_by');
    }
 
    /** Hanya yang belum diparaf */
    public function scopeBelumParaf($query)
    {
        return $query->whereNull('signed_by');
    }
 
    /** Filter by kitab */
    public function scopeKitab($query, string $kitab)
    {
        return $query->where('kitab', $kitab);
    }
 
    /** Filter by tanggal hari ini */
    public function scopeHariIni($query)
    {
        return $query->whereDate('tanggal', today());
    }
 
    /** Filter by bulan & tahun */
    public function scopeBulan($query, int $bulan, int $tahun)
    {
        return $query->whereMonth('tanggal', $bulan)
                     ->whereYear('tanggal', $tahun);
    }
 
    /** Hanya yang lanjut */
    public function scopeLanjut($query)
    {
        return $query->where('is_lanjut', true);
    }
 
    /** Hanya yang mengulang */
    public function scopeUlang($query)
    {
        return $query->where('is_lanjut', false);
    }
 
    // ── Accessor ──────────────────────────────────────────────────────
 
    /**
     * Apakah sesi ini sudah diparaf?
     */
    public function getSudahDiparafAttribute(): bool
    {
        return ! is_null($this->signed_by);
    }
 
    /**
     * Label halaman yang dibaca.
     * Contoh: "Hal. 5" atau "Hal. 5–7"
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
     * Contoh: "Iqro Jilid 2 – Hal. 5–7"
     */
    public function getLabelPosisiAttribute(): string
    {
        $kitab = ucfirst($this->kitab);
        return "{$kitab} Jilid {$this->jilid} – {$this->label_halaman}";
    }
}
