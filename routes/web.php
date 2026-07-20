<?php

use Illuminate\Support\Facades\Route;

// ── AUTH (session, bukan sanctum) ─────────────────────────────
use App\Http\Controllers\Web\Auth\LoginController;

// ── SUPERADMIN ─────────────────────────────────────────────────
use App\Http\Controllers\Web\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminTenantController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminPlanController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminInvoiceController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminStaffController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminAuditLogController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminSettingController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminSupportTicketController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminHelpArticleController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminAppPolicyController;
// ↓↓↓ BARU — sebelumnya belum di-import sama sekali ↓↓↓
use App\Http\Controllers\Web\SuperAdmin\SuperAdminDiscountController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminCompanySubscriptionController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminVaPaymentController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminAnalyticsController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminGlobalSearchController;
use App\Http\Controllers\Web\SuperAdmin\SuperAdminImpersonateController;

// ── COMPANY / HR ────────────────────────────────────────────────
use App\Http\Controllers\Web\Company\HrDashboardController;
use App\Http\Controllers\Web\Company\HrAttendanceController;
use App\Http\Controllers\Web\Company\HrEmployeeController;
use App\Http\Controllers\Web\Company\HrPermissionController;
use App\Http\Controllers\Web\Company\HrPayrollController;
use App\Http\Controllers\Web\Company\HrLoanController;
use App\Http\Controllers\Web\Company\HrShiftController;
use App\Http\Controllers\Web\Company\HrScheduleController;
use App\Http\Controllers\Web\Company\HrHolidayController;

// ── COMPANY / EMPLOYEE (sudah ada — dipertahankan) ───────────────
use App\Http\Controllers\Web\Employee\EmployeeAttendanceWebController;
use App\Http\Controllers\Web\Employee\EmployeeDailyReportWebController;
use App\Http\Controllers\Web\Employee\EmployeeDashboardWebController;
use App\Http\Controllers\Web\Employee\EmployeeHolidayWebController;
use App\Http\Controllers\Web\Employee\EmployeeLeaveWebController;
use App\Http\Controllers\Web\Employee\EmployeeLoanWebController;
use App\Http\Controllers\Web\Employee\EmployeeMonthlyReportWebController;
use App\Http\Controllers\Web\Employee\EmployeeNotesWebController;
use App\Http\Controllers\Web\Employee\EmployeeOvertimeWebController;
use App\Http\Controllers\Web\Employee\EmployeePayrollWebController;
use App\Http\Controllers\Web\Employee\EmployeePerformanceWebController;
use App\Http\Controllers\Web\Employee\EmployeePermissionWebController;
use App\Http\Controllers\Web\Employee\EmployeeScheduleWebController;

// ── PESANTREN / USTADZ ────────────────────────────────────────
use App\Http\Controllers\Web\Ustadz\UstadzDashboardController;
use App\Http\Controllers\Web\Ustadz\UstadzAttendanceController;
use App\Http\Controllers\Web\Ustadz\UstadzSantriController;
use App\Http\Controllers\Web\Ustadz\UstadzMutabaahController;
use App\Http\Controllers\Web\Ustadz\UstadzScheduleController;

// ── PESANTREN / SANTRI (sudah ada — dipertahankan) ───────────────
use App\Http\Controllers\Web\Santri\SantriAttendanceWebController;
use App\Http\Controllers\Web\Santri\SantriDailyReportWebController;
use App\Http\Controllers\Web\Santri\SantriDashboardWebController;
use App\Http\Controllers\Web\Santri\SantriHolidayWebController;
use App\Http\Controllers\Web\Santri\SantriMonthlyReportWebController;
use App\Http\Controllers\Web\Santri\SantriMutabaahWebController;
use App\Http\Controllers\Web\Santri\SantriNotesWebController;
use App\Http\Controllers\Web\Santri\SantriPerformanceWebController;
use App\Http\Controllers\Web\Santri\SantriPermissionWebController;
use App\Http\Controllers\Web\Santri\SantriPrayerWebController;
use App\Http\Controllers\Web\Santri\SantriQuranWebController;
use App\Http\Controllers\Web\Santri\SantriScheduleWebController;

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
Route::post('/impersonate/stop', [SuperAdminImpersonateController::class, 'stop'])
    ->middleware('auth')
    ->name('impersonate.stop');

/*
|--------------------------------------------------------------------------
| SUPERADMIN — context:system,superadmin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'context:system,superadmin'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {

    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

    // Global Search — BARU
    Route::get('/global-search', [SuperAdminGlobalSearchController::class, 'index'])->name('global-search');

    // Tenant (company / pesantren / school)
    Route::prefix('tenants')->name('tenants.')->group(function () {
        Route::get('/', [SuperAdminTenantController::class, 'index'])->name('index');
        Route::get('/{id}', [SuperAdminTenantController::class, 'show'])->name('show');
        Route::post('/{id}/suspend', [SuperAdminTenantController::class, 'suspend'])->name('suspend');
        Route::post('/{id}/activate', [SuperAdminTenantController::class, 'activate'])->name('activate');
        Route::delete('/{id}', [SuperAdminTenantController::class, 'destroy'])->name('destroy');
    });

    // Paket langganan
    Route::resource('plans', SuperAdminPlanController::class);

    // Voucher / Diskon — BARU
    Route::resource('discounts', SuperAdminDiscountController::class)->except(['show']);
    Route::patch('/discounts/{id}/toggle-active', [SuperAdminDiscountController::class, 'toggleActive'])->name('discounts.toggle-active');

    // Langganan Tenant (monitoring & override manual) — BARU
    Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
        Route::get('/', [SuperAdminCompanySubscriptionController::class, 'index'])->name('index');
        Route::get('/{id}', [SuperAdminCompanySubscriptionController::class, 'show'])->name('show');
        Route::post('/{id}/extend', [SuperAdminCompanySubscriptionController::class, 'extend'])->name('extend');
        Route::post('/{id}/change-plan', [SuperAdminCompanySubscriptionController::class, 'changePlan'])->name('change-plan');
        Route::post('/{id}/cancel', [SuperAdminCompanySubscriptionController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/reactivate', [SuperAdminCompanySubscriptionController::class, 'reactivate'])->name('reactivate');
    });

    // Invoice / pembayaran semua tenant
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [SuperAdminInvoiceController::class, 'index'])->name('index');
        Route::get('/{id}', [SuperAdminInvoiceController::class, 'show'])->name('show');
        Route::post('/{id}/verify', [SuperAdminInvoiceController::class, 'verify'])->name('verify');
        Route::post('/{id}/reject', [SuperAdminInvoiceController::class, 'reject'])->name('reject');
    });

    // Monitoring VA Payment & webhook logs — BARU
    Route::prefix('va-payments')->name('va-payments.')->group(function () {
        Route::get('/', [SuperAdminVaPaymentController::class, 'index'])->name('index');
        Route::get('/{id}', [SuperAdminVaPaymentController::class, 'show'])->name('show');
        Route::post('/{id}/mark-paid', [SuperAdminVaPaymentController::class, 'markPaid'])->name('mark-paid');
    });

    // Analytics / Revenue — BARU
    Route::get('/analytics', [SuperAdminAnalyticsController::class, 'index'])->name('analytics');

    // Staff internal superadmin
    Route::resource('staff', SuperAdminStaffController::class);

    // Impersonate start — BARU (masih di dalam context superadmin)
    Route::post('/impersonate/{user}', [SuperAdminImpersonateController::class, 'start'])->name('impersonate.start');

    Route::get('/audit-logs', [SuperAdminAuditLogController::class, 'index'])->name('audit-logs');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SuperAdminSettingController::class, 'show'])->name('show');
        Route::put('/', [SuperAdminSettingController::class, 'update'])->name('update');
    });

    Route::resource('help-articles', SuperAdminHelpArticleController::class)->except(['show']);
    Route::patch('/help-articles/{id}/toggle-publish', [SuperAdminHelpArticleController::class, 'togglePublish'])->name('help-articles.toggle-publish');

    Route::prefix('support-tickets')->name('support-tickets.')->group(function () {
        Route::get('/', [SuperAdminSupportTicketController::class, 'index'])->name('index');
        Route::get('/{id}', [SuperAdminSupportTicketController::class, 'show'])->name('show');
        Route::post('/{id}/reply', [SuperAdminSupportTicketController::class, 'reply'])->name('reply');
        Route::patch('/{id}/status', [SuperAdminSupportTicketController::class, 'updateStatus'])->name('status');
        Route::patch('/{id}/assign', [SuperAdminSupportTicketController::class, 'assign'])->name('assign');
    });

    Route::prefix('app-policies')->name('app-policies.')->group(function () {
        Route::get('/', [SuperAdminAppPolicyController::class, 'index'])->name('index');
        Route::get('/create', [SuperAdminAppPolicyController::class, 'create'])->name('create');
        Route::post('/', [SuperAdminAppPolicyController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [SuperAdminAppPolicyController::class, 'edit'])->name('edit');
        Route::put('/{id}', [SuperAdminAppPolicyController::class, 'update'])->name('update');
        Route::delete('/{id}', [SuperAdminAppPolicyController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/publish', [SuperAdminAppPolicyController::class, 'publish'])->name('publish');
    });
    });

/*
|--------------------------------------------------------------------------
| COMPANY / HR — context:company,hr
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'context:company,hr'])
    ->prefix('company')
    ->name('company.')
    ->group(function () {

    Route::get('/dashboard', [HrDashboardController::class, 'index'])->name('dashboard');

    Route::prefix('attendances')->name('attendances.')->group(function () {
        Route::get('/', [HrAttendanceController::class, 'index'])->name('index');
        Route::get('/{id}', [HrAttendanceController::class, 'show'])->name('show');
        });

    Route::resource('employees', HrEmployeeController::class);

    Route::prefix('permissions')->name('permissions.')->group(function () {
        Route::get('/', [HrPermissionController::class, 'index'])->name('index');
        Route::post('/{id}/approve', [HrPermissionController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [HrPermissionController::class, 'reject'])->name('reject');
        });

    Route::resource('shifts', HrShiftController::class);
    Route::resource('schedules', HrScheduleController::class);
    Route::resource('holidays', HrHolidayController::class);

    Route::prefix('payrolls')->name('payrolls.')->group(function () {
        Route::get('/', [HrPayrollController::class, 'index'])->name('index');
        Route::post('/generate', [HrPayrollController::class, 'generate'])->name('generate');
        Route::get('/{id}', [HrPayrollController::class, 'show'])->name('show');
        Route::patch('/{id}/approve', [HrPayrollController::class, 'approve'])->name('approve');
        Route::patch('/{id}/mark-paid', [HrPayrollController::class, 'markPaid'])->name('mark-paid');
        Route::get('/{id}/slip', [HrPayrollController::class, 'slip'])->name('slip');
        });

    Route::prefix('loans')->name('loans.')->group(function () {
        Route::get('/', [HrLoanController::class, 'index'])->name('index');
        Route::get('/{id}', [HrLoanController::class, 'show'])->name('show');
        Route::put('/{id}/approve', [HrLoanController::class, 'approve'])->name('approve');
        Route::put('/{id}/reject', [HrLoanController::class, 'reject'])->name('reject');
        });
    });

/*
|--------------------------------------------------------------------------
| COMPANY / EMPLOYEE — context:company,employee
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'context:company,employee'])
    ->prefix('employee')
    ->name('employee.')
    ->group(function () {
    // ... (persis seperti "versi salma" yang sudah Anda tulis — dipertahankan apa adanya)
});

/*
|--------------------------------------------------------------------------
| PESANTREN / USTADZ — context:pesantren,ustadz
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'context:pesantren,ustadz'])
    ->prefix('pesantren')
    ->name('pesantren.')
    ->group(function () {

    Route::get('/dashboard', [UstadzDashboardController::class, 'index'])->name('dashboard');

    Route::prefix('attendances')->name('attendances.')->group(function () {
        Route::get('/', [UstadzAttendanceController::class, 'index'])->name('index');
        Route::get('/santri', [UstadzAttendanceController::class, 'santriToday'])->name('santri');
    });

    Route::resource('santri', UstadzSantriController::class);
    Route::resource('mutabaah', UstadzMutabaahController::class);
    Route::resource('schedules', UstadzScheduleController::class);
    });

/*
|--------------------------------------------------------------------------
| PESANTREN / SANTRI — context:pesantren,santri
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'context:pesantren,santri'])
    ->prefix('santri')
    ->name('santri.')
    ->group(function () {
    // ... (persis seperti blok santri yang sudah Anda tulis — dipertahankan apa adanya)
});
