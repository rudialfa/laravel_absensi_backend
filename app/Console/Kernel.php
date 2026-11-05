<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Jadwal tugas otomatis.
     *
     * Supaya jalan, scheduler harus dihidupkan:
     *   - Di komputer (development) : php artisan schedule:work   (biarkan jendela terbuka)
     *   - Di server Linux           : cron  * * * * * cd /path/project && php artisan schedule:run >> /dev/null 2>&1
     */
    protected function schedule(Schedule $schedule): void
    {
        // CATATAN: setiap command cukup didaftarkan SATU kali.
        // Sebelumnya 'notifications:tick' tertulis dua kali → notifikasi bisa terkirim dobel.
        // 'holidays:remind' juga tidak dipakai lagi (pengingat libur H-1 sudah ada di notifications:tick).

        // 💓 Detak scheduler: dipakai halaman superadmin "Antrian Job" untuk tahu cron hidup atau mati
        $schedule->call(function () {
            \Illuminate\Support\Facades\Cache::forever('scheduler:heartbeat', now()->timestamp);
        })
            ->everyMinute()
            ->name('scheduler-heartbeat');

        // 🧹 Hapus foto absen lama (sekolah)
        $schedule->command('school:cleanup-attendance-photos')
            ->dailyAt('02:00')
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping();

        // 🔔 Semua notifikasi terjadwal (pengingat absen, shift besok, libur H-1,
        //    ringkasan HR, pengingat jadwal). Jam & on/off diatur HR per company.
        $schedule->command('notifications:tick')
            ->everyMinute()
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/notifications-tick.log'));

        // 📣 Pengumuman (notifikasi massal) dari superadmin yang sudah tiba jadwalnya
        $schedule->command('broadcasts:send')
            ->everyMinute()
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/broadcasts.log'));

        // 🕌 Siapkan jadwal sholat bulan ini & bulan depan untuk semua kota tenant (tgl 1 & 25)
        $schedule->command('prayers:sync --months=2')
            ->monthlyOn(1, '01:30')
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/prayers.log'));
        $schedule->command('prayers:sync --months=2')
            ->monthlyOn(25, '01:30')
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/prayers.log'));

        // 🧾 Rapikan status langganan, invoice, dan VA yang sudah lewat waktu
        $schedule->command('subscriptions:expire')
            ->hourly()
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/subscriptions.log'));

        // 💳 Notifikasi langganan (trial, masa aktif, masa toleransi, invoice)
        $schedule->command('subscriptions:notify')
            ->everyFifteenMinutes()
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/subscriptions.log'));

        // Bersihkan notifikasi lama (lebih dari 90 hari) setiap hari jam 02:00
        $schedule->call(function () {
            \App\Models\AppNotification::where('created_at', '<', now()->subDays(90))->delete();
        })
            ->dailyAt('02:00')
            ->timezone('Asia/Jakarta')
            ->name('cleanup-old-notifications')
            ->withoutOverlapping();

        // Kosongkan file log scheduler setiap Minggu jam 03:00 supaya tidak membengkak
        $schedule->call(function () {
            foreach (['notifications-tick.log', 'subscriptions.log', 'broadcasts.log', 'prayers.log'] as $file) {
                $path = storage_path('logs/' . $file);
                if (file_exists($path)) {
                    file_put_contents($path, '');
                }
            }
        })
            ->weeklyOn(0, '03:00')
            ->timezone('Asia/Jakarta')
            ->name('truncate-scheduler-logs');
    }

    /**
     * Daftarkan semua command di app/Console/Commands.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}