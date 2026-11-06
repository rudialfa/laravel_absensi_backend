<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Kirim push notification lewat FCM HTTP v1.
 * Tidak butuh package tambahan — hanya file service account JSON.
 *
 * Pemakaian:
 *   app(FcmService::class)->sendToUsers($users, 'Judul', 'Isi', ['type' => 'holiday']);
 */
class FcmService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private ?array $credentials = null;

    // ----------------------------------------------------------
    // KONFIGURASI
    // ----------------------------------------------------------

    public function isConfigured(): bool
    {
        return File::exists((string) config('fcm.credentials'));
    }

    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = (string) config('fcm.credentials');

        if (!File::exists($path)) {
            throw new \RuntimeException("File service account FCM tidak ditemukan: {$path}");
        }

        $json = json_decode(File::get($path), true);

        if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new \RuntimeException('File service account FCM tidak valid.');
        }

        return $this->credentials = $json;
    }

    private function projectId(): string
    {
        return config('fcm.project_id') ?: $this->credentials()['project_id'];
    }

    // ----------------------------------------------------------
    // OAUTH TOKEN (berlaku 1 jam, di-cache 55 menit)
    // ----------------------------------------------------------

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm_access_token', 55 * 60, function () {
            $cred     = $this->credentials();
            $tokenUri = $cred['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now      = time();

            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64Url(json_encode([
                'iss'   => $cred['client_email'],
                'scope' => self::SCOPE,
                'aud'   => $tokenUri,
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));

            $unsigned = $header . '.' . $claims;
            $signature = '';

            if (!openssl_sign($unsigned, $signature, $cred['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Gagal menandatangani JWT FCM (cek private_key).');
            }

            $jwt = $unsigned . '.' . $this->base64Url($signature);

            $res = Http::asForm()->timeout(15)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if (!$res->successful() || !$res->json('access_token')) {
                throw new \RuntimeException('Gagal ambil access token FCM: ' . $res->body());
            }

            return $res->json('access_token');
        });
    }

    // ----------------------------------------------------------
    // KIRIM
    // ----------------------------------------------------------

    /**
     * @return array{ok:bool, invalid_token:bool, error:?string}
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        // FCM v1 mewajibkan semua nilai "data" berupa string
        $data = collect($data)->map(fn($v) => is_scalar($v) ? (string) $v : json_encode($v))->all();

        $payload = [
            'message' => [
                'token'        => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data'         => (object) $data,
                'android'      => [
                    'priority'     => 'HIGH',
                    'notification' => [
                        'channel_id' => config('fcm.android_channel_id'),
                        // Nama file di android/app/src/main/res/raw (tanpa ekstensi).
                        // Android 8+ memakai suara milik channel; nilai ini untuk Android lama.
                        'sound'      => config('fcm.android_sound', 'default'),
                    ],
                ],
                'apns' => [
                    'payload' => ['aps' => ['sound' => config('fcm.ios_sound', 'default')]],
                ],
            ],
        ];

        try {
            $res = Http::withToken($this->accessToken())
                ->timeout(15)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId()}/messages:send", $payload);
        } catch (\Throwable $e) {
            Log::warning('FCM error', ['error' => $e->getMessage()]);
            return ['ok' => false, 'invalid_token' => false, 'error' => $e->getMessage()];
        }

        if ($res->successful()) {
            return ['ok' => true, 'invalid_token' => false, 'error' => null];
        }

        // Token HP sudah tidak berlaku (aplikasi di-uninstall / login di HP lain)
        $code    = $res->json('error.details.0.errorCode') ?? $res->json('error.status');
        $invalid = in_array($code, ['UNREGISTERED', 'INVALID_ARGUMENT'], true) || $res->status() === 404;

        // Token OAuth kadaluarsa → buang cache, percobaan berikutnya ambil token baru
        if ($res->status() === 401) {
            Cache::forget('fcm_access_token');
        }

        Log::warning('FCM gagal kirim', ['status' => $res->status(), 'code' => $code, 'body' => $res->body()]);

        return ['ok' => false, 'invalid_token' => $invalid, 'error' => $code ?: $res->body()];
    }

    /**
     * Kirim ke banyak user. Token yang sudah tidak berlaku otomatis dikosongkan.
     *
     * @param iterable<User> $users
     * @return array{sent:int, failed:int, skipped:int, invalid_tokens_cleared:int}
     */
    public function sendToUsers(iterable $users, string $title, string $body, array $data = []): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'invalid_tokens_cleared' => 0];

        if (!$this->isConfigured()) {
            Log::warning('FCM belum dikonfigurasi: file service account tidak ada.', [
                'path' => config('fcm.credentials'),
            ]);
            return $result;
        }

        $seen = [];

        foreach ($users as $user) {
            $token = $user->fcm_token;

            if (!$token || isset($seen[$token])) {
                $result['skipped']++;
                continue;
            }
            $seen[$token] = true;

            $r = $this->sendToToken($token, $title, $body, $data);

            if ($r['ok']) {
                $result['sent']++;
                continue;
            }

            $result['failed']++;

            if ($r['invalid_token']) {
                User::where('fcm_token', $token)->update(['fcm_token' => null]);
                $result['invalid_tokens_cleared']++;
            }
        }

        return $result;
    }
}
