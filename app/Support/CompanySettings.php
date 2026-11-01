<?php

namespace App\Support;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Arr;

/**
 * Baca & simpan pengaturan hari kerja + notifikasi per company.
 *
 * Disimpan di kolom JSON companies.work_days & companies.notification_settings.
 * Kalau kosong, otomatis pakai DEFAULT di bawah — jadi company lama tetap jalan
 * tanpa perlu diisi dulu.
 *
 * Pemakaian:
 *   CompanySettings::workDays($company)             // [1,2,3,4,5]
 *   CompanySettings::isWorkDay($company, $date)     // true / false
 *   CompanySettings::notif($company, 'checkin_reminder.minutes_before')  // 15
 */
class CompanySettings
{
    /** ISO: 1=Senin ... 7=Minggu */
    public const DEFAULT_WORK_DAYS = [1, 2, 3, 4, 5];

    public const DAY_LABELS = [
        1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis',
        5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
    ];

    public const DEFAULT_NOTIFICATION = [
        // Karyawan — pengingat absen
        'checkin_reminder' => [
            'enabled'        => true,
            'minutes_before' => 15,   // 15 menit sebelum jam masuk
        ],
        'late_reminder' => [
            'enabled'       => true,
            'minutes_after' => 30,    // 30 menit setelah jam masuk, kalau belum absen
        ],
        'checkout_reminder' => [
            'enabled'       => true,
            'minutes_after' => 0,     // tepat di jam pulang
        ],

        // Karyawan — shift & libur
        'shift_tomorrow' => [
            'enabled' => true,
            'time'    => '20:00',     // malam sebelumnya
        ],
        'holiday_reminder' => [
            'enabled' => true,
            'time'    => '17:00',     // H-1
        ],

        // HR — persetujuan
        'hr_instant_approval' => [
            'enabled' => true,        // notif setiap ada pengajuan baru
        ],
        'hr_daily_digest' => [
            'enabled' => true,
            'time'    => '08:00',     // ringkasan pengajuan yang belum diputuskan
        ],
    ];

    // ----------------------------------------------------------
    // HARI KERJA
    // ----------------------------------------------------------

    private static function decode($value): ?array
    {
        if (is_array($value)) return $value;
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    public static function workDays(Company $company): array
    {
        $days = self::decode($company->getAttribute('work_days'));

        if (!$days) {
            return self::DEFAULT_WORK_DAYS;
        }

        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);

        return array_values(array_filter($days, fn ($d) => $d >= 1 && $d <= 7));
    }

    public static function isWorkDay(Company $company, $date): bool
    {
        $d = $date instanceof Carbon ? $date : Carbon::parse($date);

        return in_array($d->dayOfWeekIso, self::workDays($company), true);
    }

    public static function workDaysLabel(Company $company): string
    {
        $days = self::workDays($company);

        if ($days === [1, 2, 3, 4, 5])    return 'Senin – Jumat';
        if ($days === [1, 2, 3, 4, 5, 6]) return 'Senin – Sabtu';
        if ($days === [1, 2, 3, 4, 5, 6, 7]) return 'Setiap hari';

        return implode(', ', array_map(fn ($d) => self::DAY_LABELS[$d], $days));
    }

    // ----------------------------------------------------------
    // NOTIFIKASI
    // ----------------------------------------------------------

    /** Pengaturan lengkap (default + yang sudah diubah HR) */
    public static function notification(Company $company): array
    {
        $saved = self::decode($company->getAttribute('notification_settings')) ?? [];

        return array_replace_recursive(self::DEFAULT_NOTIFICATION, $saved);
    }

    /** Ambil satu nilai, contoh: notif($company, 'hr_daily_digest.time') */
    public static function notif(Company $company, string $key, $default = null)
    {
        return Arr::get(self::notification($company), $key, $default);
    }

    // ----------------------------------------------------------
    // SIMPAN
    // ----------------------------------------------------------

    public static function save(Company $company, ?array $workDays, ?array $notification): void
    {
        if ($workDays !== null) {
            $workDays = array_values(array_unique(array_map('intval', $workDays)));
            sort($workDays);
            $company->forceFill(['work_days' => json_encode($workDays)]);
        }

        if ($notification !== null) {
            // Hanya simpan kunci yang dikenal (abaikan kiriman asing)
            $merged = array_replace_recursive(self::notification($company), $notification);
            $clean  = array_intersect_key($merged, self::DEFAULT_NOTIFICATION);

            $company->forceFill(['notification_settings' => json_encode($clean)]);
        }

        $company->save();
    }

    /** Format untuk response API */
    public static function toApiArray(Company $company): array
    {
        $days = self::workDays($company);

        return [
            'work_days'        => $days,
            'work_days_label'  => self::workDaysLabel($company),
            'work_days_detail' => collect(self::DAY_LABELS)->map(fn ($label, $num) => [
                'day'      => $num,
                'label'    => $label,
                'is_work'  => in_array($num, $days, true),
            ])->values(),
            'time_in'          => $company->time_in,
            'time_out'         => $company->time_out,
            'notification'     => self::notification($company),
        ];
    }
}