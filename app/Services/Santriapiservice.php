<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * SantriApiService
 *
 * Wrapper HTTP ke semua API endpoint pesantren (/api/pesantren/santri/*)
 * Token Sanctum diambil dari session 'api_token' yang disimpan saat login.
 */
class SantriApiService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('app.url'), '/') . '/api/pesantren';
        $this->token   = session('api_token', '');
    }

    // ── Core HTTP ─────────────────────────────────────────────────

    public function get(string $path, array $query = []): Response
    {
        return Http::withToken($this->token)->acceptJson()
            ->get($this->baseUrl . $path, $query);
    }

    public function post(string $path, array $data = []): Response
    {
        return Http::withToken($this->token)->acceptJson()
            ->post($this->baseUrl . $path, $data);
    }

    public function postMultipart(string $path, array $fields = [], array $files = []): Response
    {
        $req = Http::withToken($this->token)->acceptJson();
        foreach ($files as $name => $file) {
            if ($file) {
                $req = $req->attach($name, file_get_contents($file->getRealPath()), $file->getClientOriginalName());
            }
        }
        return $req->post($this->baseUrl . $path, $fields);
    }

    public function patch(string $path, array $data = []): Response
    {
        return Http::withToken($this->token)->acceptJson()
            ->patch($this->baseUrl . $path, $data);
    }

    public function delete(string $path): Response
    {
        return Http::withToken($this->token)->acceptJson()
            ->delete($this->baseUrl . $path);
    }

    // ── Flash helpers ─────────────────────────────────────────────

    public static function flashError(Response $response): void
    {
        $body = $response->json();
        $msg  = $body['message'] ?? 'Terjadi kesalahan.';
        if (empty($msg) && !empty($body['errors'])) {
            $msg = implode(', ', array_map(
                fn($e) => is_array($e) ? implode(', ', $e) : $e,
                $body['errors']
            ));
        }
        session()->flash('error', $msg);
    }

    public static function flashSuccess(string $message): void
    {
        session()->flash('success', $message);
    }
}