<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * EmployeeApiService
 *
 * Semua request ke API internal Laravel pakai token Sanctum milik user yang sedang login.
 * Base URL diambil dari config/app.php (APP_URL) supaya fleksibel di setiap environment.
 *
 * Cara pakai di controller:
 *   $api = new EmployeeApiService();
 *   $response = $api->get('/company/employee/attendances/history');
 *   $data = $response->json();
 */
class EmployeeApiService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('app.url'), '/') . '/api';
        // Ambil token Sanctum dari session (disimpan saat login)
        $this->token = session('api_token', '');
    }

    // ── Core HTTP methods ──────────────────────────────────────────────────

    public function get(string $path, array $query = []): Response
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->get($this->baseUrl . $path, $query);
    }

    public function post(string $path, array $data = []): Response
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->post($this->baseUrl . $path, $data);
    }

    public function postMultipart(string $path, array $fields = [], array $files = []): Response
    {
        $request = Http::withToken($this->token)->acceptJson();

        foreach ($files as $name => $file) {
            if ($file) {
                $request = $request->attach(
                    $name,
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName()
                );
            }
        }

        return $request->post($this->baseUrl . $path, $fields);
    }

    public function patch(string $path, array $data = []): Response
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->patch($this->baseUrl . $path, $data);
    }

    public function put(string $path, array $data = []): Response
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->put($this->baseUrl . $path, $data);
    }

    public function delete(string $path): Response
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->delete($this->baseUrl . $path);
    }

    // ── Helper: redirect back with error jika API gagal ───────────────────

    // public static function flashError(Response $response): void
    // {
    //     $body = $response->json();
    //     $msg  = $body['message'] ?? ($body['errors'] ? implode(', ', array_map(
    //         fn($e) => is_array($e) ? implode(', ', $e) : $e,
    //         $body['errors']
    //     )) : 'Terjadi kesalahan.');
    //     session()->flash('error', $msg);
    // }

    public static function flashSuccess(string $message): void
    {
        session()->flash('success', $message);
    }

    public static function flashError(Response $response): void
{
    $body = $response->json() ?? [];  // pastikan tidak null

    $msg = $body['message'] ?? null;

    if (!$msg && !empty($body['errors'])) {
        $msg = implode(', ', array_map(
            fn($e) => is_array($e) ? implode(', ', $e) : $e,
            $body['errors']
        ));
    }

    session()->flash('error', $msg ?? 'Terjadi kesalahan.');
}
}