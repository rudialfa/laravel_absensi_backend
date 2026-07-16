<?php

namespace App\Models;

use App\Models\ShiftGroup;
use App\Models\UserShiftOverride;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Auth\Passwords\CanResetPassword;
// use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, CanResetPassword;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'salary' => 'decimal:2',
    ];


    // kode
    // ═══════════════════════════════════════════════════════════════════
    // ACCESSOR
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Tipe aplikasi berdasarkan company type.
     * Contoh: 'company', 'pesantren', 'school'
     */
    public function getAppTypeAttribute(): ?string
    {
        return $this->company?->type;
    }

    /**
     * Key untuk routing dashboard.
     * Contoh: 'pesantren.ustadz', 'company.hr', 'company.employee'
     */
    public function getDashboardKeyAttribute(): ?string
    {
        return $this->company?->type . '.' . $this->role;
    }

    public function isBillingManager(): bool
    {
        if (! $this->company) {
            return false;
        }

        return $this->role === $this->company->billingRole();
    }


    // ═══════════════════════════════════════════════════════════════════
    // RELASI — UMUM
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Pesantren / perusahaan tempat user ini terdaftar.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ═══════════════════════════════════════════════════════════════════
    // RELASI — HR / COMPANY
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Semua record absensi milik user ini.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Laporan bulanan milik user ini.
     */
    public function monthlyReports(): HasMany
    {
        return $this->hasMany(MonthlyReport::class);
    }

    /**
     * Laporan harian milik user ini.
     */
    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    /**
     * Skor performa milik user ini.
     */
    public function performanceScores(): HasMany
    {
        return $this->hasMany(PerformanceScore::class);
    }

    /**
     * Shift group yang diikuti user ini (many-to-many).
     */
    public function shiftGroups()
    {
        return $this->belongsToMany(ShiftGroup::class, 'shift_group_users')
            ->withPivot(['start_date', 'end_date'])
            ->withTimestamps();
    }

    /**
     * Override shift individual untuk user ini.
     */
    public function shiftOverrides(): HasMany
    {
        return $this->hasMany(UserShiftOverride::class);
    }

    /**
     * Jadwal yang dimiliki / ditugaskan ke user ini.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'user_id');
    }

    /**
     * Jadwal yang dibuat oleh user ini (sebagai HR / creator).
     */
    public function createdSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'created_by');
    }

    /**
     * Jadwal yang diikuti user ini sebagai peserta.
     */
    public function scheduleParticipations(): HasMany
    {
        return $this->hasMany(ScheduleParticipant::class, 'user_id');
    }

    // ═══════════════════════════════════════════════════════════════════
    // RELASI — PESANTREN (MutabaahYaumiyah)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * [Santri] Semua riwayat ngaji milik santri ini.
     *
     * Contoh:
     *   $santri->mutabaahSebagaiSantri()->bulan(3, 2026)->get();
     *   $santri->mutabaahSebagaiSantri()->hariIni()->get();
     */
    public function mutabaahSebagaiSantri(): HasMany
    {
        return $this->hasMany(MutabaahYaumiyah::class, 'santri_id');
    }

    /**
     * [Ustadz] Semua sesi ngaji yang diajar oleh ustadz ini.
     *
     * Contoh:
     *   $ustadz->mutabaahSebagaiUstadz()->hariIni()->get();
     *   $ustadz->mutabaahSebagaiUstadz()->bulan(3, 2026)->count();
     */
    public function mutabaahSebagaiUstadz(): HasMany
    {
        return $this->hasMany(MutabaahYaumiyah::class, 'ustadz_id');
    }

    /**
     * [Ustadz] Semua sesi yang sudah diparaf oleh ustadz ini.
     */
    public function mutabaahYangDiparaf(): HasMany
    {
        return $this->hasMany(MutabaahYaumiyah::class, 'signed_by');
    }

    /**
     * [Santri] Record ngaji terakhir — posisi terkini santri.
     * Berguna untuk auto-fill jilid & halaman saat ustadz input sesi baru.
     *
     * Contoh:
     *   $santri->progressNgaji;           // → MutabaahYaumiyah|null
     *   $santri->progressNgaji->jilid;    // → 2
     *   $santri->progressNgaji->halaman_dari; // → 15
     */
    public function progressNgaji()
    {
        return $this->hasOne(MutabaahYaumiyah::class, 'santri_id')
            ->latestOfMany();
    }
}
