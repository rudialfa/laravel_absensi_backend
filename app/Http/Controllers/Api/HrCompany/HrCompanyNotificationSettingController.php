<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Support\CompanySettings;
use Illuminate\Http\Request;

/**
 * Pengaturan hari kerja & notifikasi — diatur oleh HR masing-masing perusahaan.
 *
 * GET /api/company/hr/settings/notifications
 * PUT /api/company/hr/settings/notifications
 */
class HrCompanyNotificationSettingController extends Controller
{
    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function company()
    {
        $company = auth()->user()->company;

        if (!$company) {
            abort(response()->json(['status' => false, 'message' => 'Company tidak ditemukan'], 422));
        }

        return $company;
    }

    // ============================================================
    // GET /api/company/hr/settings/notifications
    // ============================================================
    public function show()
    {
        $this->ensureHr();

        return response()->json([
            'status'  => true,
            'message' => 'Pengaturan hari kerja & notifikasi',
            'data'    => CompanySettings::toApiArray($this->company()),
        ]);
    }

    // ============================================================
    // PUT /api/company/hr/settings/notifications
    //
    // Semua field opsional — kirim yang mau diubah saja. Contoh:
    // {
    //   "work_days": [1,2,3,4,5,6],
    //   "notification": {
    //     "checkin_reminder":    { "enabled": true, "minutes_before": 10 },
    //     "hr_instant_approval": { "enabled": false },
    //     "hr_daily_digest":     { "time": "07:30" }
    //   }
    // }
    // ============================================================
    public function update(Request $request)
    {
        $this->ensureHr();

        $time = ['nullable', 'date_format:H:i'];

        $request->validate([
            'work_days'   => ['nullable', 'array', 'min:1', 'max:7'],
            'work_days.*' => ['integer', 'between:1,7'],

            'notification'                                    => ['nullable', 'array'],
            'notification.checkin_reminder.enabled'           => ['nullable', 'boolean'],
            'notification.checkin_reminder.minutes_before'    => ['nullable', 'integer', 'between:0,180'],
            'notification.late_reminder.enabled'              => ['nullable', 'boolean'],
            'notification.late_reminder.minutes_after'        => ['nullable', 'integer', 'between:5,240'],
            'notification.checkout_reminder.enabled'          => ['nullable', 'boolean'],
            'notification.checkout_reminder.minutes_after'    => ['nullable', 'integer', 'between:0,180'],
            'notification.shift_tomorrow.enabled'             => ['nullable', 'boolean'],
            'notification.shift_tomorrow.time'                => $time,
            'notification.holiday_reminder.enabled'           => ['nullable', 'boolean'],
            'notification.holiday_reminder.time'              => $time,
            'notification.hr_instant_approval.enabled'        => ['nullable', 'boolean'],
            'notification.hr_daily_digest.enabled'            => ['nullable', 'boolean'],
            'notification.hr_daily_digest.time'               => $time,
        ], [
            'work_days.min'      => 'Pilih minimal 1 hari kerja.',
            'work_days.*.between'=> 'Hari kerja harus angka 1 (Senin) sampai 7 (Minggu).',
            '*.date_format'      => 'Format jam harus HH:mm, contoh 08:00.',
        ]);

        // Ubah "true"/"false"/"1"/"0" jadi boolean asli, dan angka jadi integer
        $notification = $request->input('notification');
        if (is_array($notification)) {
            array_walk_recursive($notification, function (&$v, $k) {
                if ($k === 'enabled') {
                    $v = filter_var($v, FILTER_VALIDATE_BOOLEAN);
                } elseif (in_array($k, ['minutes_before', 'minutes_after'], true)) {
                    $v = (int) $v;
                }
            });
        }

        $company = $this->company();

        CompanySettings::save($company, $request->input('work_days'), $notification);

        return response()->json([
            'status'  => true,
            'message' => 'Pengaturan berhasil disimpan',
            'data'    => CompanySettings::toApiArray($company->fresh()),
        ]);
    }

    // ============================================================
    // POST /api/company/hr/settings/notifications/reset
    // Kembalikan semua ke default
    // ============================================================
    public function reset()
    {
        $this->ensureHr();

        $company = $this->company();
        $company->forceFill(['work_days' => null, 'notification_settings' => null])->save();

        return response()->json([
            'status'  => true,
            'message' => 'Pengaturan dikembalikan ke default',
            'data'    => CompanySettings::toApiArray($company->fresh()),
        ]);
    }
}