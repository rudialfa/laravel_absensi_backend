<?php

use App\Http\Controllers\Backend\Admin\AdminDashboardController;
use App\Http\Controllers\Backend\Admin\AttendanceController;
use App\Http\Controllers\Backend\Admin\CompanyController;
use App\Http\Controllers\Backend\Admin\LoanController;
use App\Http\Controllers\Backend\Admin\PayroolController;
use App\Http\Controllers\Backend\Admin\PermissionController;
use App\Http\Controllers\Backend\Admin\PrayerController;
use App\Http\Controllers\Backend\Admin\ReportController;
use App\Http\Controllers\Backend\Admin\ScheduleController;
use App\Http\Controllers\Backend\Admin\ShiftController;
use App\Http\Controllers\Backend\Admin\UserController;
use App\Http\Controllers\Backend\Auth\LoginController;
use App\Http\Controllers\Backend\Company\CompanyAttendanceController;
use App\Http\Controllers\Backend\Company\CompanyDashboardController;
use App\Http\Controllers\Backend\Company\CompanyEmployeeController;
use App\Http\Controllers\Backend\Company\CompanyLoansController;
use App\Http\Controllers\Backend\Company\CompanyPayroolsController;
use App\Http\Controllers\Backend\Company\CompanyPermissionController;
use App\Http\Controllers\Backend\Company\CompanyShiftController;
use App\Http\Controllers\Backend\User\UserAttendanceController;
use App\Http\Controllers\Backend\User\UserDashboardController;
use App\Http\Controllers\Backend\User\UserLoansController;
use App\Http\Controllers\Backend\User\UserNotesController;
use App\Http\Controllers\Backend\User\UserPayrollController;
use App\Http\Controllers\Backend\User\UserPermissionController;
use App\Http\Controllers\Backend\User\UserScheduleController;
use Illuminate\Support\Facades\Route;


Route::get('/', [LoginController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');


/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        Route::resource('users', UserController::class);
        Route::resource('companies', CompanyController::class);
        Route::resource('attendances', AttendanceController::class);
        Route::resource('permissions', PermissionController::class);
        Route::resource('payrools', PayroolController::class);
        Route::resource('loans', LoanController::class);
        Route::resource('shifts', ShiftController::class);
        Route::resource('schedules', ScheduleController::class);
        Route::resource('prayers', PrayerController::class);

        Route::get('/reports', [ReportController::class, 'index'])
            ->name('reports.index');
    });

/*
|--------------------------------------------------------------------------
| COMPANY ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:company'])
    ->prefix('company')
    ->name('company.')
    ->group(function () {

        Route::get('/dashboard', [CompanyDashboardController::class, 'index'])
            ->name('dashboard');

        // Attendance
        Route::get('/attendances', [CompanyAttendanceController::class, 'index'])
            ->name('attendances.index');
        Route::get('/attendances/{id}', [CompanyAttendanceController::class, 'show'])
            ->name('attendances.show');

        // Employees
        Route::resource('employees', CompanyEmployeeController::class);

        // Permissions
        Route::get('/permissions', [CompanyPermissionController::class, 'index'])
            ->name('permissions.index');
        Route::post('/permissions/{id}/approve', [CompanyPermissionController::class, 'approve'])
            ->name('permissions.approve');
        Route::post('/permissions/{id}/reject', [CompanyPermissionController::class, 'reject'])
            ->name('permissions.reject');

        // Shifts
        Route::resource('shifts', CompanyShiftController::class);

        // Payrolls
        Route::resource('payrolls', CompanyPayroolsController::class);
        Route::post('/payrolls/{id}/status', [CompanyPayroolsController::class, 'changeStatus'])
            ->name('payrolls.changeStatus');

        // Loans
        Route::resource('loans', CompanyLoansController::class);
        Route::post('/loans/{id}/status', [CompanyLoansController::class, 'changeStatus'])
            ->name('loans.changeStatus');
    });

/*
|--------------------------------------------------------------------------
| USER ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:user'])
    ->prefix('user')
    ->name('user.')
    ->group(function () {

        Route::get('/dashboard', [UserDashboardController::class, 'index'])
            ->name('dashboard');

        // Attendance
        Route::get('/attendances', [UserAttendanceController::class, 'index'])
            ->name('attendances.index');

        // Permissions
        Route::resource('permissions', UserPermissionController::class);

        // Schedules
        Route::resource('schedules', UserScheduleController::class);

        // Payrolls
        Route::get('/payrolls', [UserPayrollController::class, 'index'])
            ->name('payrolls.index');
        Route::get('/payrolls/{id}', [UserPayrollController::class, 'show'])
            ->name('payrolls.show');

        // Loans
        Route::resource('loans', UserLoansController::class);

        // Notes
        Route::resource('notes', UserNotesController::class);
    });
