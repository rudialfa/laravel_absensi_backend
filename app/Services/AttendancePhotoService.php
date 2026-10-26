<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan foto yang dikirim aplikasi dalam bentuk STRING base64.
 *
 * Aplikasi mobile cukup kirim:
 *   "photo": "/9j/4AAQSkZJRgABAQ..."              (base64 polos)
 *   "photo": "data:image/jpeg;base64,/9j/4AAQ..."  (data URI juga diterima)
 *
 * Request tidak perlu multipart/form-data. Di database hanya tersimpan
 * path pendek seperti "image/attendance/2026/10/9b1f...jpg".
 */
class AttendancePhotoService
{
    private const MAX_BYTES = 3 * 1024 * 1024; // 3 MB setelah di-decode

    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function storeBase64(string $base64, string $field = 'photo', string $folder = 'image/attendance'): string
    {
        // Buang prefix data URI kalau ada
        if (str_contains($base64, ',') && str_starts_with(trim($base64), 'data:')) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $base64 = preg_replace('/\s+/', '', $base64);
        $binary = base64_decode($base64, true);

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                $field => 'Format foto tidak valid (harus base64).',
            ]);
        }

        if (strlen($binary) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                $field => 'Ukuran foto maksimal 3 MB. Kompres foto di aplikasi sebelum dikirim.',
            ]);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);

        if (!isset(self::ALLOWED_MIMES[$mime])) {
            throw ValidationException::withMessages([
                $field => 'Foto harus berformat JPG, PNG, atau WEBP.',
            ]);
        }

        $relativeDir = trim($folder, '/') . '/' . now()->format('Y/m');
        $absoluteDir = public_path($relativeDir);

        if (!File::exists($absoluteDir)) {
            File::makeDirectory($absoluteDir, 0755, true);
        }

        $fileName = Str::uuid()->toString() . '.' . self::ALLOWED_MIMES[$mime];
        File::put($absoluteDir . DIRECTORY_SEPARATOR . $fileName, $binary);

        return $relativeDir . '/' . $fileName;
    }

    public function delete(?string $relativePath): void
    {
        if ($relativePath && File::exists(public_path($relativePath))) {
            File::delete(public_path($relativePath));
        }
    }
}
