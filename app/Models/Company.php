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

    public function subscriptions()
    {
        return $this->hasMany(CompanySubscription::class, 'company_id');
    }

    public function activeSubscription()
    {
        return $this->hasOne(CompanySubscription::class, 'company_id')
            ->whereIn('status', ['trial', 'active'])
            ->latest('expires_at');
    }

    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class, 'company_id');
    }

    public function vaPayments()
    {
        return $this->hasMany(VaPayment::class, 'company_id');
    }

    // Cek cepat apakah fitur boleh diakses
    public function isSubscriptionActive(): bool
    {
        return $this->activeSubscription?->isActive() ?? false;
    }

    // Cek apakah company pernah trial
    public function hasUsedTrial(): bool
    {
        return $this->subscriptions()
            ->where('has_used_trial', true)
            ->exists();
    }

    public function billingRole(): string
    {
        return config("subscription.billing_roles.{$this->type}")
            ?? config('subscription.default_billing_role', 'hr');
    }

    public function classes()
    {
        return $this->hasMany(ClassRoom::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function attendanceDevices()
    {
        return $this->hasMany(AttendanceDevice::class);
    }

    public function studentAttendances()
    {
        return $this->hasMany(StudentAttendance::class);
    }
}
