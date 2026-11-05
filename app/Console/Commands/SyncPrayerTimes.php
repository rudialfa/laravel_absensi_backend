<?php

namespace App\Console\Commands;

use App\Services\PrayerTimeService;
use Illuminate\Console\Command;

/**
 * Siapkan jadwal sholat bulan ini & bulan depan untuk semua kota tenant,
 * supaya aplikasi santri/ustadz tidak perlu menunggu API Aladhan saat dibuka.
 *
 *   php artisan prayers:sync
 *   php artisan prayers:sync --months=3 --city=Magelang
 */
class SyncPrayerTimes extends Command
{
    protected $signature = 'prayers:sync
                            {--months=2 : Jumlah bulan mulai bulan ini}
                            {--city= : Hanya 1 kota}';

    protected $description = 'Ambil jadwal sholat dari API Aladhan untuk semua kota tenant';

    public function handle(PrayerTimeService $service): int
    {
        $cities = $this->option('city') ? collect([$this->option('city') => 1]) : $service->tenantCities();
        $months = max(1, min(12, (int) $this->option('months')));

        foreach ($cities->keys() as $city) {
            for ($i = 0; $i < $months; $i++) {
                $m = now('Asia/Jakarta')->startOfMonth()->addMonthsNoOverflow($i);
                try {
                    $r = $service->fetchMonth($city, $m->year, $m->month);
                    $this->info("{$city} {$m->format('m/Y')}: {$r['saved']} hari disimpan" . ($r['skipped_manual'] ? ", {$r['skipped_manual']} manual dilewati" : ''));
                } catch (\Throwable $e) {
                    $this->error("{$city} {$m->format('m/Y')}: " . $e->getMessage());
                }
                usleep(300000); // jeda supaya tidak membebani API
            }
        }

        return self::SUCCESS;
    }
}
