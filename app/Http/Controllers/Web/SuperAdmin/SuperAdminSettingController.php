<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SuperAdminSettingController extends Controller
{
    /**
     * Kunci pengaturan yang bisa diubah lewat halaman ini.
     * Tambahkan kunci baru di sini kalau perlu.
     */
    private array $keys = [
        'app_name',
        'support_email',
        'trial_duration_days',
        'grace_period_days',
        'maintenance_mode',
    ];

    public function show()
    {
        $settings = collect($this->keys)->mapWithKeys(function ($key) {
            return [$key => SystemSetting::get($key)];
        });

        return view('pages.superadmin.settings.show', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'app_name'             => 'nullable|string|max:255',
            'support_email'        => 'nullable|email|max:255',
            'trial_duration_days'  => 'nullable|integer|min:0',
            'grace_period_days'    => 'nullable|integer|min:0',
            'maintenance_mode'     => 'nullable|boolean',
        ]);

        $data['maintenance_mode'] = $request->boolean('maintenance_mode');

        foreach ($data as $key => $value) {
            SystemSetting::set($key, $value);
        }

        AuditLog::record('update_settings', null, 'Pengaturan sistem diperbarui');

        return redirect()
            ->route('superadmin.settings.show')
            ->with('success', 'Pengaturan berhasil disimpan.');
    }
}
