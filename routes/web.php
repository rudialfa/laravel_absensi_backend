//################################# versi web #######################

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

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES - Authentication
|--------------------------------------------------------------------------
*/

Route::get('/', [LoginController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES (Super Admin)
|--------------------------------------------------------------------------
| Role: admin
| Context: Tidak memerlukan context karena super admin
| Access: Manage semua company, users, dan system-wide settings
*/
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        // User Management
        Route::resource('users', UserController::class);

        // Company Management
        Route::resource('companies', CompanyController::class);

        // Attendance Management (All Companies)
        Route::resource('attendances', AttendanceController::class);

        // Permission Management (All Companies)
        Route::resource('permissions', PermissionController::class);

        // Payroll Management (All Companies)
        Route::resource('payrools', PayroolController::class);

        // Loan Management (All Companies)
        Route::resource('loans', LoanController::class);

        // Shift Management (All Companies)
        Route::resource('shifts', ShiftController::class);

        // Schedule Management (All Companies)
        Route::resource('schedules', ScheduleController::class);

        // Prayer Management (System-wide)
        Route::resource('prayers', PrayerController::class);

        // Reports (System-wide)W
        Route::get('/reports', [ReportController::class, 'index'])
            ->name('reports.index');
    });

/*
|--------------------------------------------------------------------------
| COMPANY ROUTES - HR/Admin Company
|--------------------------------------------------------------------------
| Context: company,hr
| Access: HR/Admin dapat mengelola karyawan, absensi, payroll, dll
| Sejajar dengan: API /company/hr/*
*/
Route::middleware(['auth', 'context:company,hr'])
    ->prefix('company')
    ->name('company.')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [CompanyDashboardController::class, 'index'])
            ->name('dashboard');

        // ==========================================
        // ATTENDANCE MANAGEMENT
        // ==========================================
        Route::prefix('attendances')->name('attendances.')->group(function () {
            Route::get('/', [CompanyAttendanceController::class, 'index'])
                ->name('index');
            Route::get('/{id}', [CompanyAttendanceController::class, 'show'])
                ->name('show');
        });

        // ==========================================
        // EMPLOYEE MANAGEMENT
        // ==========================================
        Route::resource('employees', CompanyEmployeeController::class);

        // ==========================================
        // PERMISSION MANAGEMENT
        // ==========================================
        Route::prefix('permissions')->name('permissions.')->group(function () {
            Route::get('/', [CompanyPermissionController::class, 'index'])
                ->name('index');
            Route::post('/{id}/approve', [CompanyPermissionController::class, 'approve'])
                ->name('approve');
            Route::post('/{id}/reject', [CompanyPermissionController::class, 'reject'])
                ->name('reject');
        });

        // ==========================================
        // SHIFT MANAGEMENT
        // ==========================================
        Route::resource('shifts', CompanyShiftController::class);

        // ==========================================
        // PAYROLL MANAGEMENT
        // ==========================================
        Route::prefix('payrolls')->name('payrolls.')->group(function () {
            Route::get('/', [CompanyPayroolsController::class, 'index'])
                ->name('index');
            Route::get('/create', [CompanyPayroolsController::class, 'create'])
                ->name('create');
            Route::post('/', [CompanyPayroolsController::class, 'store'])
                ->name('store');
            Route::get('/{id}', [CompanyPayroolsController::class, 'show'])
                ->name('show');
            Route::get('/{id}/edit', [CompanyPayroolsController::class, 'edit'])
                ->name('edit');
            Route::put('/{id}', [CompanyPayroolsController::class, 'update'])
                ->name('update');
            Route::delete('/{id}', [CompanyPayroolsController::class, 'destroy'])
                ->name('destroy');
            Route::post('/{id}/status', [CompanyPayroolsController::class, 'changeStatus'])
                ->name('changeStatus');
        });

        // ==========================================
        // LOAN MANAGEMENT
        // ==========================================
        Route::prefix('loans')->name('loans.')->group(function () {
            Route::get('/', [CompanyLoansController::class, 'index'])
                ->name('index');
            Route::get('/create', [CompanyLoansController::class, 'create'])
                ->name('create');
            Route::post('/', [CompanyLoansController::class, 'store'])
                ->name('store');
            Route::get('/{id}', [CompanyLoansController::class, 'show'])
                ->name('show');
            Route::get('/{id}/edit', [CompanyLoansController::class, 'edit'])
                ->name('edit');
            Route::put('/{id}', [CompanyLoansController::class, 'update'])
                ->name('update');
            Route::delete('/{id}', [CompanyLoansController::class, 'destroy'])
                ->name('destroy');
            Route::post('/{id}/status', [CompanyLoansController::class, 'changeStatus'])
                ->name('changeStatus');
        });
    });

/*
|--------------------------------------------------------------------------
| EMPLOYEE ROUTES (User/Karyawan)
|--------------------------------------------------------------------------
| Context: company,employee
| Access: Employee dapat melihat data sendiri, absensi, izin, payroll, dll
| Sejajar dengan: API /company/employee/*
*/
Route::middleware(['auth', 'context:company,employee'])
    ->prefix('employee')
    ->name('employee.')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [UserDashboardController::class, 'index'])
            ->name('dashboard');

        // ==========================================
        // ATTENDANCE (Self)
        // ==========================================
        Route::prefix('attendances')->name('attendances.')->group(function () {
            Route::get('/', [UserAttendanceController::class, 'index'])
                ->name('index');
        });

        // ==========================================
        // PERMISSIONS (Self)
        // ==========================================
        Route::resource('permissions', UserPermissionController::class);

        // ==========================================
        // SCHEDULES (Self)
        // ==========================================
        Route::resource('schedules', UserScheduleController::class);

        // ==========================================
        // PAYROLLS (Self - Read Only)
        // ==========================================
        Route::prefix('payrolls')->name('payrolls.')->group(function () {
            Route::get('/', [UserPayrollController::class, 'index'])
                ->name('index');
            Route::get('/{id}', [UserPayrollController::class, 'show'])
                ->name('show');
        });

        // ==========================================
        // LOANS (Self)
        // ==========================================
        Route::resource('loans', UserLoansController::class);

        // ==========================================
        // NOTES (Self - Read Only)
        // ==========================================
        Route::resource('notes', UserNotesController::class)->only(['index', 'show']);
    });

/*
|--------------------------------------------------------------------------
| PESANTREN ROUTES - Ustadz
|--------------------------------------------------------------------------
| Context: pesantren,ustadz
| Access: Ustadz dapat mengelola santri, absensi, jadwal, dll
| Sejajar dengan: API /pesantren/ustadz/*
|
| CATATAN: Controllers belum dibuat, ini hanya struktur routing
*/
Route::middleware(['auth', 'context:pesantren,ustadz'])
    ->prefix('pesantren')
    ->name('pesantren.')
    ->group(function () {

        // Dashboard
        // Route::get('/dashboard', [PesantrenDashboardController::class, 'index'])
        //     ->name('dashboard');

        // ==========================================
        // SANTRI MANAGEMENT
        // ==========================================
        // Route::resource('santri', PesantrenSantriController::class);

        // ==========================================
        // ATTENDANCE MANAGEMENT
        // ==========================================
        // Route::prefix('attendances')->name('attendances.')->group(function () {
        //     Route::get('/', [PesantrenAttendanceController::class, 'index'])
        //         ->name('index');
        //     Route::get('/{id}', [PesantrenAttendanceController::class, 'show'])
        //         ->name('show');
        // });

        // ==========================================
        // PERMISSION MANAGEMENT (Santri)
        // ==========================================
        // Route::prefix('permissions')->name('permissions.')->group(function () {
        //     Route::get('/', [PesantrenPermissionController::class, 'index'])
        //         ->name('index');
        //     Route::post('/{id}/approve', [PesantrenPermissionController::class, 'approve'])
        //         ->name('approve');
        //     Route::post('/{id}/reject', [PesantrenPermissionController::class, 'reject'])
        //         ->name('reject');
        // });

        // ==========================================
        // SCHEDULES MANAGEMENT
        // ==========================================
        // Route::resource('schedules', PesantrenScheduleController::class);

        // ==========================================
        // NOTES MANAGEMENT (untuk santri)
        // ==========================================
        // Route::resource('notes', PesantrenNotesController::class);

        // ==========================================
        // MUTABAAH (Kartu Prestasi Iqro)
        // ==========================================
        // Route::resource('mutabaah', PesantrenMutabaahController::class);

        // ==========================================
        // PRAYER TIMES
        // ==========================================
        // Route::prefix('prayers')->name('prayers.')->group(function () {
        //     Route::get('/', [PesantrenPrayerController::class, 'index'])
        //         ->name('index');
        // });

        // ==========================================
        // DAILY REPORTS
        // ==========================================
        // Route::prefix('daily-reports')->name('daily-reports.')->group(function () {
        //     Route::get('/', [PesantrenDailyReportController::class, 'index'])
        //         ->name('index');
        //     Route::get('/{id}', [PesantrenDailyReportController::class, 'show'])
        //         ->name('show');
        // });

        // ==========================================
        // MONTHLY REPORTS
        // ==========================================
        // Route::prefix('monthly-reports')->name('monthly-reports.')->group(function () {
        //     Route::get('/', [PesantrenMonthlyReportController::class, 'index'])
        //         ->name('index');
        //     Route::get('/{id}', [PesantrenMonthlyReportController::class, 'show'])
        //         ->name('show');
        //     Route::post('/{id}/approve', [PesantrenMonthlyReportController::class, 'approve'])
        //         ->name('approve');
        //     Route::post('/{id}/reject', [PesantrenMonthlyReportController::class, 'reject'])
        //         ->name('reject');
        // });

        // ==========================================
        // PERFORMANCE SCORES
        // ==========================================
        // Route::prefix('performance')->name('performance.')->group(function () {
        //     Route::get('/', [PesantrenPerformanceController::class, 'index'])
        //         ->name('index');
        //     Route::get('/leaderboard', [PesantrenPerformanceController::class, 'leaderboard'])
        //         ->name('leaderboard');
        // });
    });

/*
|--------------------------------------------------------------------------
| PESANTREN ROUTES - Santri
|--------------------------------------------------------------------------
| Context: pesantren,santri
| Access: Santri dapat melihat data sendiri, absensi, jadwal, dll
| Sejajar dengan: API /pesantren/santri/*
|
| CATATAN: Controllers belum dibuat, ini hanya struktur routing
*/
Route::middleware(['auth', 'context:pesantren,santri'])
    ->prefix('santri')
    ->name('santri.')
    ->group(function () {

        // Dashboard
        // Route::get('/dashboard', [SantriDashboardController::class, 'index'])
        //     ->name('dashboard');

        // ==========================================
        // ATTENDANCE (Self)
        // ==========================================
        // Route::prefix('attendances')->name('attendances.')->group(function () {
        //     Route::get('/', [SantriAttendanceController::class, 'index'])
        //         ->name('index');
        // });

        // ==========================================
        // PERMISSIONS (Self)
        // ==========================================
        // Route::resource('permissions', SantriPermissionController::class);

        // ==========================================
        // SCHEDULES (Self - Read Only)
        // ==========================================
        // Route::prefix('schedules')->name('schedules.')->group(function () {
        //     Route::get('/', [SantriScheduleController::class, 'index'])
        //         ->name('index');
        //     Route::get('/{id}', [SantriScheduleController::class, 'show'])
        //         ->name('show');
        // });

        // ==========================================
        // NOTES (Self - Read Only)
        // ==========================================
        // Route::prefix('notes')->name('notes.')->group(function () {
        //     Route::get('/', [SantriNotesController::class, 'index'])
        //         ->name('index');
        //     Route::get('/{id}', [SantriNotesController::class, 'show'])
        //         ->name('show');
        // });

        // ==========================================
        // MUTABAAH (Self - Read Only)
        // ==========================================
        // Route::prefix('mutabaah')->name('mutabaah.')->group(function () {
        //     Route::get('/', [SantriMutabaahController::class, 'index'])
        //         ->name('index');
        //     Route::get('/progress', [SantriMutabaahController::class, 'progress'])
        //         ->name('progress');
        // });

        // ==========================================
        // PRAYER TIMES (Read Only)
        // ==========================================
        // Route::prefix('prayers')->name('prayers.')->group(function () {
        //     Route::get('/', [SantriPrayerController::class, 'index'])
        //         ->name('index');
        // });

        // ==========================================
        // DAILY REPORTS (Self)
        // ==========================================
        // Route::resource('daily-reports', SantriDailyReportController::class);

        // ==========================================
        // MONTHLY REPORTS (Self)
        // ==========================================
        // Route::resource('monthly-reports', SantriMonthlyReportController::class);

        // ==========================================
        // PERFORMANCE (Self - Read Only)
        // ==========================================
        // Route::prefix('performance')->name('performance.')->group(function () {
        //     Route::get('/', [SantriPerformanceController::class, 'index'])
        //         ->name('index');
        // });
    });

/*
|--------------------------------------------------------------------------
| SCHOOL ROUTES - Teacher
|--------------------------------------------------------------------------
| Context: school,teacher
| Access: Teacher dapat mengelola siswa, jadwal, nilai, dll
| Sejajar dengan: API /school/teacher/*
|
| CATATAN: Controllers belum dibuat, ini hanya struktur routing
*/
Route::middleware(['auth', 'context:school,teacher'])
    ->prefix('school')
    ->name('school.')
    ->group(function () {

        // Dashboard
        // Route::get('/dashboard', [SchoolDashboardController::class, 'index'])
        //     ->name('dashboard');

        // ==========================================
        // STUDENT MANAGEMENT
        // ==========================================
        // Route::resource('students', SchoolStudentController::class);

        // ==========================================
        // ATTENDANCE MANAGEMENT
        // ==========================================
        // Route::prefix('attendances')->name('attendances.')->group(function () {
        //     Route::get('/', [SchoolAttendanceController::class, 'index'])
        //         ->name('index');
        //     Route::get('/{id}', [SchoolAttendanceController::class, 'show'])
        //         ->name('show');
        // });

        // ==========================================
        // SCHEDULE MANAGEMENT
        // ==========================================
        // Route::resource('schedules', SchoolScheduleController::class);

        // ==========================================
        // GRADE MANAGEMENT
        // ==========================================
        // Route::resource('grades', SchoolGradeController::class);
    });

/*
|--------------------------------------------------------------------------
| SCHOOL ROUTES - Student
|--------------------------------------------------------------------------
| Context: school,student
| Access: Student dapat melihat data sendiri, jadwal, nilai, dll
| Sejajar dengan: API /school/student/*
|
| CATATAN: Controllers belum dibuat, ini hanya struktur routing
*/
Route::middleware(['auth', 'context:school,student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {

        // Dashboard
        // Route::get('/dashboard', [StudentDashboardController::class, 'index'])
        //     ->name('dashboard');

        // ==========================================
        // ATTENDANCE (Self - Read Only)
        // ==========================================
        // Route::prefix('attendances')->name('attendances.')->group(function () {
        //     Route::get('/', [StudentAttendanceController::class, 'index'])
        //         ->name('index');
        // });

        // ==========================================
        // SCHEDULES (Self - Read Only)
        // ==========================================
        // Route::prefix('schedules')->name('schedules.')->group(function () {
        //     Route::get('/', [StudentScheduleController::class, 'index'])
        //         ->name('index');
        //     Route::get('/{id}', [StudentScheduleController::class, 'show'])
        //         ->name('show');
        // });

        // ==========================================
        // GRADES (Self - Read Only)
        // ==========================================
        // Route::prefix('grades')->name('grades.')->group(function () {
        //     Route::get('/', [StudentGradeController::class, 'index'])
        //         ->name('index');
        // });
    });
