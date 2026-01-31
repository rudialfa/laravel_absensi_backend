<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\Company\CompanyProfileController;
use App\Http\Controllers\Api\Company\DashboardController;
use App\Http\Controllers\Api\Company\CompanyEmployeeController;
use App\Http\Controllers\Api\Company\CompanyAttendanceController;
use App\Http\Controllers\Api\Company\CompanyPermissionController;
use App\Http\Controllers\Api\Company\CompanyLoanController;
use App\Http\Controllers\Api\Company\CompanyPayrollController;
use App\Http\Controllers\Api\Company\CompanyShiftController;
use App\Http\Controllers\Api\Company\CompanyReportController;

// ustadz
use App\Http\Controllers\Api\Ustadz\PesantrenDashboardController;
use App\Http\Controllers\Api\Ustadz\PesantrenUstadzAttendanceController;
use App\Http\Controllers\Api\Ustadz\PesantrenSantriController;
use App\Http\Controllers\Api\Ustadz\PesantrenSchedulesController;

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

            // Route::get('/profile', [AuthController::class, 'show']);
            // Route::post('/profile', [AuthController::class, 'update']);

            Route::get('/attendances', [AttendanceController::class, 'index']);
            Route::post('/attendances/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('/attendances/check-out', [AttendanceController::class, 'checkOut']);
            Route::get('/attendances/is-checkin', [AttendanceController::class, 'isCheckedIn']);

            Route::apiResource('/notes', NoteController::class);
            Route::apiResource('/schedules', ScheduleController::class);
            Route::post('/schedules/{id}/status', [ScheduleController::class, 'updateStatus']);

            Route::get('/payrolls', [PayrollController::class, 'index']);
            Route::get('/payrolls/{id}', [PayrollController::class, 'show']);

            Route::get('/loans', [LoanController::class, 'index']);
            Route::post('/loans', [LoanController::class, 'store']);
            Route::get('/loans/{id}', [LoanController::class, 'show']);
        });

        // =======================
        // 🧑‍💼 HR / ADMIN COMPANY
        // =======================
        Route::middleware('context:company,hr')->group(function () {

            Route::get('/dashboard', [DashboardController::class, 'index']);

            Route::get('/employees', [CompanyEmployeeController::class, 'index']);
            Route::post('/employees', [CompanyEmployeeController::class, 'store']);
            Route::put('/employees/{id}', [CompanyEmployeeController::class, 'update']);
            Route::delete('/employees/{id}', [CompanyEmployeeController::class, 'destroy']);

            Route::get('/attendances', [CompanyAttendanceController::class, 'index']);
            Route::get('/attendances/{id}', [CompanyAttendanceController::class, 'show']);
            Route::patch('/attendances/{id}/overtime', [CompanyAttendanceController::class, 'approveOvertime']);

            Route::get('/permissions', [CompanyPermissionController::class, 'index']);
            Route::post('/permissions/{id}/approve', [CompanyPermissionController::class, 'approve']);
            Route::post('/permissions/{id}/reject', [CompanyPermissionController::class, 'reject']);

            Route::apiResource('/shifts', CompanyShiftController::class);
            Route::patch('/shifts/{id}/default', [CompanyShiftController::class, 'setDefault']);

            Route::apiResource('/payrolls', CompanyPayrollController::class);
            Route::post('/payrolls/{id}/status', [CompanyPayrollController::class, 'changeStatus']);

            Route::apiResource('/loans', CompanyLoanController::class);
            Route::post('/loans/{id}/status', [CompanyLoanController::class, 'changeStatus']);

            Route::get('/reports/attendance', [CompanyReportController::class, 'attendance']);
            Route::get('/reports/permission', [CompanyReportController::class, 'permission']);
            Route::get('/reports/overtime', [CompanyReportController::class, 'overtime']);
            Route::get('/reports/loan', [CompanyReportController::class, 'loan']);
            Route::get('/reports/payroll', [CompanyReportController::class, 'payroll']);
        });
    });


Route::prefix('pesantren')
    ->middleware(['auth:sanctum', 'context:pesantren'])
    ->group(function () {

        // 🧑‍🏫 USTADZ
        Route::middleware('context:pesantren,ustadz')->group(function () {
            // // Profile
            // Route::get('/profile', [AuthController::class, 'show']);
            // Route::post('/profile', [AuthController::class, 'update']);

            // // Dashboard
            Route::get('/dashboard', [PesantrenDashboardController::class, 'ustadz']);

            Route::prefix('attendances')->group(function () {

                // === USTADZ (OWN ATTENDANCE)
                Route::post('/check-in', [PesantrenUstadzAttendanceController::class, 'checkIn']);
                Route::post('/check-out', [PesantrenUstadzAttendanceController::class, 'checkOut']);
                Route::get('/is-checkin', [PesantrenUstadzAttendanceController::class, 'isCheckedIn']);

                // === SANTRI ATTENDANCE
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
                Route::get('/', [PesantrenSchedulesController::class, 'index']);         // list all (punya ustadz ini)
                Route::get('/today', [PesantrenSchedulesController::class, 'today']);   // jadwal hari ini
                Route::post('/', [PesantrenSchedulesController::class, 'store']);       // create
                Route::get('/{id}', [PesantrenSchedulesController::class, 'show']);     // detail
                Route::put('/{id}', [PesantrenSchedulesController::class, 'update']);   // update
                Route::delete('/{id}', [PesantrenSchedulesController::class, 'destroy']); // delete

                // optional status
                Route::post('/{id}/status', [PesantrenSchedulesController::class, 'updateStatus']);
            });


            // // Ustadz Own Attendance
            // Route::prefix('my-attendance')->group(function () {
            //     Route::get('/', [PesantrenAttendanceController::class, 'myAttendances']);
            //     Route::post('/check-in', [PesantrenAttendanceController::class, 'checkIn']);
            //     Route::post('/check-out', [PesantrenAttendanceController::class, 'checkOut']);
            //     Route::get('/is-checkin', [PesantrenAttendanceController::class, 'isCheckedIn']);
            // });

            // // Santri Management
            // Route::prefix('santri')->group(function () {
            //     Route::get('/', [SantriController::class, 'index']);
            //     Route::get('/{id}', [SantriController::class, 'show']);
            //     Route::get('/{id}/attendances', [SantriController::class, 'attendances']);
            //     Route::get('/{id}/hafalan', [SantriController::class, 'hafalan']);
            //     Route::get('/{id}/report', [SantriController::class, 'report']);
            // });

            // // Santri Attendance Management
            // Route::prefix('attendances')->group(function () {
            //     Route::get('/', [PesantrenAttendanceController::class, 'index']);
            //     Route::get('/{id}', [PesantrenAttendanceController::class, 'show']);
            //     Route::post('/mark', [PesantrenAttendanceController::class, 'markAttendance']);
            //     Route::get('/export', [PesantrenAttendanceController::class, 'export']);
            // });

            // // Permission Management (Santri Permissions)
            // Route::prefix('permissions')->group(function () {
            //     Route::get('/', [PesantrenPermissionController::class, 'index']);
            //     Route::get('/pending', [PesantrenPermissionController::class, 'pending']);
            //     Route::get('/{id}', [PesantrenPermissionController::class, 'show']);
            //     Route::post('/{id}/approve', [PesantrenPermissionController::class, 'approve']);
            //     Route::post('/{id}/reject', [PesantrenPermissionController::class, 'reject']);
            // });

            // // Schedule Management
            // Route::prefix('schedules')->group(function () {
            //     Route::get('/my', [PesantrenScheduleController::class, 'mySchedules']);
            //     Route::get('/class', [PesantrenScheduleController::class, 'classSchedules']);
            //     Route::post('/', [PesantrenScheduleController::class, 'store']);
            //     Route::put('/{id}', [PesantrenScheduleController::class, 'update']);
            //     Route::delete('/{id}', [PesantrenScheduleController::class, 'destroy']);
            // });

            // // Hafalan Management
            // Route::prefix('hafalan')->group(function () {
            //     Route::get('/', [HafalanController::class, 'index']);
            //     Route::post('/', [HafalanController::class, 'store']);
            //     Route::get('/{id}', [HafalanController::class, 'show']);
            //     Route::put('/{id}', [HafalanController::class, 'update']);
            //     Route::delete('/{id}', [HafalanController::class, 'destroy']);
            //     Route::post('/{id}/verify', [HafalanController::class, 'verify']);
            // });

            // // Notes
            // Route::apiResource('/notes', PesantrenNoteController::class);

            // // Prayer Times
            // Route::prefix('prayers')->group(function () {
            //     Route::get('/today', [PrayerController::class, 'today']);
            //     Route::get('/month', [PrayerController::class, 'month']);
            // });
        });

        // 👦 SANTRI
        Route::middleware('context:pesantren,santri')->group(function () {
            // // Profile
            // Route::get('/profile', [AuthController::class, 'show']);
            // Route::post('/profile', [AuthController::class, 'update']);

            //     // Dashboard
            //     Route::get('/dashboard', [PesantrenDashboardController::class, 'santri']);

            //     // Attendance
            //     Route::prefix('attendances')->group(function () {
            //         Route::get('/', [PesantrenAttendanceController::class, 'myAttendances']);
            //         Route::post('/check-in', [PesantrenAttendanceController::class, 'checkIn']);
            //         Route::post('/check-out', [PesantrenAttendanceController::class, 'checkOut']);
            //         Route::get('/is-checkin', [PesantrenAttendanceController::class, 'isCheckedIn']);
            //         Route::get('/history', [PesantrenAttendanceController::class, 'history']);
            //         Route::get('/statistics', [PesantrenAttendanceController::class, 'myStatistics']);
            //     });

            //     // Permission (Izin)
            //     Route::prefix('permissions')->group(function () {
            //         Route::get('/', [PesantrenPermissionController::class, 'myPermissions']);
            //         Route::post('/', [PesantrenPermissionController::class, 'store']);
            //         Route::get('/{id}', [PesantrenPermissionController::class, 'show']);
            //         Route::delete('/{id}', [PesantrenPermissionController::class, 'destroy']);
            //     });

            //     // Schedules (Jadwal Pelajaran/Kegiatan)
            //     Route::prefix('schedules')->group(function () {
            //         Route::get('/', [PesantrenScheduleController::class, 'mySchedules']);
            //         Route::get('/today', [PesantrenScheduleController::class, 'today']);
            //         Route::get('/week', [PesantrenScheduleController::class, 'week']);
            //         Route::get('/{id}', [PesantrenScheduleController::class, 'show']);
            //         Route::post('/{id}/status', [PesantrenScheduleController::class, 'updateStatus']);
            //     });

            //     // Hafalan (Tahfidz Progress)
            //     Route::prefix('hafalan')->group(function () {
            //         Route::get('/', [HafalanController::class, 'myHafalan']);
            //         Route::get('/progress', [HafalanController::class, 'myProgress']);
            //         Route::get('/{id}', [HafalanController::class, 'show']);
            //     });

            //     // Notes (Catatan Pribadi)
            //     Route::apiResource('/notes', PesantrenNoteController::class);

            //     // Prayer Times
            //     Route::prefix('prayers')->group(function () {
            //         Route::get('/today', [PrayerController::class, 'today']);
            //         Route::get('/month', [PrayerController::class, 'month']);
            //     });

            //     // Ustadz List
            //     Route::get('/ustadz', [SantriController::class, 'ustadzList']);
            // });

            // Route::prefix('prayers')->group(function () {
            //     Route::get('/today', [PrayerController::class, 'today']);
            //     Route::get('/month', [PrayerController::class, 'month']);
            //     Route::get('/date/{date}', [PrayerController::class, 'byDate']);
            // });
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
