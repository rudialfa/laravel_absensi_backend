<?php

namespace App\Console\Commands;

use App\Services\BroadcastService;
use Illuminate\Console\Command;

/**
 * Kirim pengumuman superadmin yang sudah tiba jadwalnya.
 * Dijalankan scheduler setiap menit (lihat app/Console/Kernel.php).
 *
 *   php artisan broadcasts:send
 */
class SendBroadcastNotifications extends Command
{
    protected $signature = 'broadcasts:send';

    protected $description = 'Kirim pengumuman (notifikasi massal) superadmin yang sudah waktunya';

    public function handle(BroadcastService $service): int
    {
        $n = $service->processDue();

        if ($n > 0) {
            $this->info(now()->format('Y-m-d H:i:s') . " — {$n} pengumuman dikirim.");
        }

        return self::SUCCESS;
    }
}
