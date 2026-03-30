<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;
    protected $guarded = [];

    public const TYPES = [
        'company',
        'school',
        'pesantren',
        'hospital',
        'government',
        'factory',
        'retail',
        'restaurant',
        'training',
        'organization',
        'transport',
        'remote',
        'sports'
    ];


    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function monthlyReports()
    {
        return $this->hasMany(MonthlyReport::class);
    }

        // ═══════════════════════════════════════════════════════════════════
    // RELASI — PESANTREN (MutabaahYaumiyah)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Semua record ngaji (mutabaah yaumiyah) di pesantren ini.
     *
     * Contoh:
     *   $pesantren->mutabaahYaumiyahs()->hariIni()->get();
     *   $pesantren->mutabaahYaumiyahs()->bulan(3, 2026)->count();
     *   $pesantren->mutabaahYaumiyahs()->ofSantri($santriId)->get();
     */
    public function mutabaahYaumiyahs()
    {
        return $this->hasMany(MutabaahYaumiyah::class);
    }
}
