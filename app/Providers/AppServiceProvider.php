<?php

namespace App\Providers;

use App\Models\SystemSetting;
use App\View\Composers\SuperAdminGlobalDataComposer;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('pages.superadmin.*', SuperAdminGlobalDataComposer::class);

        // Nama aplikasi dari Pengaturan Sistem (kalau diisi) → dipakai config('app.name'),
        // judul halaman, email, dll. Nilai .env APP_NAME tetap jadi cadangan.
        if ($name = SystemSetting::get('app_name')) {
            config(['app.name' => $name]);
        }
    }
}