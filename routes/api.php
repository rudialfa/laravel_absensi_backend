<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Employee\EmployeeAttendanceController;
use App\Http\Controllers\Api\Employee\EmployeeLoanController;
use App\Http\Controllers\Api\Employee\EmployeeNotesController;
use App\Http\Controllers\Api\Employee\EmployeePayrollController;
use App\Http\Controllers\Api\Employee\EmployeePermissionController;

// ustadz
use App\Http\Controllers\Api\Employee\EmployeeSchedulesController;
use App\Http\Controllers\Api\HrCompany\HrCompanyAttendanceController;
use App\Http\Controllers\Api\HrCompany\HrCompanyDashboardController;
use App\Http\Controllers\Api\HrCompany\HrCompanyEmployeeController;

// santri
use App\Http\Controllers\Api\HrCompany\HrCompanyLoanController;


// employee
use App\Http\Controllers\Api\HrCompany\HrCompanyPayrollController;


// hr company

use App\Http\Controllers\Api\HrCompany\HrCompanyPermissionController;
use App\Http\Controllers\Api\HrCompany\HrCompanyShiftController;



use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\Santri\SantriAttendanceController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\Ustadz\PesantrenDashboardController;
use App\Http\Controllers\Api\Ustadz\PesantrenPrayerController;
use App\Http\Controllers\Api\Ustadz\PesantrenSantriController;
use App\Http\Controllers\Api\Ustadz\PesantrenSchedulesController;
use App\Http\Controllers\Api\Ustadz\PesantrenUstadzAttendanceController;
use App\Http\Controllers\Api\Ustadz\PesantrenUstadzSantriPermissionController;
use Illuminate\Http\Request;
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

                // Self attendance
                Route::post('/check-in', [EmployeeAttendanceController::class, 'checkIn']);
                Route::post('/check-out', [EmployeeAttendanceController::class, 'checkOut']);
                Route::get('/is-checkin', [EmployeeAttendanceController::class, 'isCheckedIn']);

                // History employee sendiri
                Route::get('/history', [EmployeeAttendanceController::class, 'history']);

                // Optional: Register/Update Face Embedding (kalau employee juga pakai face)
                Route::post('/register-face', [EmployeeAttendanceController::class, 'registerFace']);
            });

            // ===== Permissions (baru)
            Route::prefix('employee/permissions')->group(function () {
                Route::get('/', [EmployeePermissionController::class, 'index']);
                Route::post('/', [EmployeePermissionController::class, 'store']);
                Route::get('/{id}', [EmployeePermissionController::class, 'show']);
                Route::post('/{id}/cancel', [EmployeePermissionController::class, 'cancel']);
            });

            // ===== Notes (baru)
            Route::prefix('employee/notes')->group(function () {
                Route::get('/', [EmployeeNotesController::class, 'index']);
                Route::post('/', [EmployeeNotesController::class, 'store']);
                Route::get('/{id}', [EmployeeNotesController::class, 'show']);
                Route::put('/{id}', [EmployeeNotesController::class, 'update']);
                Route::delete('/{id}', [EmployeeNotesController::class, 'destroy']);
            });

            // ===== Schedules (baru)
            Route::prefix('employee/schedules')->group(function () {
                Route::get('/', [EmployeeSchedulesController::class, 'index']);
                Route::get('/today', [EmployeeSchedulesController::class, 'today']);
                Route::post('/', [EmployeeSchedulesController::class, 'store']);
                Route::get('/{id}', [EmployeeSchedulesController::class, 'show']);
                Route::put('/{id}', [EmployeeSchedulesController::class, 'update']);
                Route::delete('/{id}', [EmployeeSchedulesController::class, 'destroy']);
                Route::post('/{id}/status', [EmployeeSchedulesController::class, 'updateStatus']);
            });

            // ===== Loans (baru)
            Route::prefix('employee/loans')->group(function () {
                Route::get('/', [EmployeeLoanController::class, 'index']);
                Route::post('/', [EmployeeLoanController::class, 'store']);
                Route::get('/{id}', [EmployeeLoanController::class, 'show']);
            });

            // ===== Payrolls (baru)
            Route::prefix('employee/payrolls')->group(function () {
                Route::get('/', [EmployeePayrollController::class, 'index']);
                Route::get('/{id}', [EmployeePayrollController::class, 'show']);
            });
        });

        // =======================
        // 🧑‍💼 HR / ADMIN COMPANY
        // =======================
        Route::middleware('context:company,hr')->group(function () {

            Route::get('/dashboard', [HrCompanyDashboardController::class, 'index']);


            Route::prefix('hr/attendances')->group(function () {

                // === HR SETTINGS (radius + lokasi kantor) -> update companies table
                Route::get('/settings', [HrCompanyAttendanceController::class, 'settings']);
                Route::post('/settings', [HrCompanyAttendanceController::class, 'updateSettings']);

                // === HR: MARK EMPLOYEE ATTENDANCE (pakai 1 device + face)
                Route::get('/employees', [HrCompanyAttendanceController::class, 'employeesToday']);
                Route::post('/employees/mark', [HrCompanyAttendanceController::class, 'markEmployeeAttendance']);
                Route::get('/employees/{id}/history', [HrCompanyAttendanceController::class, 'employeeHistory']);

                // TAMBAHAN FITUR HR ATTENDANCE
                // ===============================
                Route::get('/today', [HrCompanyAttendanceController::class, 'today']);
                Route::get('/history', [HrCompanyAttendanceController::class, 'history']);
                Route::post('/mark-manual', [HrCompanyAttendanceController::class, 'markManual']);
                Route::post('/{id}/approve-overtime', [HrCompanyAttendanceController::class, 'approveOvertime']);
            });


            // =========================
            // Employees Management (HR)
            // =========================
            Route::prefix('hr/employees')->group(function () {
                Route::get('/', [HrCompanyEmployeeController::class, 'index']);
                Route::post('/', [HrCompanyEmployeeController::class, 'store']);
                Route::get('/{id}', [HrCompanyEmployeeController::class, 'show']);
                Route::put('/{id}', [HrCompanyEmployeeController::class, 'update']);
                Route::delete('/{id}', [HrCompanyEmployeeController::class, 'destroy']);
            });

            // =========================
            // Permissions (HR)
            // =========================
            Route::prefix('hr/permissions')->group(function () {
                Route::get('/', [HrCompanyPermissionController::class, 'index']);
                Route::get('/{id}', [HrCompanyPermissionController::class, 'show']);
                Route::post('/{id}/approve', [HrCompanyPermissionController::class, 'approve']);
                Route::post('/{id}/reject', [HrCompanyPermissionController::class, 'reject']);
            });

            // =========================
            // Shifts (HR)
            // =========================
            Route::prefix('hr/shifts')->group(function () {
                Route::get('/', [HrCompanyShiftController::class, 'index']);
                Route::post('/', [HrCompanyShiftController::class, 'store']);
                Route::get('/{id}', [HrCompanyShiftController::class, 'show']);
                Route::put('/{id}', [HrCompanyShiftController::class, 'update']);
                Route::delete('/{id}', [HrCompanyShiftController::class, 'destroy']);
                Route::post('/{id}/set-default', [HrCompanyShiftController::class, 'setDefault']);
            });

            // =========================
            // Loans (HR)
            // =========================
            Route::prefix('hr/loans')->group(function () {
                Route::get('/', [HrCompanyLoanController::class, 'index']);
                Route::get('/{id}', [HrCompanyLoanController::class, 'show']);
                Route::post('/{id}/approve', [HrCompanyLoanController::class, 'approve']);
                Route::post('/{id}/reject', [HrCompanyLoanController::class, 'reject']);
                Route::post('/{id}/mark-paid', [HrCompanyLoanController::class, 'markPaid']);
            });

            // =========================
            // Payrolls (HR)
            // =========================
            Route::prefix('hr/payrolls')->group(function () {
                Route::get('/', [HrCompanyPayrollController::class, 'index']);
                Route::post('/', [HrCompanyPayrollController::class, 'store']);
                Route::get('/{id}', [HrCompanyPayrollController::class, 'show']);
                Route::put('/{id}', [HrCompanyPayrollController::class, 'update']);
                Route::post('/{id}/approve', [HrCompanyPayrollController::class, 'approve']);
                Route::post('/{id}/mark-paid', [HrCompanyPayrollController::class, 'markPaid']);
            });
        });
    });


Route::prefix('pesantren')
    ->middleware(['auth:sanctum', 'context:pesantren'])
    ->group(function () {

        // 🧑‍🏫 USTADZ
        Route::middleware('context:pesantren,ustadz')->group(function () {
            // // Dashboard
            Route::get('/dashboard', [PesantrenDashboardController::class, 'ustadz']);

            Route::prefix('attendances')->group(function () {

                Route::post('/check-in', [PesantrenUstadzAttendanceController::class, 'checkIn']);
                Route::post('/check-out', [PesantrenUstadzAttendanceController::class, 'checkOut']);
                Route::get('/is-checkin', [PesantrenUstadzAttendanceController::class, 'isCheckedIn']);

                // ---- SANTRI ATTENDANCE (diinput ustadz)
                Route::get('/santri', [PesantrenUstadzAttendanceController::class, 'santriToday']);
                Route::post('/santri/mark', [PesantrenUstadzAttendanceController::class, 'markSantriAttendance']);
                Route::get('/santri/{id}/history', [PesantrenUstadzAttendanceController::class, 'santriHistory']);
            });

            Route::prefix('santri')->group(function () {

                Route::get('/', [PesantrenSantriController::class, 'index']);
                Route::post('/', [PesantrenSantriController::class, 'store']);
                Route::get('/{id}', [PesantrenSantriController::class, 'show']);
                Route::put('/{id}', [PesantrenSantriController::class, 'update']);
                Route::delete('/{id}', [PesantrenSantriController::class, 'destroy']);

                Route::get('/{id}/attendance', [PesantrenSantriController::class, 'attendance']);
                Route::get('/{id}/permissions', [PesantrenSantriController::class, 'permissions']);
            });

            Route::prefix('schedules')->group(function () {
                Route::get('/', [PesantrenSchedulesController::class, 'index']);        // list all (punya ustadz ini)
                Route::get('/today', [PesantrenSchedulesController::class, 'today']);  // jadwal hari ini
                Route::post('/', [PesantrenSchedulesController::class, 'store']);     // create
                Route::get('/{id}', [PesantrenSchedulesController::class, 'show']);  // detail
                Route::put('/{id}', [PesantrenSchedulesController::class, 'update']); // update
                Route::delete('/{id}', [PesantrenSchedulesController::class, 'destroy']); // delete

                // optional status
                Route::post('/{id}/status', [PesantrenSchedulesController::class, 'updateStatus']);
            });

            // Prayers (read)
            Route::prefix('prayers')->group(function () {
                Route::get('/today', [PesantrenPrayerController::class, 'today']);
                Route::get('/{date}', [PesantrenPrayerController::class, 'byDate']); // YYYY-MM-DD
            });

            // (Opsional) Permission santri (kalau ustadz yang approve)
            Route::prefix('permissions/santri')->group(function () {
                Route::get('/', [PesantrenUstadzSantriPermissionController::class, 'index']);
                Route::get('/{id}', [PesantrenUstadzSantriPermissionController::class, 'show']);
                Route::post('/{id}/approve', [PesantrenUstadzSantriPermissionController::class, 'approve']);
                Route::post('/{id}/reject', [PesantrenUstadzSantriPermissionController::class, 'reject']);
            });
        });

        // 👦 SANTRI
        Route::middleware('context:pesantren,santri')->group(function () {

            //     // Dashboard
            //     Route::get('/dashboard', [PesantrenDashboardController::class, 'santri']);

            //     // Attendance
            Route::prefix('santri/attendances')->group(function () {

                // Self attendance (optional, kalau santri juga boleh checkin sendiri)
                Route::post('/check-in', [SantriAttendanceController::class, 'checkIn']);
                Route::post('/check-out', [SantriAttendanceController::class, 'checkOut']);
                Route::get('/is-checkin', [SantriAttendanceController::class, 'isCheckedIn']);

                // History santri sendiri
                Route::get('/history', [SantriAttendanceController::class, 'history']);

                // Register/Update Face Embedding (INI WAJIB)
                Route::post('/register-face', [SantriAttendanceController::class, 'registerFace']);
            });
        });
    });

Route::prefix('school')
    ->middleware(['auth:sanctum', 'context:school'])
    ->group(function () {

        // 👩‍🏫 TEACHER
        Route::middleware('context:school,teacher')->group(function () {
            // Route::get('/classes', [ClassController::class, 'index']);
            // Route::get('/attendance', [SchoolAttendanceController::class, 'index']);
        });

        // 👨‍🎓 STUDENT
        Route::middleware('context:school,student')->group(function () {
            // Route::get('/my-attendance', [SchoolAttendanceController::class, 'my']);
            // Route::get('/schedule', [ScheduleController::class, 'my']);
        });
    });


// END ROUTE NEWS 2 #################################################################################
