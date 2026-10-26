<?php

namespace App\Console\Commands;

use App\Models\CompanyHoliday;
use App\Services\HolidayNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Kirim pengingat H-1 libur ke HP karyawan.
 *
 * Manual:   php artisan holidays:remind
 * Tes:      php artisan holidays:remind --date=2026-10-27 --force
 * Otomatis: didaftarkan di scheduler (lihat panduan)
 */
class SendHolidayReminders extends Command
{
    protected $signature = 'holidays:remind
                            {--date= : Tanggal libur yang dicek (default: besok)}
                            {--force : Kirim ulang walau pengingat sudah pernah dikirim}';

    protected $description = 'Kirim notifikasi pengingat H-1 sebelum libur ke karyawan';

    public function handle(HolidayNotifier $notifier): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : now()->addDay()->toDateString();

        $holidays = CompanyHoliday::query()
            ->whereDate('start_date', $date)
            ->when(!$this->option('force'), fn($q) => $q->whereNull('reminder_sent_at'))
            ->get();

        if ($holidays->isEmpty()) {
            $this->info("Tidak ada libur yang dimulai {$date}.");
            return self::SUCCESS;
        }

        foreach ($holidays as $holiday) {
            $r = $notifier->reminder($holiday);

            $holiday->forceFill(['reminder_sent_at' => now()])->save();

            $this->line(sprintf(
                '[company %d] %s → terkirim %d, gagal %d, tanpa token %d',
                $holiday->company_id,
                $holiday->name,
                $r['sent'],
                $r['failed'],
                $r['skipped']
            ));
        }

        return self::SUCCESS;
    }
}
