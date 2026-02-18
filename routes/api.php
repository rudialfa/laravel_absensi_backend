<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Employee\EmployeeAttendanceController;
use App\Http\Controllers\Api\Employee\EmployeeLoanController;
use App\Http\Controllers\Api\Employee\EmployeeMonthlyReportController;
use App\Http\Controllers\Api\Employee\EmployeeNotesController;
use App\Http\Controllers\Api\Employee\EmployeePayrollController;

// employee schedules (kamu taruh di ustadz tapi ini controller employee)
use App\Http\Controllers\Api\Employee\EmployeePermissionController;

// hr company
use App\Http\Controllers\Api\Employee\EmployeeSchedulesController;
use App\Http\Controllers\Api\HrCompany\HrCompanyAttendanceController;
use App\Http\Controllers\Api\HrCompany\HrCompanyDashboardController;
use App\Http\Controllers\Api\HrCompany\HrCompanyEmployeeController;
use App\Http\Controllers\Api\HrCompany\HrCompanyLoanController;
use App\Http\Controllers\Api\HrCompany\HrCompanyPayrollController;
use App\Http\Controllers\Api\HrCompany\HrCompanyPermissionController;

// pesantren santri
use App\Http\Controllers\Api\HrCompany\HrCompanyShiftController;
use App\Http\Controllers\Api\Santri\SantriAttendanceController;
use App\Http\Controllers\Api\Santri\SantriNotesController;
use App\Http\Controllers\Api\Santri\SantriPermissionController;
use App\Http\Controllers\Api\Santri\SantriPrayerController;

// pesantren ustadz
use App\Http\Controllers\Api\Santri\SantriSchedulesController;
use App\Http\Controllers\Api\Ustadz\PesantrenDashboardController;
use App\Http\Controllers\Api\Ustadz\PesantrenPrayerController;
use App\Http\Controllers\Api\Ustadz\PesantrenSantriController;
use App\Http\Controllers\Api\Ustadz\PesantrenSchedulesController;
use App\Http\Controllers\Api\Ustadz\PesantrenUstadzAttendanceController;

use App\Http\Controllers\Api\Ustadz\PesantrenUstadzSantriPermissionController;
use Illuminate\Support\Facades\Route;

// ROUTE NEWS 2 #################################################################################

Route::prefix('auth')->group(function () {

    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register-organization', [AuthController::class, 'registerOrganization']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        // Profile Management (Universal untuk semua role)
        Route::get('/profile', [AuthController::class, 'show']);
        Route::post('/profile', [AuthController::class, 'update']);
        Route::post('/upload-face', [AuthController::class, 'uploadFaceEmbedding']);
    });
});


Route::prefix('company')
    ->middleware(['auth:sanctum', 'context:company'])
    ->group(function () {

        // =======================
        // 👨‍💼 EMPLOYEE (karyawan)
        // =======================
        Route::middleware('context:company,employee')->group(function () {

            Route::prefix('employee/attendances')->group(function () {

                Route::post('/check-in', [EmployeeAttendanceController::class, 'checkIn']);
                Route::post('/check-out', [EmployeeAttendanceController::class, 'checkOut']);
                Route::get('/is-checkin', [EmployeeAttendanceController::class, 'isCheckedIn']);

                Route::get('/history', [EmployeeAttendanceController::class, 'history']);

                Route::post('/register-face', [EmployeeAttendanceController::class, 'registerFace']);
            });

            Route::prefix('employee/permissions')->group(function () {
                Route::get('/', [EmployeePermissionController::class, 'index']);
                Route::post('/', [EmployeePermissionController::class, 'store']);
                Route::get('/{id}', [EmployeePermissionController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/cancel', [EmployeePermissionController::class, 'cancel'])->whereNumber('id');
            });

            Route::prefix('employee/notes')->group(function () {
                Route::get('/', [EmployeeNotesController::class, 'index']);
                Route::post('/', [EmployeeNotesController::class, 'store']);
                Route::get('/{id}', [EmployeeNotesController::class, 'show'])->whereNumber('id');
            });

            Route::prefix('employee/monthly-reports')->group(function () {
                Route::get('/status', [EmployeeMonthlyReportController::class, 'status']);
                Route::post('/', [EmployeeMonthlyReportController::class, 'store']);
                Route::get('/current', [EmployeeMonthlyReportController::class, 'current']); // optional
            });

            Route::prefix('employee/schedules')->group(function () {
                Route::get('/', [EmployeeSchedulesController::class, 'index']);
                Route::get('/today', [EmployeeSchedulesController::class, 'today']);
                Route::post('/', [EmployeeSchedulesController::class, 'store']);
                Route::get('/{id}', [EmployeeSchedulesController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [EmployeeSchedulesController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [EmployeeSchedulesController::class, 'destroy'])->whereNumber('id');
                Route::post('/{id}/status', [EmployeeSchedulesController::class, 'updateStatus'])->whereNumber('id');
            });

            Route::prefix('employee/loans')->group(function () {
                Route::get('/', [EmployeeLoanController::class, 'index']);
                Route::post('/', [EmployeeLoanController::class, 'store']);
                Route::get('/{id}', [EmployeeLoanController::class, 'show'])->whereNumber('id');
            });

            Route::prefix('employee/payrolls')->group(function () {
                Route::get('/', [EmployeePayrollController::class, 'index']);
                Route::get('/{id}', [EmployeePayrollController::class, 'show'])->whereNumber('id');
            });
        });

        // =======================
        // 🧑‍💼 HR / ADMIN COMPANY
        // =======================
        Route::middleware('context:company,hr')->group(function () {

            Route::get('/dashboard', [HrCompanyDashboardController::class, 'index']);

            Route::prefix('hr/attendances')->group(function () {

                Route::get('/settings', [HrCompanyAttendanceController::class, 'settings']);
                Route::post('/settings', [HrCompanyAttendanceController::class, 'updateSettings']);

                Route::get('/employees', [HrCompanyAttendanceController::class, 'employeesToday']);
                Route::post('/employees/mark', [HrCompanyAttendanceController::class, 'markEmployeeAttendance']);
                Route::get('/employees/{id}/history', [HrCompanyAttendanceController::class, 'employeeHistory'])->whereNumber('id');

                Route::get('/today', [HrCompanyAttendanceController::class, 'today']);
                Route::get('/history', [HrCompanyAttendanceController::class, 'history']);
                Route::post('/mark-manual', [HrCompanyAttendanceController::class, 'markManual']);
                Route::post('/{id}/approve-overtime', [HrCompanyAttendanceController::class, 'approveOvertime'])->whereNumber('id');
            });

            Route::prefix('hr/employees')->group(function () {
                Route::get('/', [HrCompanyEmployeeController::class, 'index']);
                Route::post('/', [HrCompanyEmployeeController::class, 'store']);
                Route::get('/{id}', [HrCompanyEmployeeController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [HrCompanyEmployeeController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [HrCompanyEmployeeController::class, 'destroy'])->whereNumber('id');
            });

            Route::prefix('hr/permissions')->group(function () {
                Route::get('/', [HrCompanyPermissionController::class, 'index']);
                Route::get('/{id}', [HrCompanyPermissionController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/approve', [HrCompanyPermissionController::class, 'approve'])->whereNumber('id');
                Route::post('/{id}/reject', [HrCompanyPermissionController::class, 'reject'])->whereNumber('id');
            });

            Route::prefix('hr/shifts')->group(function () {
                Route::get('/', [HrCompanyShiftController::class, 'index']);
                Route::post('/', [HrCompanyShiftController::class, 'store']);
                Route::get('/{id}', [HrCompanyShiftController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [HrCompanyShiftController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [HrCompanyShiftController::class, 'destroy'])->whereNumber('id');
                Route::post('/{id}/set-default', [HrCompanyShiftController::class, 'setDefault'])->whereNumber('id');
            });

            Route::prefix('hr/loans')->group(function () {
                Route::get('/', [HrCompanyLoanController::class, 'index']);
                Route::get('/{id}', [HrCompanyLoanController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/approve', [HrCompanyLoanController::class, 'approve'])->whereNumber('id');
                Route::post('/{id}/reject', [HrCompanyLoanController::class, 'reject'])->whereNumber('id');
                Route::post('/{id}/mark-paid', [HrCompanyLoanController::class, 'markPaid'])->whereNumber('id');
            });

            Route::prefix('hr/payrolls')->group(function () {
                Route::get('/', [HrCompanyPayrollController::class, 'index']);
                Route::post('/', [HrCompanyPayrollController::class, 'store']);
                Route::get('/{id}', [HrCompanyPayrollController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [HrCompanyPayrollController::class, 'update'])->whereNumber('id');
                Route::post('/{id}/approve', [HrCompanyPayrollController::class, 'approve'])->whereNumber('id');
                Route::post('/{id}/mark-paid', [HrCompanyPayrollController::class, 'markPaid'])->whereNumber('id');
            });
        });
    });


Route::prefix('pesantren')
    ->middleware(['auth:sanctum', 'context:pesantren'])
    ->group(function () {

        // =========================
        // 🧑‍🏫 USTADZ
        // =========================
        Route::middleware('context:pesantren,ustadz')->group(function () {

            Route::get('/dashboard', [PesantrenDashboardController::class, 'ustadz']);

            Route::prefix('attendances')->group(function () {

                Route::post('/check-in', [PesantrenUstadzAttendanceController::class, 'checkIn']);
                Route::post('/check-out', [PesantrenUstadzAttendanceController::class, 'checkOut']);
                Route::get('/is-checkin', [PesantrenUstadzAttendanceController::class, 'isCheckedIn']);

                Route::get('/santri', [PesantrenUstadzAttendanceController::class, 'santriToday']);
                Route::post('/santri/mark', [PesantrenUstadzAttendanceController::class, 'markSantriAttendance']);
                Route::get('/santri/{id}/history', [PesantrenUstadzAttendanceController::class, 'santriHistory'])->whereNumber('id');
            });

            // ✅ FIX utama konflik: {id} hanya angka
            Route::prefix('santri')->group(function () {

                Route::get('/', [PesantrenSantriController::class, 'index']);
                Route::post('/', [PesantrenSantriController::class, 'store']);

                Route::get('/{id}', [PesantrenSantriController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [PesantrenSantriController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [PesantrenSantriController::class, 'destroy'])->whereNumber('id');

                Route::get('/{id}/attendance', [PesantrenSantriController::class, 'attendance'])->whereNumber('id');
                Route::get('/{id}/permissions', [PesantrenSantriController::class, 'permissions'])->whereNumber('id');
            });

            Route::prefix('schedules')->group(function () {
                Route::get('/', [PesantrenSchedulesController::class, 'index']);
                Route::get('/today', [PesantrenSchedulesController::class, 'today']);
                Route::post('/', [PesantrenSchedulesController::class, 'store']);

                Route::get('/{id}', [PesantrenSchedulesController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [PesantrenSchedulesController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [PesantrenSchedulesController::class, 'destroy'])->whereNumber('id');

                Route::post('/{id}/status', [PesantrenSchedulesController::class, 'updateStatus'])->whereNumber('id');
            });

            Route::prefix('prayers')->group(function () {
                Route::get('/today', [PesantrenPrayerController::class, 'today']);
                Route::get('/{date}', [PesantrenPrayerController::class, 'byDate'])
                    ->where('date', '^\d{4}-\d{2}-\d{2}$');
            });

            Route::prefix('permissions/santri')->group(function () {
                Route::get('/', [PesantrenUstadzSantriPermissionController::class, 'index']);
                Route::get('/{id}', [PesantrenUstadzSantriPermissionController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/approve', [PesantrenUstadzSantriPermissionController::class, 'approve'])->whereNumber('id');
                Route::post('/{id}/reject', [PesantrenUstadzSantriPermissionController::class, 'reject'])->whereNumber('id');
            });
        });

        // =========================
        // 👦 SANTRI
        // =========================
        Route::middleware('context:pesantren,santri')->group(function () {

            Route::prefix('santri/attendances')->group(function () {
                Route::post('/check-in', [SantriAttendanceController::class, 'checkIn']);
                Route::post('/check-out', [SantriAttendanceController::class, 'checkOut']);
                Route::get('/is-checkin', [SantriAttendanceController::class, 'isCheckedIn']);
                Route::get('/history', [SantriAttendanceController::class, 'history']);
                Route::post('/register-face', [SantriAttendanceController::class, 'registerFace']);
            });

            Route::prefix('santri/permissions')->group(function () {
                Route::get('/', [SantriPermissionController::class, 'index']);
                Route::post('/', [SantriPermissionController::class, 'store']);
                Route::get('/{id}', [SantriPermissionController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/cancel', [SantriPermissionController::class, 'cancel'])->whereNumber('id');
            });

            Route::prefix('santri/notes')->group(function () {
                Route::get('/', [SantriNotesController::class, 'index']);
                Route::post('/', [SantriNotesController::class, 'store']);
                Route::get('/{id}', [SantriNotesController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [SantriNotesController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [SantriNotesController::class, 'destroy'])->whereNumber('id');
            });

            Route::prefix('santri/schedules')->group(function () {
                Route::get('/', [SantriSchedulesController::class, 'index']);
                Route::get('/today', [SantriSchedulesController::class, 'today']);
                Route::get('/{id}', [SantriSchedulesController::class, 'show'])->whereNumber('id');
            });

            Route::prefix('prayers')->group(function () {
                Route::get('/today', [SantriPrayerController::class, 'today']);
                Route::get('/{date}', [SantriPrayerController::class, 'byDate'])
                    ->where('date', '^\d{4}-\d{2}-\d{2}$');
            });
        });
    });


Route::prefix('school')
    ->middleware(['auth:sanctum', 'context:school'])
    ->group(function () {

        Route::middleware('context:school,teacher')->group(function () {
            // ...
        });

        Route::middleware('context:school,student')->group(function () {
            // ...
        });
    });

// END ROUTE NEWS 2 #################################################################################
