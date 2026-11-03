<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\CompanyHoliday;
use App\Models\Leaves;
use App\Models\Permission;
use App\Models\User;
use App\Services\AttendanceRuleService;
use App\Services\FcmService;
use App\Support\CompanySettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Cek kenapa pengingat absen (masuk / belum absen / pulang) tidak sampai ke 1 karyawan.
 *
 *   php artisan notif:check 26
 *   php artisan notif:check 26 --date=2026-11-04
 *
 * Yang dicek: zona waktu server, FCM, token HP, hari kerja, libur, cuti, izin,
 * shift & jam masuk/pulang, jam kirim pengingat, status absen, dan apakah sudah pernah terkirim.
 */
class NotifCheck extends Command
{
    protected $signature = 'notif:check
        {user_id : ID karyawan}
        {--date= : Tanggal yang dicek (default hari ini), contoh 2026-11-04}';

    protected $description = 'Diagnosa pengingat absen untuk 1 karyawan';

    public function handle(AttendanceRuleService $rules, FcmService $fcm): int
    {
        $user = User::find($this->argument('user_id'));
        if (! $user) {
            $this->error('User tidak ditemukan.');
            return self::FAILURE;
        }

        $company = $user->company;
        if (! $company) {
            $this->error('User tidak terhubung ke perusahaan.');
            return self::FAILURE;
        }

        $date = $this->option('date') ?: now()->toDateString();
        $set  = CompanySettings::notification($company);
        $ok   = fn(bool $v) => $v ? '<fg=green>✔</>' : '<fg=red>✘</>';

        $this->info("Karyawan : {$user->name} (id {$user->id}, role {$user->role})");
        $this->info("Perusahaan: {$company->name} (id {$company->id})");
        $this->info("Tanggal  : {$date}");
        $this->newLine();

        // ---------- 1. Server ----------
        $this->line('<options=bold>1. Server</>');
        $tz = config('app.timezone');
        $this->line("  {$ok($tz === 'Asia/Jakarta')} Zona waktu aplikasi: {$tz}"
            . ($tz === 'Asia/Jakarta' ? '' : '  ← harus Asia/Jakarta (config/app.php)'));
        $this->line('    Jam server sekarang: ' . now()->format('Y-m-d H:i:s'));
        $this->line("  {$ok($fcm->isConfigured())} FCM siap (file kunci: " . config('fcm.credentials') . ')');
        $this->line("  {$ok((bool)$company->is_active)} Perusahaan aktif");
        $this->line("  {$ok($user->role === 'employee')} Role employee (pengingat absen hanya untuk employee)");
        $this->line("  {$ok((bool)$user->is_active)} Akun aktif");
        $this->line("  {$ok((bool)$user->fcm_token)} Token HP ada"
            . ($user->fcm_token ? '' : '  ← logout lalu login lagi di HP'));

        $last = AppNotification::where('type', 'like', 'attendance.%')->latest('id')->first();
        $this->line('    Pengingat terakhir terkirim (semua user): '
            . ($last ? $last->created_at->timezone($tz)->format('Y-m-d H:i') : 'BELUM PERNAH ← scheduler kemungkinan tidak jalan'));

        // ---------- 2. Hari ini ----------
        $this->newLine();
        $this->line('<options=bold>2. Hari ini</>');
        $isWorkDay = CompanySettings::isWorkDay($company, $date);
        $this->line("  {$ok($isWorkDay)} Hari kerja (" . CompanySettings::workDaysLabel($company) . ')');

        $holiday = CompanyHoliday::where('company_id', $company->id)->activeOn($date)->first();
        $this->line("  {$ok(!$holiday)} Bukan hari libur" . ($holiday ? "  ← libur: {$holiday->name}" : ''));

        $onLeave = Leaves::where('company_id', $company->id)->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)
            ->exists();
        $this->line("  {$ok(!$onLeave)} Tidak sedang cuti");

        $perms = $rules->approvedPermissionsOn($user->id, $company->id, $date);
        $fullDay = $perms->contains('type', Permission::TYPE_TIDAK_MASUK);
        $this->line("  {$ok(!$fullDay)} Tidak ada izin \"tidak masuk\" yang disetujui");
        if ($perms->isNotEmpty()) {
            $this->line('    Izin disetujui: ' . $perms->pluck('type')->implode(', '));
        }

        // ---------- 3. Jadwal ----------
        $this->newLine();
        $this->line('<options=bold>3. Shift & jam</>');
        $shift = $rules->resolveShift($user, $date);
        $in    = $rules->scheduledIn($shift, $company, $date);
        $out   = $rules->scheduledOut($shift, $company, $date);

        $this->line('    Shift      : ' . ($shift ? "{$shift->name}" : '(jam perusahaan)'));
        $this->line('    Jam masuk  : ' . ($in ? $in->format('Y-m-d H:i') : '-'));
        $this->line('    Jam pulang : ' . ($out ? $out->format('Y-m-d H:i') : '-'));

        if (! $in) {
            $this->error('  Jam masuk kosong → pengingat tidak bisa dihitung. Isi jam masuk perusahaan / shift.');
            return self::SUCCESS;
        }

        [$allowedIn]  = $rules->allowedIn($in, $perms, $date);
        [$allowedOut] = $rules->allowedOut($out, $perms, $date);

        // ---------- 4. Pengingat ----------
        $this->newLine();
        $this->line('<options=bold>4. Jadwal kirim pengingat</>');

        $att        = Attendance::where('company_id', $company->id)->where('user_id', $user->id)->whereDate('date', $date)->first();
        $checkedIn  = $att && $att->time_in;
        $checkedOut = $att && $att->time_out;
        $this->line('    Status absen: ' . ($checkedIn ? 'sudah masuk ' . $att->time_in : 'belum masuk')
            . ($checkedOut ? ', sudah pulang ' . $att->time_out : ''));

        $sent = fn(string $type) => AppNotification::where('user_id', $user->id)
            ->where('type', $type)->where('data->date', $date)->first();

        $rows = [];

        $c = $set['checkin_reminder'];
        $t = $allowedIn->copy()->subMinutes((int) $c['minutes_before']);
        $s = $sent('attendance.checkin_reminder');
        $rows[] = [
            'Absen masuk',
            $c['enabled'] ? 'ON' : 'OFF',
            $t->format('H:i') . ' – ' . $t->copy()->addMinutes(10)->format('H:i'),
            $checkedIn ? 'dilewati (sudah absen)' : 'akan dikirim',
            $s ? 'YA ' . $s->created_at->timezone($tz)->format('H:i') . ' (' . ($s->push_status ?? '-') . ')' : 'belum',
        ];

        $c = $set['late_reminder'];
        $t = $allowedIn->copy()->addMinutes((int) $c['minutes_after']);
        $s = $sent('attendance.late_reminder');
        $rows[] = [
            'Belum absen',
            $c['enabled'] ? 'ON' : 'OFF',
            $t->format('H:i') . ' – ' . $t->copy()->addMinutes(10)->format('H:i'),
            $checkedIn ? 'dilewati (sudah absen)' : 'akan dikirim',
            $s ? 'YA ' . $s->created_at->timezone($tz)->format('H:i') . ' (' . ($s->push_status ?? '-') . ')' : 'belum',
        ];

        $c = $set['checkout_reminder'];
        $s = $sent('attendance.checkout_reminder');
        if ($allowedOut) {
            $t = $allowedOut->copy()->addMinutes((int) $c['minutes_after']);
            $rows[] = [
                'Absen pulang',
                $c['enabled'] ? 'ON' : 'OFF',
                $t->format('H:i') . ' – ' . $t->copy()->addMinutes(10)->format('H:i'),
                ! $checkedIn ? 'dilewati (belum absen masuk)' : ($checkedOut ? 'dilewati (sudah pulang)' : 'akan dikirim'),
                $s ? 'YA ' . $s->created_at->timezone($tz)->format('H:i') . ' (' . ($s->push_status ?? '-') . ')' : 'belum',
            ];
        }

        $this->table(['Pengingat', 'Setelan', 'Jam kirim', 'Kondisi', 'Sudah terkirim?'], $rows);

        $this->newLine();
        $this->line('Catatan:');
        $this->line('- Pengingat hanya terkirim kalau scheduler berjalan TEPAT di rentang "Jam kirim".');
        $this->line('  Lokal: biarkan "php artisan schedule:work" terbuka. Server: cron schedule:run tiap menit.');
        $this->line('- push_status: sent = sampai ke FCM, no_token = HP belum terdaftar, failed = gagal (lihat laravel.log).');
        $this->line('- Simulasi tanpa menunggu jam: php artisan notifications:tick --at="' . $date . ' ' . $allowedIn->copy()->subMinutes((int) $set['checkin_reminder']['minutes_before'])->format('H:i') . '"');

        return self::SUCCESS;
    }
}
