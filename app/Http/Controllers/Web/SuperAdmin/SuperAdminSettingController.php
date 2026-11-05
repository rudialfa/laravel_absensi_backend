<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanySubscription;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan Sistem.
 *
 *  - Umum        : nama aplikasi (dipakai config('app.name')), email & WhatsApp support.
 *  - Langganan   : masa toleransi (grace) — dipakai CompanySubscription::graceDays(),
 *                  middleware CheckSubscription, notifikasi langganan, halaman Langganan.
 *                  Durasi trial TIDAK di sini: mengikuti duration_days paket gratis (menu Paket).
 *  - Maintenance : on/off, pesan, perkiraan selesai, IP & path pengecualian
 *                  → middleware CheckSystemMaintenance (API 503 JSON, web halaman maintenance).
 *  - Info sistem : versi, driver, cache, storage, scheduler + tombol bersihkan cache.
 */
class SuperAdminSettingController extends Controller
{
    private const TZ = 'Asia/Jakarta';

    /** Kunci yang dikelola halaman ini + nilai bawaan. */
    private const DEFAULTS = [
        'app_name'                 => null,
        'support_email'            => null,
        'support_whatsapp'         => null,
        'grace_period_days'        => CompanySubscription::GRACE_PERIOD_DAYS,
        'maintenance_mode'         => '0',
        'maintenance_message'      => null,
        'maintenance_until'        => null,
        'maintenance_allowed_ips'  => null,
        'maintenance_except_paths' => null,
    ];

    public function show(Request $request)
    {
        $settings = collect(self::DEFAULTS)->map(fn($default, $key) => SystemSetting::get($key, $default))->all();
        $settings['maintenance_mode'] = SystemSetting::bool('maintenance_mode');

        $trialPlan = SubscriptionPlan::where('is_free', true)->where('is_active', true)->orderBy('sort_order')->first();

        return view('pages.superadmin.settings.show', [
            'settings'      => $settings,
            'trialPlan'     => $trialPlan,
            'graceDays'     => CompanySubscription::graceDays(),
            'graceCount'    => CompanySubscription::where('status', 'grace')->count(),
            'myIp'          => $request->ip(),
            'system'        => $this->systemInfo(),
            'lastChange'    => AuditLog::with('user:id,name')->whereIn('action', ['update_settings', 'toggle_maintenance'])->latest('id')->first(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'app_name'                 => 'nullable|string|max:100',
            'support_email'            => 'nullable|email|max:255',
            'support_whatsapp'         => ['nullable', 'string', 'max:20', 'regex:/^(\+?62|0)8[0-9]{7,13}$/'],
            'grace_period_days'        => 'required|integer|min:0|max:30',
            'maintenance_mode'         => 'nullable|boolean',
            'maintenance_message'      => 'nullable|string|max:500',
            'maintenance_until'        => 'nullable|date_format:Y-m-d\TH:i',
            'maintenance_allowed_ips'  => 'nullable|string|max:1000',
            'maintenance_except_paths' => 'nullable|string|max:1000',
        ], [
            'support_whatsapp.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890 atau 6281234567890.',
        ], [
            'app_name'                 => 'nama aplikasi',
            'support_email'            => 'email support',
            'support_whatsapp'         => 'WhatsApp support',
            'grace_period_days'        => 'masa toleransi',
            'maintenance_message'      => 'pesan maintenance',
            'maintenance_until'        => 'perkiraan selesai',
            'maintenance_allowed_ips'  => 'IP pengecualian',
            'maintenance_except_paths' => 'path pengecualian',
        ]);

        // Validasi IP satu per satu
        $ips = $this->splitLines($data['maintenance_allowed_ips'] ?? '');
        $badIps = array_filter($ips, fn($ip) => !filter_var($ip, FILTER_VALIDATE_IP));
        if ($badIps) {
            return back()->withInput()->withErrors(['maintenance_allowed_ips' => 'IP tidak valid: ' . implode(', ', $badIps)]);
        }

        $paths = array_map(fn($p) => ltrim($p, '/'), $this->splitLines($data['maintenance_except_paths'] ?? ''));

        $new = [
            'app_name'                 => trim((string) ($data['app_name'] ?? '')) ?: null,
            'support_email'            => $data['support_email'] ?? null,
            'support_whatsapp'         => $data['support_whatsapp'] ?? null,
            'grace_period_days'        => (string) (int) $data['grace_period_days'],
            'maintenance_mode'         => $request->boolean('maintenance_mode') ? '1' : '0',
            'maintenance_message'      => trim((string) ($data['maintenance_message'] ?? '')) ?: null,
            'maintenance_until'        => $data['maintenance_until'] ?? null,
            'maintenance_allowed_ips'  => $ips ? implode("\n", $ips) : null,
            'maintenance_except_paths' => $paths ? implode("\n", $paths) : null,
        ];

        $before = collect(array_keys($new))->mapWithKeys(fn($k) => [$k => SystemSetting::get($k)])->all();
        $changed = array_filter($new, fn($v, $k) => (string) ($before[$k] ?? '') !== (string) ($v ?? ''), ARRAY_FILTER_USE_BOTH);

        if (!$changed) {
            return redirect()->route('superadmin.settings.show')->with('success', 'Tidak ada perubahan.');
        }

        SystemSetting::setMany($changed);

        AuditLog::record('update_settings', null, 'Pengaturan sistem diperbarui: ' . implode(', ', array_keys($changed)), [
            'before' => array_intersect_key($before, $changed),
            'after'  => $changed,
        ]);

        $msg = 'Pengaturan disimpan.';
        if (array_key_exists('maintenance_mode', $changed)) {
            $msg .= $new['maintenance_mode'] === '1'
                ? ' Mode maintenance AKTIF — aplikasi HP & web tenant sekarang menampilkan pesan maintenance.'
                : ' Mode maintenance dimatikan.';
        }
        if (array_key_exists('grace_period_days', $changed)) {
            $msg .= " Masa toleransi sekarang {$new['grace_period_days']} hari (berlaku untuk semua langganan, termasuk yang sedang dalam masa toleransi).";
        }

        return redirect()->route('superadmin.settings.show')->with('success', $msg);
    }

    /** Tombol cepat nyalakan / matikan maintenance. */
    public function toggleMaintenance()
    {
        $on = !SystemSetting::bool('maintenance_mode');
        SystemSetting::set('maintenance_mode', $on);

        AuditLog::record('toggle_maintenance', null, $on ? 'Mode maintenance diaktifkan' : 'Mode maintenance dimatikan', [
            'before' => ['maintenance_mode' => $on ? '0' : '1'],
            'after'  => ['maintenance_mode' => $on ? '1' : '0'],
        ]);

        return redirect()->route('superadmin.settings.show')
            ->with($on ? 'warning' : 'success', $on
                ? 'Mode maintenance AKTIF. Hanya superadmin, IP pengecualian, dan callback pembayaran yang bisa mengakses.'
                : 'Mode maintenance dimatikan. Aplikasi kembali normal.');
    }

    /** Bersihkan cache konfigurasi, route, view & aplikasi. */
    public function clearCache()
    {
        @set_time_limit(120);

        try {
            Artisan::call('optimize:clear');
            $output = trim(Artisan::output());
            $ok = true;
        } catch (\Throwable $e) {
            $output = $e->getMessage();
            $ok = false;
        }

        SystemSetting::flushCache();

        AuditLog::record('clear_cache', null, 'Bersihkan cache aplikasi (optimize:clear)', ['ok' => $ok, 'output' => $output]);

        return redirect()->route('superadmin.settings.show')
            ->with($ok ? 'success' : 'error', $ok ? 'Cache dibersihkan.' : 'Gagal membersihkan cache: ' . $output);
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function splitLines(string $text): array
    {
        return collect(preg_split('/[\r\n,]+/', $text))->map(fn($v) => trim($v))->filter()->unique()->values()->all();
    }

    private function systemInfo(): array
    {
        try {
            $dbVersion = DB::selectOne('select version() as v')->v ?? '-';
        } catch (\Throwable) {
            $dbVersion = 'tidak terbaca';
        }

        $storage = storage_path();
        $free = @disk_free_space($storage);
        $total = @disk_total_space($storage);

        $heartbeat = Cache::get('scheduler:heartbeat');

        return [
            'php'           => PHP_VERSION,
            'laravel'       => app()->version(),
            'env'           => app()->environment(),
            'debug'         => (bool) config('app.debug'),
            'url'           => config('app.url'),
            'timezone'      => config('app.timezone'),
            'db'            => config('database.default') . ' ' . $dbVersion,
            'cache'         => config('cache.default'),
            'queue'         => config('queue.default'),
            'session'       => config('session.driver'),
            'mail'          => config('mail.default'),
            'config_cached' => app()->configurationIsCached(),
            'routes_cached' => app()->routesAreCached(),
            'storage_link'  => is_link(public_path('storage')) || is_dir(public_path('storage')),
            'logs_writable' => is_writable(storage_path('logs')),
            'disk_free'     => $free !== false ? $free : null,
            'disk_total'    => $total !== false ? $total : null,
            'scheduler'     => $heartbeat ? Carbon::createFromTimestamp($heartbeat)->timezone(self::TZ) : null,
            'scheduler_ok'  => $heartbeat && (time() - (int) $heartbeat) <= 180,
        ];
    }
}