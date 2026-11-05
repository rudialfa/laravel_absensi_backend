<?php

use Illuminate\Support\Facades\Route;

// ── AUTH (session, bukan sanctum) ─────────────────────────────
use App\Http\Controllers\Web\Auth\LoginController;

// ── SUPERADMIN: UMUM ───────────────────────────────────────────
use App\Http\Controllers\Web\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminGlobalSearchController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminTenantController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminUserController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminStaffController;
// use App\Http\Controllers\Web\SuperAdmin\SuperAdminImpersonateController;

// ── SUPERADMIN: LANGGANAN & PEMBAYARAN ─────────────────────────
use App\Http\Controllers\Web\SuperAdmin\SuperAdminPlanController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminDiscountController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminCompanySubscriptionController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminInvoiceController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminVaPaymentController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminVaLogController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminAnalyticsController;

// ── SUPERADMIN: MONITORING KARYAWAN (No. 13–18) ────────────────
use App\Http\Controllers\Web\SuperAdmin\Monitoring\SuperAdminAttendanceController;
use App\Http\Controllers\Web\SuperAdmin\Monitoring\SuperAdminRequestController;
use App\Http\Controllers\Web\SuperAdmin\Monitoring\SuperAdminShiftController;
use App\Http\Controllers\Web\SuperAdmin\Monitoring\SuperAdminHolidayController;
use App\Http\Controllers\Web\SuperAdmin\Monitoring\SuperAdminPayrollController;
use App\Http\Controllers\Web\SuperAdmin\Monitoring\SuperAdminReportController;

// ── SUPERADMIN: MONITORING SEKOLAH (No. 19–26) ─────────────────
use App\Http\Controllers\Web\SuperAdmin\School\SuperAdminSchoolClassController;
use App\Http\Controllers\Web\SuperAdmin\School\SuperAdminDeviceController;
use App\Http\Controllers\Web\SuperAdmin\School\SuperAdminStudentAttendanceController;
use App\Http\Controllers\Web\SuperAdmin\School\SuperAdminAcademicController;
use App\Http\Controllers\Web\SuperAdmin\School\SuperAdminSchoolFinanceController;
use App\Http\Controllers\Web\SuperAdmin\School\SuperAdminPpdbController;
use App\Http\Controllers\Web\SuperAdmin\School\SuperAdminHealthController;
use App\Http\Controllers\Web\SuperAdmin\School\SuperAdminExtracurricularController;

// ── SUPERADMIN: MONITORING PESANTREN (No. 27–29) ───────────────
use App\Http\Controllers\Web\SuperAdmin\Boarding\SuperAdminDormitoryController;
use App\Http\Controllers\Web\SuperAdmin\Boarding\SuperAdminMutabaahController;
use App\Http\Controllers\Web\SuperAdmin\Boarding\SuperAdminBoardingPermissionController;

// ── SUPERADMIN: KONTEN, DUKUNGAN & SISTEM (No. 30–37) ──────────
use App\Http\Controllers\Web\SuperAdmin\SuperAdminSupportTicketController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminHelpArticleController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminAppPolicyController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminNotificationController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminPrayerController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminAuditLogController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminJobController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminSettingController;


/*
|--------------------------------------------------------------------------
| PUBLIC
|--------------------------------------------------------------------------
*/

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| IMPERSONATE STOP — SENGAJA DI LUAR GROUP SUPERADMIN
|--------------------------------------------------------------------------
| Saat impersonate aktif, user yang login BUKAN superadmin lagi, jadi
| middleware context:system,superadmin akan menolaknya. Route ini cuma
| butuh 'auth' biasa supaya siapapun yang sedang di-impersonate tetap
| bisa klik "Kembali ke Superadmin".
*/
// Route::post('/impersonate/stop', [SuperAdminImpersonateController::class, 'stop'])
//     ->middleware('auth')
//     ->name('impersonate.stop');


/*
|--------------------------------------------------------------------------
| SUPERADMIN — context:system,superadmin
|--------------------------------------------------------------------------
| URL diawali /superadmin, nama route diawali superadmin.
*/
Route::middleware(['auth', 'active', 'context:system,superadmin'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {

    // =====================================================================
    // UMUM
    // =====================================================================

    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/global-search', [SuperAdminGlobalSearchController::class, 'index'])->name('global-search');
    Route::get('/analytics', [SuperAdminAnalyticsController::class, 'index'])->name('analytics');

    // Tenant (company / pesantren / school) — CRUD lengkap
    Route::prefix('tenants')->name('tenants.')->group(function () {
        Route::get('/', [SuperAdminTenantController::class, 'index'])->name('index');
        Route::get('/create', [SuperAdminTenantController::class, 'create'])->name('create');
        Route::post('/', [SuperAdminTenantController::class, 'store'])->name('store');
        Route::get('/{id}', [SuperAdminTenantController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [SuperAdminTenantController::class, 'edit'])->name('edit');
        Route::put('/{id}', [SuperAdminTenantController::class, 'update'])->name('update');
        Route::post('/{id}/suspend', [SuperAdminTenantController::class, 'suspend'])->name('suspend');
        Route::post('/{id}/activate', [SuperAdminTenantController::class, 'activate'])->name('activate');
        Route::delete('/{id}', [SuperAdminTenantController::class, 'destroy'])->name('destroy');
    });

    // Pengguna (semua user tenant) — CRUD lengkap
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [SuperAdminUserController::class, 'index'])->name('index');
        Route::get('/create', [SuperAdminUserController::class, 'create'])->name('create');
        Route::post('/', [SuperAdminUserController::class, 'store'])->name('store');
        Route::get('/{id}', [SuperAdminUserController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [SuperAdminUserController::class, 'edit'])->name('edit');
        Route::put('/{id}', [SuperAdminUserController::class, 'update'])->name('update');
        Route::patch('/{id}/toggle-active', [SuperAdminUserController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{id}/reset-password', [SuperAdminUserController::class, 'resetPassword'])->name('reset-password');
        Route::post('/{id}/logout-all', [SuperAdminUserController::class, 'logoutAll'])->name('logout-all');
        Route::delete('/{id}', [SuperAdminUserController::class, 'destroy'])->name('destroy');
    });

    // Staff superadmin — CRUD lengkap + aktif/nonaktif
    Route::patch('staff/{id}/toggle-active', [SuperAdminStaffController::class, 'toggleActive'])->name('staff.toggle-active');
    Route::resource('staff', SuperAdminStaffController::class);

    // Impersonate start (masih di dalam context superadmin)
    // Route::post('/impersonate/{user}', [SuperAdminImpersonateController::class, 'start'])->name('impersonate.start');


    // =====================================================================
    // LANGGANAN & PEMBAYARAN
    // =====================================================================

    // Paket langganan — CRUD lengkap + aktif/nonaktif
    Route::patch('plans/{id}/toggle-active', [SuperAdminPlanController::class, 'toggleActive'])->name('plans.toggle-active');
    Route::resource('plans', SuperAdminPlanController::class);

    // Voucher / Diskon — CRUD lengkap + aktif/nonaktif
    Route::patch('discounts/{id}/toggle-active', [SuperAdminDiscountController::class, 'toggleActive'])->name('discounts.toggle-active');
    Route::resource('discounts', SuperAdminDiscountController::class);

    // Langganan Tenant — CRUD lengkap + aksi manual
    Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
        Route::get('/', [SuperAdminCompanySubscriptionController::class, 'index'])->name('index');
        Route::get('/create', [SuperAdminCompanySubscriptionController::class, 'create'])->name('create');
        Route::post('/', [SuperAdminCompanySubscriptionController::class, 'store'])->name('store');
        Route::get('/{id}', [SuperAdminCompanySubscriptionController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [SuperAdminCompanySubscriptionController::class, 'edit'])->name('edit');
        Route::put('/{id}', [SuperAdminCompanySubscriptionController::class, 'update'])->name('update');
        Route::post('/{id}/extend', [SuperAdminCompanySubscriptionController::class, 'extend'])->name('extend');
        Route::post('/{id}/change-plan', [SuperAdminCompanySubscriptionController::class, 'changePlan'])->name('change-plan');
        Route::post('/{id}/cancel', [SuperAdminCompanySubscriptionController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/reactivate', [SuperAdminCompanySubscriptionController::class, 'reactivate'])->name('reactivate');
        Route::post('/{id}/sync', [SuperAdminCompanySubscriptionController::class, 'sync'])->name('sync');
        Route::delete('/{id}', [SuperAdminCompanySubscriptionController::class, 'destroy'])->name('destroy');
    });

    // Invoice — CRUD lengkap + verifikasi, VA, cetak, export
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [SuperAdminInvoiceController::class, 'index'])->name('index');
        Route::get('/export', [SuperAdminInvoiceController::class, 'export'])->name('export');
        Route::get('/create', [SuperAdminInvoiceController::class, 'create'])->name('create');
        Route::post('/', [SuperAdminInvoiceController::class, 'store'])->name('store');
        Route::get('/{id}', [SuperAdminInvoiceController::class, 'show'])->name('show');
        Route::get('/{id}/print', [SuperAdminInvoiceController::class, 'print'])->name('print');
        Route::get('/{id}/edit', [SuperAdminInvoiceController::class, 'edit'])->name('edit');
        Route::put('/{id}', [SuperAdminInvoiceController::class, 'update'])->name('update');
        Route::post('/{id}/verify', [SuperAdminInvoiceController::class, 'verify'])->name('verify');
        Route::post('/{id}/reject', [SuperAdminInvoiceController::class, 'reject'])->name('reject');
        Route::post('/{id}/expire', [SuperAdminInvoiceController::class, 'markExpired'])->name('expire');
        Route::post('/{id}/create-va', [SuperAdminInvoiceController::class, 'createVa'])->name('create-va');
        Route::post('/{id}/check-status', [SuperAdminInvoiceController::class, 'checkStatus'])->name('check-status');
        Route::delete('/{id}', [SuperAdminInvoiceController::class, 'destroy'])->name('destroy');
    });

    // VA Payment — monitoring + tindak lanjut
    Route::prefix('va-payments')->name('va-payments.')->group(function () {
        Route::get('/', [SuperAdminVaPaymentController::class, 'index'])->name('index');
        Route::get('/{id}', [SuperAdminVaPaymentController::class, 'show'])->name('show');
        Route::post('/{id}/check-status', [SuperAdminVaPaymentController::class, 'checkStatus'])->name('check-status');
        Route::post('/{id}/mark-paid', [SuperAdminVaPaymentController::class, 'markPaid'])->name('mark-paid');
        Route::post('/{id}/close', [SuperAdminVaPaymentController::class, 'close'])->name('close');
    });

    // Log Callback Bank (Midtrans) — lihat saja + bersihkan log lama
    Route::prefix('va-logs')->name('va-logs.')->group(function () {
        Route::get('/', [SuperAdminVaLogController::class, 'index'])->name('index');
        Route::delete('/prune', [SuperAdminVaLogController::class, 'prune'])->name('prune');
        Route::get('/{id}', [SuperAdminVaLogController::class, 'show'])->name('show');
    });


    // =====================================================================
    // MONITORING KARYAWAN (No. 13–18)  →  /superadmin/monitoring/...
    // =====================================================================
    Route::prefix('monitoring')->name('monitoring.')->group(function () {

        // No. 13 Absensi
        Route::prefix('attendances')->name('attendances.')->group(function () {
            Route::get('/', [SuperAdminAttendanceController::class, 'index'])->name('index');
            Route::get('/export', [SuperAdminAttendanceController::class, 'export'])->name('export');
            Route::get('/{id}', [SuperAdminAttendanceController::class, 'show'])->name('show');
            Route::delete('/{id}', [SuperAdminAttendanceController::class, 'destroy'])->name('destroy');
        });

        // No. 14 Izin, Cuti & Lembur
        Route::prefix('requests')->name('requests.')->group(function () {
            Route::get('/', [SuperAdminRequestController::class, 'index'])->name('index');
            Route::get('/export', [SuperAdminRequestController::class, 'export'])->name('export');
            Route::get('/{tab}/{id}', [SuperAdminRequestController::class, 'show'])->whereIn('tab', ['permissions', 'leaves', 'overtimes'])->name('show');
            Route::delete('/{tab}/{id}', [SuperAdminRequestController::class, 'destroy'])->whereIn('tab', ['permissions', 'leaves', 'overtimes'])->name('destroy');
        });

        // No. 15 Shift & Jadwal
        Route::prefix('shifts')->name('shifts.')->group(function () {
            Route::get('/', [SuperAdminShiftController::class, 'index'])->name('index');
            Route::get('/{type}/{id}', [SuperAdminShiftController::class, 'show'])->whereIn('type', ['shifts', 'groups', 'schedules'])->name('show');
            Route::delete('/{type}/{id}', [SuperAdminShiftController::class, 'destroy'])->whereIn('type', ['shifts', 'groups', 'assignments', 'overrides', 'schedules'])->name('destroy');
        });

        // No. 16 Hari Libur (CRUD)
        Route::resource('holidays', SuperAdminHolidayController::class)->except(['show']);

        // No. 17 Payroll & Pinjaman
        Route::prefix('payrolls')->name('payrolls.')->group(function () {
            Route::get('/', [SuperAdminPayrollController::class, 'index'])->name('index');
            Route::get('/export', [SuperAdminPayrollController::class, 'export'])->name('export');
            Route::get('/{tab}/{id}', [SuperAdminPayrollController::class, 'show'])->whereIn('tab', ['payrolls', 'loans'])->name('show');
            Route::delete('/{tab}/{id}', [SuperAdminPayrollController::class, 'destroy'])->whereIn('tab', ['payrolls', 'loans'])->name('destroy');
        });

        // No. 18 Laporan & Kinerja
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [SuperAdminReportController::class, 'index'])->name('index');
            Route::get('/export', [SuperAdminReportController::class, 'export'])->name('export');
            Route::get('/{tab}/{id}', [SuperAdminReportController::class, 'show'])->whereIn('tab', ['daily', 'monthly', 'notes'])->name('show');
            Route::delete('/{tab}/{id}', [SuperAdminReportController::class, 'destroy'])->whereIn('tab', ['daily', 'monthly', 'notes'])->name('destroy');
        });
    }); // ← akhir grup monitoring


    // =====================================================================
    // MONITORING SEKOLAH (No. 19–26)  →  /superadmin/school/...
    // =====================================================================
    Route::prefix('school')->name('school.')->group(function () {

        // No. 19 Kelas, Murid & Wali
        Route::prefix('classes')->name('classes.')->controller(SuperAdminSchoolClassController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::get('/{id}', 'showClass')->whereNumber('id')->name('show');
            Route::patch('/{id}/toggle-active', 'toggleClass')->whereNumber('id')->name('toggle-active');
            Route::delete('/{id}', 'destroyClass')->whereNumber('id')->name('destroy');
        });

        Route::prefix('students')->name('students.')->controller(SuperAdminSchoolClassController::class)->group(function () {
            Route::get('/{id}', 'showStudent')->whereNumber('id')->name('show');
            Route::delete('/{id}', 'destroyStudent')->whereNumber('id')->name('destroy');
            Route::patch('/{id}/restore', 'restoreStudent')->whereNumber('id')->name('restore');
            Route::delete('/{student}/guardians/{link}', 'detachGuardian')->whereNumber(['student', 'link'])->name('guardians.destroy');
        });

        Route::delete('/class-teachers/{id}', [SuperAdminSchoolClassController::class, 'detachTeacher'])
            ->whereNumber('id')->name('class-teachers.destroy');

        // No. 20 Perangkat Kiosk (export, toggle-active, regenerate-token WAJIB di atas resource)
        Route::get('/devices/export', [SuperAdminDeviceController::class, 'export'])->name('devices.export');
        Route::patch('/devices/{id}/toggle-active', [SuperAdminDeviceController::class, 'toggleActive'])->whereNumber('id')->name('devices.toggle-active');
        Route::post('/devices/{id}/regenerate-token', [SuperAdminDeviceController::class, 'regenerateToken'])->whereNumber('id')->name('devices.regenerate-token');
        Route::resource('devices', SuperAdminDeviceController::class)->parameters(['devices' => 'id']);

        // No. 21 Absen & Izin Murid
        Route::prefix('attendances')->name('attendances.')->controller(SuperAdminStudentAttendanceController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::get('/{tab}/{id}', 'show')->whereIn('tab', ['attendances', 'permissions'])->whereNumber('id')->name('show');
            Route::delete('/{tab}/{id}', 'destroy')->whereIn('tab', ['attendances', 'permissions'])->whereNumber('id')->name('destroy');
        });

        // No. 22 Akademik
        Route::prefix('academics')->name('academics.')->controller(SuperAdminAcademicController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::get('/{tab}/{id}', 'show')->whereIn('tab', ['curricula', 'assignments'])->whereNumber('id')->name('show');
            Route::delete('/{tab}/{id}', 'destroy')
                ->whereIn('tab', ['subjects', 'curricula', 'schedules', 'events', 'grades', 'promotions', 'materials', 'assignments'])
                ->whereNumber('id')->name('destroy');
        });

        // No. 23 Keuangan Sekolah
        Route::prefix('finance')->name('finance.')->controller(SuperAdminSchoolFinanceController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::get('/bills/{id}', 'show')->whereNumber('id')->name('show');
            Route::patch('/bills/{id}/resync', 'resync')->whereNumber('id')->name('resync');
            Route::delete('/{tab}/{id}', 'destroy')->whereIn('tab', ['bills', 'payments', 'types'])->whereNumber('id')->name('destroy');
        });

        // No. 24 PPDB & Mutasi
        Route::prefix('ppdb')->name('ppdb.')->controller(SuperAdminPpdbController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::get('/{tab}/{id}', 'show')->whereIn('tab', ['periods', 'applicants'])->whereNumber('id')->name('show');
            Route::delete('/{tab}/{id}', 'destroy')->whereIn('tab', ['periods', 'applicants', 'mutations'])->whereNumber('id')->name('destroy');
        });

        // No. 25 Kesehatan / UKS (hanya lihat)
        Route::prefix('health')->name('health.')->controller(SuperAdminHealthController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::get('/students/{studentId}', 'show')->whereNumber('studentId')->name('show');
        });

        // No. 26 Ekskul & Prestasi
        Route::prefix('extracurriculars')->name('extracurriculars.')->controller(SuperAdminExtracurricularController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
            Route::delete('/{tab}/{id}', 'destroy')->whereIn('tab', ['extracurriculars', 'achievements'])->whereNumber('id')->name('destroy');
        });
    }); // ← akhir grup school


    // =====================================================================
    // MONITORING PESANTREN / ASRAMA (No. 27–29)  →  /superadmin/boarding/...
    // =====================================================================
    Route::prefix('boarding')->name('boarding.')->group(function () {

        // No. 27 Asrama & Kamar
        Route::prefix('dormitories')->name('dormitories.')->controller(SuperAdminDormitoryController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
            Route::patch('/assignments/{id}/checkout', 'checkout')->whereNumber('id')->name('checkout');
            Route::delete('/{tab}/{id}', 'destroy')->whereIn('tab', ['dormitories', 'rooms'])->whereNumber('id')->name('destroy');
        });

        // No. 28 Mutaba'ah & Tahfidz
        Route::prefix('mutabaah')->name('mutabaah.')->controller(SuperAdminMutabaahController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::get('/santri/{userId}', 'santri')->whereNumber('userId')->name('santri');
            Route::delete('/{tab}/{id}', 'destroy')->whereIn('tab', ['mutabaah', 'tahfidz'])->whereNumber('id')->name('destroy');
        });

        // No. 29 Izin Asrama
        Route::prefix('permissions')->name('permissions.')->controller(SuperAdminBoardingPermissionController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
        });
    }); // ← akhir grup boarding


    // =====================================================================
    // KONTEN & DUKUNGAN (No. 30–33)
    // =====================================================================

    // No. 30 Tiket Bantuan
    Route::prefix('support-tickets')->name('support-tickets.')->controller(SuperAdminSupportTicketController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/export', 'export')->name('export');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        Route::post('/{id}/reply', 'reply')->whereNumber('id')->name('reply');
        Route::patch('/{id}/status', 'updateStatus')->whereNumber('id')->name('status');
        Route::patch('/{id}/assign', 'assign')->whereNumber('id')->name('assign');
        Route::patch('/{id}', 'update')->whereNumber('id')->name('update');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
    });

    // No. 31 Bantuan / FAQ (CRUD lengkap + detail + terbit/sembunyikan)
    Route::patch('help-articles/{id}/toggle-publish', [SuperAdminHelpArticleController::class, 'togglePublish'])->name('help-articles.toggle-publish');
    Route::resource('help-articles', SuperAdminHelpArticleController::class);

    // No. 32 Kebijakan Aplikasi
    Route::prefix('app-policies')->name('app-policies.')->controller(SuperAdminAppPolicyController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update');
        Route::post('/{id}/publish', 'publish')->whereNumber('id')->name('publish');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
    });

    // No. 33 Kirim Notifikasi (pengumuman massal)
    Route::prefix('notifications')->name('notifications.')->controller(SuperAdminNotificationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/estimate', 'estimate')->name('estimate');
        Route::post('/test', 'test')->name('test');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update');
        Route::post('/{id}/send-now', 'sendNow')->whereNumber('id')->name('send-now');
        Route::patch('/{id}/cancel', 'cancel')->whereNumber('id')->name('cancel');
        Route::post('/{id}/retract', 'retract')->whereNumber('id')->name('retract');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
    });


    // =====================================================================
    // SISTEM (No. 34–37)
    // =====================================================================

    // No. 34 Jadwal Sholat
    Route::prefix('prayers')->name('prayers.')->controller(SuperAdminPrayerController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/export', 'export')->name('export');
        Route::post('/fetch', 'fetch')->name('fetch');
        Route::post('/save', 'save')->name('save');
        Route::delete('/month', 'destroyMonth')->name('destroy-month');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
    });

    // No. 35 Audit Log (hanya lihat + export)
    Route::get('/audit-logs', [SuperAdminAuditLogController::class, 'index'])->name('audit-logs');
    Route::get('/audit-logs/export', [SuperAdminAuditLogController::class, 'export'])->name('audit-logs.export');
    Route::get('/audit-logs/{id}', [SuperAdminAuditLogController::class, 'show'])->whereNumber('id')->name('audit-logs.show');

    // No. 36 Antrian Job & Scheduler
    Route::prefix('jobs')->name('jobs.')->controller(SuperAdminJobController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{kind}/{id}', 'show')->whereIn('kind', ['pending', 'failed'])->whereNumber('id')->name('show');
        Route::post('/test', 'test')->name('test');
        Route::post('/run', 'run')->name('run');
        Route::post('/unlock', 'unlock')->name('unlock');
        Route::post('/failed/retry-all', 'retryAll')->name('retry-all');
        Route::post('/failed/{id}/retry', 'retry')->whereNumber('id')->name('retry');
        Route::delete('/failed', 'flushFailed')->name('flush');
        Route::delete('/{kind}/{id}', 'destroy')->whereIn('kind', ['pending', 'failed'])->whereNumber('id')->name('destroy');
    });

    // No. 37 Pengaturan Sistem
    Route::prefix('settings')->name('settings.')->controller(SuperAdminSettingController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::put('/', 'update')->name('update');
        Route::post('/toggle-maintenance', 'toggleMaintenance')->name('toggle-maintenance');
        Route::post('/clear-cache', 'clearCache')->name('clear-cache');
    });
    }); // ← akhir grup SUPERADMIN (JANGAN dihapus)