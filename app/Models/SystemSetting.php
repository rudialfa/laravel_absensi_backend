<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan sistem (tabel system_settings: key, value).
 *
 * Semua baris dibaca SEKALI lalu disimpan di cache, karena beberapa pengaturan
 * (mode maintenance, masa toleransi) dibaca di setiap request.
 * Cache otomatis dibuang setiap kali set()/setMany() dipanggil.
 *
 *   SystemSetting::get('app_name', 'Absen Pintar');
 *   SystemSetting::bool('maintenance_mode');
 *   SystemSetting::int('grace_period_days', 3, 0, 30);
 *   SystemSetting::set('support_email', 'cs@contoh.id');
 */
class SystemSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    private const CACHE_KEY = 'system_settings:all';

    /** Semua pengaturan sebagai [key => value]. */
    public static function allValues(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn() => static::query()->pluck('value', 'key')->all());
        } catch (\Throwable) {
            // Tabel belum ada (misal sebelum migrate) → anggap kosong
            return [];
        }
    }

    public static function get(string $key, $default = null)
    {
        $value = static::allValues()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function int(string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $value = static::get($key);
        $int = is_numeric($value) ? (int) $value : $default;

        if ($min !== null) {
            $int = max($min, $int);
        }
        if ($max !== null) {
            $int = min($max, $int);
        }

        return $int;
    }

    /** Daftar baris (dipisah enter / koma) → array tanpa baris kosong. */
    public static function lines(string $key): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) static::get($key, '')))
            ->map(fn($v) => trim($v))->filter()->unique()->values()->all();
    }

    public static function set(string $key, $value): void
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::flushCache();
    }

    /** Simpan banyak sekaligus. */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        static::flushCache();
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn() => static::flushCache());
        static::deleted(fn() => static::flushCache());
    }
}