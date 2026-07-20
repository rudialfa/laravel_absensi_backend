<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View; // ← INI YANG KURANG
use Illuminate\Support\ServiceProvider;
use App\View\Composers\SuperAdminGlobalDataComposer;;

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

        View::composer('pages.superadmin.*', \App\View\Composers\SuperAdminGlobalDataComposer::class);
    }
}
