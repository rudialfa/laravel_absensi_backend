<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\CompanyHoliday;
use App\Models\Leaves;
use App\Models\MonthlyReport;
use App\Models\OvertimeRequest;
use App\Models\Permission;
use App\Models\Schedule;
use App\Models\ScheduleParticipant;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceRuleService;
use App\Services\HolidayNotifier;
use App\Services\NotificationService;
use App\Services\Notifiers\ScheduleNotifier;
use App\Support\CompanySettings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * SEMUA notifikasi terjadwal — dijalankan scheduler SETIAP MENIT.
 *
 *   ⏰ Pengingat absen masuk        (X menit sebelum jam masuk)
 *   ⚠️ "Anda belum absen masuk"     (X menit setelah jam masuk)
 *   🏠 Pengingat absen pulang       (di jam pulang)
 *   🔄 Shift besok berbeda          (malam sebelumnya)
 *   🔔 Besok libur                  (H-1)
 *   📋 Ringkasan pengajuan untuk HR (pagi)
 *   📅 Pengingat jadwal/tugas       (sesuai reminder_offsets jadwal)
 *
 * Jam & on/off mengikuti pengaturan HR masing-masing company
 * (GET/PUT /api/company/hr/settings/notifications).
 *
 * Aman dijalankan berkali-kali: setiap notifikasi dicek dulu supaya tidak terkirim dobel.
 *
 * TES tanpa menunggu jamnya (simulasi waktu):
 *   php artisan notifications:tick --at="2026-10-30 07:45"
 *   php artisan notifications:tick --at="2026-10-30 07:45" --dry-run   ← hanya tampilkan, tidak kirim
 */
class SendScheduledNotifications extends Command
{
    protected $signature = 'notifications:tick
                            {--at= : Simulasi waktu sekarang, contoh "2026-10-30 07:45"}
                            {--dry-run : Tampilkan saja, jangan kirim}';

    protected $description = 'Kirim semua notifikasi terjadwal (pengingat absen, shift, libur, ringkasan HR, jadwal)';

    /** Toleransi: kalau scheduler telat jalan, masih dikirim dalam X menit */
    private const WINDOW_MINUTES = 10;

    private const HARI  = [0 => 'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    private const BULAN = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private bool $dry = false;
    private int $total = 0;

    private NotificationService $notif;
    private AttendanceRuleService $rules;
    private HolidayNotifier $holidays;
    private ScheduleNotifier $schedules;

    // Dependency diambil di handle() (bukan constructor) supaya tidak ikut
    // dibuat setiap kali menjalankan perintah artisan lain.
    public function handle(
        NotificationService $notif,
        AttendanceRuleService $rules,
        HolidayNotifier $holidays,
        ScheduleNotifier $schedules,
    ): int {
        $this->notif     = $notif;
        $this->rules     = $rules;
        $this->holidays  = $holidays;
        $this->schedules = $schedules;

        $this->dry = (bool) $this->option('dry-run');

        if ($this->option('at')) {
            Carbon::setTestNow(Carbon::parse($this->option('at'), config('app.timezone')));
            $this->warn('Simulasi waktu: ' . now()->format('Y-m-d H:i'));
        }

        $companies = Company::where('is_active', true)->get();

        foreach ($companies as $company) {
            try {
                $this->processCompany($company);
            } catch (\Throwable $e) {
                Log::error('notifications:tick gagal', ['company_id' => $company->id, 'error' => $e->getMessage()]);
                $this->error("[company {$company->id}] " . $e->getMessage());
            }
        }

        try {
            $this->scheduleReminders();
        } catch (\Throwable $e) {
            Log::error('notifications:tick jadwal gagal', ['error' => $e->getMessage()]);
            $this->error('[jadwal] ' . $e->getMessage());
        }

        $this->info(($this->dry ? '[DRY RUN] ' : '') . "Selesai. Total notifikasi: {$this->total}");

        Carbon::setTestNow();

        return self::SUCCESS;
    }

    // ==========================================================
    // HELPER
    // ==========================================================

    private function tgl(Carbon $d): string
    {
        return self::HARI[$d->dayOfWeek] . ', ' . $d->day . ' ' . self::BULAN[$d->month];
    }

    /** Apakah sekarang berada di [target, target + WINDOW) */
    private function inWindow(?Carbon $target): bool
    {
        if (!$target) return false;

        $now = now();

        return $now->gte($target) && $now->lt($target->copy()->addMinutes(self::WINDOW_MINUTES));
    }

    private function todayAt(string $hhmm): Carbon
    {
        return Carbon::parse(now()->toDateString() . ' ' . $hhmm);
    }

    /** Sudah pernah dikirim (type + tanggal) ke user ini? */
    private function alreadySent(int $userId, string $type, string $dateKey): bool
    {
        return AppNotification::where('user_id', $userId)
            ->where('type', $type)
            ->where('data->date', $dateKey)
            ->exists();
    }

    private function deliver($users, string $type, string $title, string $body, array $options, string $logLabel): void
    {
        $count = is_countable($users) ? count($users) : 1;

        if ($count === 0) return;

        $this->total += $count;
        $this->line(($this->dry ? '[DRY] ' : '') . "{$logLabel} → {$count} penerima: {$title}");

        if (!$this->dry) {
            $this->notif->send($users, $type, $title, $body, $options);
        }
    }

    // ==========================================================
    // PER COMPANY
    // ==========================================================

    private function processCompany(Company $company): void
    {
        $today     = now()->toDateString();
        $isWorkDay = CompanySettings::isWorkDay($company, $today);
        $isHoliday = CompanyHoliday::where('company_id', $company->id)->activeOn($today)->exists();

        if ($isWorkDay && !$isHoliday) {
            $this->attendanceReminders($company);
            $this->hrDigest($company);
        }

        // Pulang shift malam (masuk kemarin, pulang hari ini) tetap diingatkan walau hari ini libur
        $this->nightShiftCheckoutReminders($company);

        $this->holidayReminder($company);
        $this->shiftTomorrow($company);
    }

    // ----------------------------------------------------------
    // ⏰ ABSEN MASUK, BELUM ABSEN, ABSEN PULANG
    // ----------------------------------------------------------

    private function attendanceReminders(Company $company): void
    {
        $set = CompanySettings::notification($company);

        $anyOn = $set['checkin_reminder']['enabled'] || $set['late_reminder']['enabled'] || $set['checkout_reminder']['enabled'];
        if (!$anyOn) return;

        $today = now()->toDateString();

        $employees = User::where('company_id', $company->id)
            ->where('role', 'employee')
            ->where('is_active', true)
            ->get();

        if ($employees->isEmpty()) return;

        // Data hari ini sekali ambil
        $attendances = Attendance::where('company_id', $company->id)
            ->whereDate('date', $today)
            ->get()
            ->keyBy('user_id');

        $onLeave = Leaves::where('company_id', $company->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->pluck('user_id')
            ->map(fn($v) => (int) $v)
            ->all();

        foreach ($employees as $user) {
            if (in_array((int) $user->id, $onLeave, true)) continue;

            $perms = $this->rules->approvedPermissionsOn($user->id, $company->id, $today);

            // Izin tidak masuk seharian → tidak perlu diingatkan
            if ($perms->contains('type', Permission::TYPE_TIDAK_MASUK)) continue;

            $shift        = $this->rules->resolveShift($user, $today);
            $scheduledIn  = $this->rules->scheduledIn($shift, $company, $today);
            $scheduledOut = $this->rules->scheduledOut($shift, $company, $today);

            if (!$scheduledIn) continue;

            // Jam masuk/pulang yang sudah memperhitungkan izin datang terlambat / pulang cepat
            [$allowedIn]  = $this->rules->allowedIn($scheduledIn, $perms, $today);
            [$allowedOut] = $this->rules->allowedOut($scheduledOut, $perms, $today);

            $att        = $attendances->get($user->id);
            $checkedIn  = $att && $att->time_in;
            $checkedOut = $att && $att->time_out;

            // 1) Pengingat absen masuk
            if ($set['checkin_reminder']['enabled'] && !$checkedIn) {
                $target = $allowedIn->copy()->subMinutes((int) $set['checkin_reminder']['minutes_before']);

                if ($this->inWindow($target) && !$this->alreadySent($user->id, 'attendance.checkin_reminder', $today)) {
                    $this->deliver(
                        $user->id,
                        'attendance.checkin_reminder',
                        '⏰ Jangan lupa absen masuk',
                        'Jam masuk Anda hari ini ' . $allowedIn->format('H:i') . ' WIB'
                            . ($shift ? ' (' . $shift->name . ')' : '') . '.',
                        ['category' => 'attendance', 'action' => 'attendance_checkin', 'data' => ['date' => $today]],
                        "[{$company->id}] checkin_reminder user {$user->id}"
                    );
                }
            }

            // 2) Belum absen setelah lewat jam masuk
            if ($set['late_reminder']['enabled'] && !$checkedIn) {
                $target = $allowedIn->copy()->addMinutes((int) $set['late_reminder']['minutes_after']);

                if ($this->inWindow($target) && !$this->alreadySent($user->id, 'attendance.late_reminder', $today)) {
                    $this->deliver(
                        $user->id,
                        'attendance.late_reminder',
                        '⚠️ Anda belum absen masuk',
                        'Jam masuk ' . $allowedIn->format('H:i') . ' WIB sudah lewat. Segera absen, atau ajukan izin kalau berhalangan.',
                        ['category' => 'attendance', 'action' => 'attendance_checkin', 'data' => ['date' => $today]],
                        "[{$company->id}] late_reminder user {$user->id}"
                    );
                }
            }

            // 3) Pengingat absen pulang (shift biasa: pulang di hari yang sama)
            if (
                $set['checkout_reminder']['enabled'] && $checkedIn && !$checkedOut
                && $allowedOut && $allowedOut->isSameDay(now())
            ) {
                $target = $allowedOut->copy()->addMinutes((int) $set['checkout_reminder']['minutes_after']);

                if ($this->inWindow($target) && !$this->alreadySent($user->id, 'attendance.checkout_reminder', $today)) {
                    $this->deliver(
                        $user->id,
                        'attendance.checkout_reminder',
                        '🏠 Jangan lupa absen pulang',
                        'Jam pulang Anda ' . $allowedOut->format('H:i') . ' WIB. Absen pulang sebelum meninggalkan lokasi.',
                        ['category' => 'attendance', 'action' => 'attendance_checkout', 'data' => ['date' => $today]],
                        "[{$company->id}] checkout_reminder user {$user->id}"
                    );
                }
            }
        }
    }

    /** Shift malam: masuk kemarin, jam pulang jatuh hari ini */
    private function nightShiftCheckoutReminders(Company $company): void
    {
        if (!CompanySettings::notif($company, 'checkout_reminder.enabled', true)) return;

        $yesterday = now()->subDay()->toDateString();
        $minutes   = (int) CompanySettings::notif($company, 'checkout_reminder.minutes_after', 0);

        $open = Attendance::where('company_id', $company->id)
            ->whereDate('date', $yesterday)
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->whereNotNull('scheduled_in')
            ->whereNotNull('scheduled_out')
            ->whereColumn('scheduled_out', '<', 'scheduled_in')
            ->get();

        foreach ($open as $att) {
            $target = Carbon::parse(now()->toDateString() . ' ' . $att->scheduled_out)->addMinutes($minutes);

            if ($this->inWindow($target) && !$this->alreadySent($att->user_id, 'attendance.checkout_reminder', $yesterday)) {
                $this->deliver(
                    $att->user_id,
                    'attendance.checkout_reminder',
                    '🏠 Jangan lupa absen pulang',
                    'Shift malam Anda selesai jam ' . substr($att->scheduled_out, 0, 5) . ' WIB.',
                    ['category' => 'attendance', 'action' => 'attendance_checkout', 'data' => ['date' => $yesterday]],
                    "[{$company->id}] night_checkout user {$att->user_id}"
                );
            }
        }
    }

    // ----------------------------------------------------------
    // 📋 RINGKASAN UNTUK HR
    // ----------------------------------------------------------

    private function hrDigest(Company $company): void
    {
        $set = CompanySettings::notif($company, 'hr_daily_digest');

        if (empty($set['enabled']) || !$this->inWindow($this->todayAt($set['time']))) return;

        $today = now()->toDateString();

        $counts = [
            'izin'            => Permission::where('company_id', $company->id)->whereNull('is_approved')->count(),
            'cuti'            => Leaves::where('company_id', $company->id)->where('status', 'pending')->count(),
            'lembur'          => OvertimeRequest::where('company_id', $company->id)->where('status', 'pending')->count(),
            'laporan bulanan' => MonthlyReport::where('company_id', $company->id)->where('status', 'submitted')->count(),
        ];

        $total = array_sum($counts);
        if ($total === 0) return;

        $parts = collect($counts)->filter()->map(fn($n, $label) => "{$n} {$label}")->implode(', ');

        $hrs = $this->notif->hrOf($company->id)
            ->reject(fn($hr) => $this->alreadySent($hr->id, 'hr.daily_digest', $today))
            ->pluck('id')
            ->all();

        $this->deliver(
            $hrs,
            'hr.daily_digest',
            '📋 ' . $total . ' pengajuan menunggu persetujuan',
            ucfirst($parts) . ' belum diputuskan.',
            [
                'category' => 'approval',
                'action'   => 'hr_approvals',
                'data'     => ['date' => $today] + collect($counts)->mapWithKeys(fn($v, $k) => [str_replace(' ', '_', $k) => $v])->all(),
            ],
            "[{$company->id}] hr_daily_digest"
        );
    }

    // ----------------------------------------------------------
    // 🔔 BESOK LIBUR
    // ----------------------------------------------------------

    private function holidayReminder(Company $company): void
    {
        $set = CompanySettings::notif($company, 'holiday_reminder');

        if (empty($set['enabled']) || !$this->inWindow($this->todayAt($set['time']))) return;

        $tomorrow = now()->addDay()->toDateString();

        $holidays = CompanyHoliday::where('company_id', $company->id)
            ->whereDate('start_date', $tomorrow)
            ->whereNull('reminder_sent_at')
            ->get();

        foreach ($holidays as $h) {
            $this->total++;
            $this->line(($this->dry ? '[DRY] ' : '') . "[{$company->id}] holiday_reminder: {$h->name}");

            if (!$this->dry) {
                $this->holidays->reminder($h);
                $h->forceFill(['reminder_sent_at' => now()])->save();
            }
        }
    }

    // ----------------------------------------------------------
    // 🔄 SHIFT BESOK BERBEDA DARI BIASANYA
    // ----------------------------------------------------------

    private function shiftTomorrow(Company $company): void
    {
        $set = CompanySettings::notif($company, 'shift_tomorrow');

        if (empty($set['enabled']) || !$this->inWindow($this->todayAt($set['time']))) return;

        $tomorrow = now()->addDay();
        $date     = $tomorrow->toDateString();

        if (!CompanySettings::isWorkDay($company, $tomorrow)) return;
        if (CompanyHoliday::where('company_id', $company->id)->activeOn($date)->exists()) return;

        $defaultShiftId = Shift::where('company_id', $company->id)->where('is_default', true)->value('id');

        $employees = User::where('company_id', $company->id)
            ->where('role', 'employee')
            ->where('is_active', true)
            ->get();

        foreach ($employees as $user) {
            $shift = $this->rules->resolveShift($user, $date);

            // Hanya kirim kalau besok shift-nya BUKAN shift default (ada override / jadwal grup)
            if (!$shift || (int) $shift->id === (int) $defaultShiftId) continue;
            if ($this->alreadySent($user->id, 'shift.tomorrow', $date)) continue;

            $this->deliver(
                $user->id,
                'shift.tomorrow',
                '🔄 Shift besok: ' . $shift->name,
                $this->tgl($tomorrow) . ', jam ' . substr($shift->start_time, 0, 5) . '–' . substr($shift->end_time, 0, 5) . ' WIB.',
                ['category' => 'schedule', 'action' => 'shift_schedule', 'data' => ['date' => $date, 'shift_id' => $shift->id]],
                "[{$company->id}] shift_tomorrow user {$user->id}"
            );
        }
    }

    // ==========================================================
    // 📅 PENGINGAT JADWAL / TUGAS
    // ==========================================================

    private function scheduleReminders(): void
    {
        $now = now();

        // Jadwal yang mulai dalam 24 jam ke depan (offset terbesar yang wajar)
        $schedules = Schedule::query()
            ->where('status', 'upcoming')
            ->where('start_datetime', '>', $now)
            ->where('start_datetime', '<=', $now->copy()->addDay()->addMinutes(self::WINDOW_MINUTES))
            ->get();

        foreach ($schedules as $s) {
            $offsets = collect($s->reminder_offsets ?: [15])
                ->map(fn($v) => (int) $v)
                ->filter(fn($v) => $v > 0)
                ->unique();

            foreach ($offsets as $offset) {
                $target = $s->start_datetime->copy()->subMinutes($offset);

                if (!$this->inWindow($target)) continue;

                $userIds = ScheduleParticipant::where('schedule_id', $s->id)
                    ->where('status', '!=', 'declined')
                    ->pluck('user_id')
                    ->push($s->user_id)
                    ->map(fn($v) => (int) $v)
                    ->unique()
                    ->reject(fn($uid) => AppNotification::where('user_id', $uid)
                        ->where('type', 'schedule.reminder')
                        ->where('reference_id', $s->id)
                        ->where('data->offset', $offset)
                        ->exists())
                    ->values()
                    ->all();

                if (!$userIds) continue;

                $this->total += count($userIds);
                $this->line(($this->dry ? '[DRY] ' : '') . "schedule_reminder #{$s->id} ({$offset} mnt) → " . count($userIds) . " penerima: {$s->title}");

                if (!$this->dry) {
                    $this->schedules->reminder($s, $userIds, $offset);
                }
            }
        }
    }
}
