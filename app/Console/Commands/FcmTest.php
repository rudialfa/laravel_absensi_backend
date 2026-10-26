<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FcmService;
use Illuminate\Console\Command;

/**
 * Tes kirim notifikasi ke 1 user — untuk memastikan FCM sudah benar.
 *
 *   php artisan fcm:test 12
 */
class FcmTest extends Command
{
    protected $signature = 'fcm:test {user_id : ID user tujuan}';

    protected $description = 'Kirim notifikasi tes ke satu user';

    public function handle(FcmService $fcm): int
    {
        if (!$fcm->isConfigured()) {
            $this->error('File service account tidak ditemukan: ' . config('fcm.credentials'));
            $this->line('Download dari Firebase Console → Project settings → Service accounts → Generate new private key.');
            return self::FAILURE;
        }

        $user = User::find($this->argument('user_id'));

        if (!$user) {
            $this->error('User tidak ditemukan.');
            return self::FAILURE;
        }

        if (!$user->fcm_token) {
            $this->error("User {$user->name} belum punya fcm_token.");
            $this->line('Pastikan aplikasi memanggil endpoint update FCM token setelah login.');
            return self::FAILURE;
        }

        $r = $fcm->sendToToken($user->fcm_token, '🔔 Tes notifikasi', 'Kalau ini muncul, FCM sudah jalan.', [
            'type' => 'test',
        ]);

        if ($r['ok']) {
            $this->info("Terkirim ke {$user->name}. Cek HP-nya.");
            return self::SUCCESS;
        }

        $this->error('Gagal: ' . $r['error']);

        if ($r['invalid_token']) {
            $this->line('Token HP tidak berlaku lagi. Minta user logout lalu login ulang di aplikasi.');
        }

        return self::FAILURE;
    }
}
