<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Public\SupportTicketController;
use App\Http\Controllers\Api\Public\HelpArticleController;
use App\Http\Controllers\Api\Public\AppPolicyController;
use App\Http\Controllers\Api\System\CompanyController;

use App\Http\Controllers\Api\ChatMessage\ChatController;
use App\Http\Controllers\Api\Employee\EmployeeAttendanceController;
use App\Http\Controllers\Api\Employee\EmployeeDailyReportController;
use App\Http\Controllers\Api\Employee\EmployeeHolidayController;
use App\Http\Controllers\Api\Employee\EmployeeLeaveController;
use App\Http\Controllers\Api\Employee\EmployeeLoanController;
use App\Http\Controllers\Api\Employee\EmployeeMonthlyReportController;
use App\Http\Controllers\Api\Employee\EmployeeNotesController;
use App\Http\Controllers\Api\Employee\EmployeeOvertimeRequestController;
use App\Http\Controllers\Api\Employee\EmployeePayrollController;
use App\Http\Controllers\Api\Employee\EmployeePerformanceScoreController;
use App\Http\Controllers\Api\Employee\EmployeePermissionController;
use App\Http\Controllers\Api\Employee\EmployeeSchedulesController;
use App\Http\Controllers\Api\Employee\EmployeeShiftController;
use App\Http\Controllers\Api\HrCompany\HrCompanyAnalyticsController;
use App\Http\Controllers\Api\HrCompany\HrCompanyAttendanceController;
use App\Http\Controllers\Api\HrCompany\HrCompanyDailyReportController;
use App\Http\Controllers\Api\HrCompany\HrCompanyDashboardController;
use App\Http\Controllers\Api\HrCompany\HrCompanyEmployeeController;
use App\Http\Controllers\Api\HrCompany\HrCompanyHolidayController;
use App\Http\Controllers\Api\HrCompany\HrCompanyLeaveController;
use App\Http\Controllers\Api\HrCompany\HrCompanyLoanController;
use App\Http\Controllers\Api\HrCompany\HrCompanyMonthlyReportController;
use App\Http\Controllers\Api\HrCompany\HrCompanyNotesController;
use App\Http\Controllers\Api\HrCompany\HrCompanyOvertimeRequestController;
use App\Http\Controllers\Api\HrCompany\HrCompanyPayrollComponentController;
use App\Http\Controllers\Api\HrCompany\HrCompanyPayrollController;
use App\Http\Controllers\Api\HrCompany\HrCompanyPerformanceScoreController;
use App\Http\Controllers\Api\HrCompany\HrCompanyPermissionController;
use App\Http\Controllers\Api\HrCompany\HrCompanyScheduleController;
use App\Http\Controllers\Api\HrCompany\HrCompanySettingController;
use App\Http\Controllers\Api\HrCompany\HrCompanyShiftController;
use App\Http\Controllers\Api\HrCompany\HrCompanyShiftGroupAssignmentController;
use App\Http\Controllers\Api\HrCompany\HrCompanyShiftGroupController;
use App\Http\Controllers\Api\HrCompany\HrCompanyShiftGroupUserController;
use App\Http\Controllers\Api\HrCompany\HrCompanyUserShiftOverrideController;
use App\Http\Controllers\Api\Payment\MidtransCallbackController;
use App\Http\Controllers\Api\Payment\BcaWebhookController;
use App\Http\Controllers\Api\Payment\SubscriptionController;
use App\Http\Controllers\Api\Santri\SantriAttendanceController;
use App\Http\Controllers\Api\Santri\SantriDailyReportController;
use App\Http\Controllers\Api\Santri\Santridashboardcontroller;
use App\Http\Controllers\Api\Santri\Santriholidaycontroller;
use App\Http\Controllers\Api\Santri\SantriMonthlyReportController;
use App\Http\Controllers\Api\Santri\SantriMutabaahController;
use App\Http\Controllers\Api\Santri\SantriNotesController;
use App\Http\Controllers\Api\Santri\Santriperformancecontroller;
use App\Http\Controllers\Api\Santri\SantriPermissionController;
use App\Http\Controllers\Api\Santri\SantriPrayerController;
use App\Http\Controllers\Api\Santri\Santriqurancontroller;
use App\Http\Controllers\Api\Santri\SantriSchedulesController;
use App\Http\Controllers\Api\Ustadz\PesantrenAnalyticsController;
use App\Http\Controllers\Api\Ustadz\PesantrenDailyReportController;
use App\Http\Controllers\Api\Ustadz\PesantrenDashboardController;
use App\Http\Controllers\Api\Ustadz\PesantrenHolidayController;
use App\Http\Controllers\Api\Ustadz\PesantrenMonthlyReportController;
use App\Http\Controllers\Api\Ustadz\PesantrenMutabaahController;
use App\Http\Controllers\Api\Ustadz\PesantrenNotesController;
use App\Http\Controllers\Api\Ustadz\PesantrenPerformanceController;
use App\Http\Controllers\Api\Ustadz\PesantrenPrayerController;
use App\Http\Controllers\Api\Ustadz\Pesantrenqurancontroller;
use App\Http\Controllers\Api\Ustadz\PesantrenSantriController;
use App\Http\Controllers\Api\Ustadz\PesantrenSchedulesController;
use App\Http\Controllers\Api\Ustadz\PesantrenSettingController;
use App\Http\Controllers\Api\Ustadz\PesantrenUstadzAttendanceController;
use App\Http\Controllers\Api\Ustadz\PesantrenUstadzSantriPermissionController;

use App\Http\Controllers\Api\School\Admin\ClassRoomController;
use App\Http\Controllers\Api\School\Admin\StaffController;
use App\Http\Controllers\Api\School\Admin\StudentController;
use App\Http\Controllers\Api\School\Admin\TeacherAssignmentController;
use App\Http\Controllers\Api\School\Admin\AttendanceDeviceController as AdminAttendanceDeviceController;
use App\Http\Controllers\Api\School\Admin\StudentPermissionController as AdminStudentPermissionController;
use App\Http\Controllers\Api\School\Admin\DormitoryController;
use App\Http\Controllers\Api\School\Admin\DormitoryRoomController;
use App\Http\Controllers\Api\School\Admin\RoomAssignmentController;
use App\Http\Controllers\Api\School\Admin\AdminTahfidzController;
use App\Http\Controllers\Api\School\Admin\AdminMutabaahController;
use App\Http\Controllers\Api\School\Admin\AdminBoardingPermissionController;
use App\Http\Controllers\Api\School\Admin\CurriculumController;
use App\Http\Controllers\Api\School\Admin\CurriculumSubjectController;
use App\Http\Controllers\Api\School\Admin\LessonScheduleController;
use App\Http\Controllers\Api\School\Admin\AcademicEventController;
use App\Http\Controllers\Api\School\Admin\ClassPromotionController;
use App\Http\Controllers\Api\School\Admin\StudentBillController;
use App\Http\Controllers\Api\School\Admin\BillTypeController;
use App\Http\Controllers\Api\School\Admin\FinanceReportController;
use App\Http\Controllers\Api\School\Admin\PpdbPeriodController;
use App\Http\Controllers\Api\School\Admin\PpdbApplicantController;
use App\Http\Controllers\Api\School\Admin\SubjectController;
use App\Http\Controllers\Api\School\Admin\AdminGradeController;
use App\Http\Controllers\Api\School\Admin\StudentMutationController;
use App\Http\Controllers\Api\School\Admin\AdminUksController;
use App\Http\Controllers\Api\School\Admin\ExtracurricularController;
use App\Http\Controllers\Api\School\Admin\StudentAchievementController;
use App\Http\Controllers\Api\School\Admin\AdminDashboardController;

use App\Http\Controllers\Api\School\Guru\GuruClassController;
use App\Http\Controllers\Api\School\Guru\GuruAttendanceController;
use App\Http\Controllers\Api\School\Guru\GuruTahfidzController;
use App\Http\Controllers\Api\School\Guru\GuruStudentPermissionController;
use App\Http\Controllers\Api\School\Guru\GuruMutabaahController;
use App\Http\Controllers\Api\School\Guru\GuruCurriculumController;
use App\Http\Controllers\Api\School\Guru\GuruLessonScheduleController;
use App\Http\Controllers\Api\School\Guru\GuruAcademicEventController;
use App\Http\Controllers\Api\School\Guru\GuruMaterialController;
use App\Http\Controllers\Api\School\Guru\GuruAssignmentController;
use App\Http\Controllers\Api\School\Guru\GuruSubjectController;
use App\Http\Controllers\Api\School\Guru\GuruGradeController;
use App\Http\Controllers\Api\School\Guru\GuruUksController;
use App\Http\Controllers\Api\School\Guru\GuruExtracurricularController;
use App\Http\Controllers\Api\School\Guru\GuruDashboardController;

use App\Http\Controllers\Api\School\Wali\WaliStudentController;
use App\Http\Controllers\Api\School\Wali\WaliAttendanceController;
use App\Http\Controllers\Api\School\Wali\WaliPermissionController;
use App\Http\Controllers\Api\School\Wali\WaliBoardingPermissionController;
use App\Http\Controllers\Api\School\Wali\WaliAcademicEventController;
use App\Http\Controllers\Api\School\Wali\WaliMaterialController;
use App\Http\Controllers\Api\School\Wali\WaliAssignmentController;
use App\Http\Controllers\Api\School\Wali\WaliBillController;
use App\Http\Controllers\Api\School\Wali\WaliGradeController;
use App\Http\Controllers\Api\School\Wali\WaliUksController;
use App\Http\Controllers\Api\School\Wali\WaliExtracurricularController;
use App\Http\Controllers\Api\School\Wali\WaliDashboardController;

use App\Http\Controllers\Api\School\Kiosk\KioskAuthController;
use App\Http\Controllers\Api\School\Kiosk\KioskAttendanceController;

use App\Http\Controllers\Api\School\Public\PublicPpdbController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// BCA SANDBOX
// Sandbox   : https://xxxx.ngrok.io/api/webhook/bca/inquiry
// Production: https://yourdomain.com/api/webhook/bca/inquiry
// ============================================================
Route::prefix('webhook/bca')->group(function () {
    Route::post('inquiry', [BcaWebhookController::class, 'inquiry'])
        ->name('webhook.bca.inquiry');
    Route::post('payment', [BcaWebhookController::class, 'payment'])
        ->name('webhook.bca.payment');
});

// ##########################################################

// ROUTE NEWS 2 #################################################################################

// Form reset password via API route (tanpa session/csrf)
Route::get('/reset-form', function (Request $request) {
    return view('auth.reset-password', [
        'token' => $request->token,
        'email' => $request->email,
    ]);
});

Route::post('/reset-form', function (Request $request) {
    // Validasi manual
    if (
        empty($request->token) || empty($request->email) ||
        empty($request->password) || empty($request->password_confirmation)
    ) {
        return view('auth.reset-password', [
            'token' => $request->token,
            'email' => $request->email,
            'error' => 'Semua field wajib diisi'
        ]);
    }

    if ($request->password !== $request->password_confirmation) {
        return view('auth.reset-password', [
            'token' => $request->token,
            'email' => $request->email,
            'error' => 'Konfirmasi password tidak cocok'
        ]);
    }

    if (strlen($request->password) < 6) {
        return view('auth.reset-password', [
            'token' => $request->token,
            'email' => $request->email,
            'error' => 'Password minimal 6 karakter'
        ]);
    }

    $record = DB::table('password_reset_tokens')
        ->where('email', $request->email)
        ->first();

    if (!$record) {
        return view('auth.reset-password', [
            'token' => $request->token,
            'email' => $request->email,
            'error' => 'Token tidak ditemukan'
        ]);
    }

    if (\Carbon\Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        return view('auth.reset-password', [
            'token' => $request->token,
            'email' => $request->email,
            'error' => 'Token sudah expired, minta link baru'
        ]);
    }

    if (!\Illuminate\Support\Facades\Hash::check($request->token, $record->token)) {
        return view('auth.reset-password', [
            'token' => $request->token,
            'email' => $request->email,
            'error' => 'Token tidak valid'
        ]);
    }

    $user = \App\Models\User::where('email', $request->email)->first();
    $user->forceFill([
        'password'       => \Illuminate\Support\Facades\Hash::make($request->password),
        'remember_token' => \Illuminate\Support\Str::random(60),
    ])->save();

    $user->tokens()->delete();
    DB::table('password_reset_tokens')->where('email', $request->email)->delete();

    return view('auth.reset-password-success');
});

Route::get('/test-view', function () {
    return '<h1>Hello World</h1>';
});

// BARU — PPDB publik, taruh sejajar dengan login/register di atas
Route::prefix('public/ppdb')->group(function () {
    Route::get('active-period/{companyId}', [PublicPpdbController::class, 'activePeriod']);
    Route::post('register', [PublicPpdbController::class, 'register']);
    Route::get('schools', [PublicPpdbController::class, 'searchSchools']); // BARU
});

// versi lama

// Route::prefix('auth')->group(function () {

//     Route::post('/login', [AuthController::class, 'login']);
//     Route::post('/register-organization', [AuthController::class, 'registerOrganization']);


//     // Forgot Password (tidak perlu login)
//     Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
//     Route::post('/reset-password', [AuthController::class, 'resetPassword']);

//     Route::middleware('auth:sanctum')->group(function () {
//         Route::post('/logout', [AuthController::class, 'logout']);
//         Route::get('/me', [AuthController::class, 'me']);

//         Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
//         Route::post('/change-password', [AuthController::class, 'changePassword']);

//         // Profile Management (Universal untuk semua role)
//         Route::get('/profile', [AuthController::class, 'show']);
//         Route::post('/profile', [AuthController::class, 'update']);
//         Route::post('/upload-face', [AuthController::class, 'uploadFaceEmbedding']);
//     });
// });



////versi baru 

Route::group([], function () {

    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register-organization', [AuthController::class, 'registerOrganization']);

    // Forgot Password (tidak perlu login)
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

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
// ============================================================
// SUBSCRIPTION — butuh auth:sanctum
// ============================================================
Route::middleware('auth:sanctum')
    ->prefix('v1/subscriptions')
    ->group(function () {

    Route::get('plans', [SubscriptionController::class, 'plans'])
            ->name('subscriptions.plans');

    Route::get('status', [SubscriptionController::class, 'status'])
            ->name('subscriptions.status');

    Route::post('trial', [SubscriptionController::class, 'startTrial'])
            ->name('subscriptions.trial');

    Route::post('select', [SubscriptionController::class, 'selectPlan'])
            ->name('subscriptions.select');

    Route::post('check-va', [SubscriptionController::class, 'checkVa'])
            ->name('subscriptions.check-va');

    Route::get('invoices', [SubscriptionController::class, 'invoices'])
            ->name('subscriptions.invoices');

    Route::get('invoices/{id}', [SubscriptionController::class, 'invoiceDetail'])
        ->name('subscriptions.invoice-detail')
        ->whereNumber('id');
    });

// ============================================================
// Webhook Midtrans — TANPA middleware auth:sanctum
// ============================================================
Route::post('v1/midtrans/callback', [MidtransCallbackController::class, 'handle'])
    ->name('midtrans.callback');


// =======================
// 💬 CHAT (Universal - semua context)
// =======================
Route::prefix('chat')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/users',                        [ChatController::class, 'listUsers']);
        Route::get('/conversations',                [ChatController::class, 'conversations']);
        Route::post('/conversations',               [ChatController::class, 'openConversation']);
        Route::get('/conversations/{id}/messages',  [ChatController::class, 'messages'])->whereNumber('id');
        Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage'])->whereNumber('id');
        Route::post('/conversations/{id}/read',     [ChatController::class, 'markAsRead'])->whereNumber('id');
        Route::put('/messages/{id}',                [ChatController::class, 'editMessage'])->whereNumber('id');
        Route::delete('/messages/{id}',             [ChatController::class, 'deleteMessage'])->whereNumber('id');
    });

// =======================
// 🎫 SUPPORT TICKET (Universal - semua context)
// =======================
Route::prefix('support-tickets')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [SupportTicketController::class, 'index']);
        Route::post('/', [SupportTicketController::class, 'store']);
        Route::get('/{id}', [SupportTicketController::class, 'show'])->whereNumber('id');
        Route::post('/{id}/reply', [SupportTicketController::class, 'reply'])->whereNumber('id');
    });

// =======================
// ❓ HELP ARTICLE / FAQ (Universal - semua context)
// =======================
Route::prefix('help-articles')
    ->middleware('auth:sanctum')
    ->group(function () {
    Route::get('/categories', [HelpArticleController::class, 'categories']);
        Route::get('/', [HelpArticleController::class, 'index']);
        Route::get('/{id}', [HelpArticleController::class, 'show'])->whereNumber('id');
    });

// =======================
// 📄 APP POLICY — Privacy Policy, ToS, dll (Universal - butuh login)
// =======================
Route::prefix('policies')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/', [AppPolicyController::class, 'index']);
        Route::get('/{type}', [AppPolicyController::class, 'show']);
    });


// ═══════════════════════════════════════════════════════════
// SUPERADMIN — kelola aktif/nonaktif semua company/sekolah
// ═══════════════════════════════════════════════════════════
Route::prefix('system')
    ->middleware(['auth:sanctum', 'context:system,superadmin'])
    ->group(function () {
        Route::get('companies', [CompanyController::class, 'index']);
        Route::get('companies/{company}', [CompanyController::class, 'show']);
        Route::patch('companies/{company}/deactivate', [CompanyController::class, 'deactivate']);
        Route::patch('companies/{company}/reactivate', [CompanyController::class, 'reactivate']);
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

        Route::prefix('employee/stats')->group(function () {
                Route::get('/summary', [EmployeeAttendanceController::class, 'summary']);
            });

            Route::prefix('employee/permissions')->group(function () {
                Route::get('/', [EmployeePermissionController::class, 'index']);
                Route::post('/', [EmployeePermissionController::class, 'store']);
                Route::get('/{id}', [EmployeePermissionController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/cancel', [EmployeePermissionController::class, 'cancel'])->whereNumber('id');
            });

        Route::prefix('employee/notes')->group(function () {
            Route::get('/summary', [EmployeeNotesController::class, 'summary']);
                Route::get('/',        [EmployeeNotesController::class, 'index']);
                Route::get('/{id}',    [EmployeeNotesController::class, 'show'])->whereNumber('id');
                Route::patch('/{id}/read', [EmployeeNotesController::class, 'markRead'])->whereNumber('id');
            });

        Route::prefix('employee/daily-reports')->group(function () {
            Route::get('/today',  [EmployeeDailyReportController::class, 'today']);
            Route::get('/summary', [EmployeeDailyReportController::class, 'summary']);
                Route::get('/export',  [EmployeeDailyReportController::class, 'export']);
                Route::get('/',       [EmployeeDailyReportController::class, 'index']);
            Route::post('/',      [EmployeeDailyReportController::class, 'store']);
                Route::get('/{id}',   [EmployeeDailyReportController::class, 'show'])->whereNumber('id');
            Route::post('/{id}',  [EmployeeDailyReportController::class, 'update'])->whereNumber('id');
            });

        Route::prefix('employee/monthly-reports')->group(function () {
            Route::get('/summary', [EmployeeMonthlyReportController::class, 'summary']);
                Route::get('/export',  [EmployeeMonthlyReportController::class, 'export']);
                Route::get('/',        [EmployeeMonthlyReportController::class, 'index']);
                Route::post('/',       [EmployeeMonthlyReportController::class, 'store']);
                Route::get('/{id}',    [EmployeeMonthlyReportController::class, 'show'])->whereNumber('id');
                Route::post('/{id}',   [EmployeeMonthlyReportController::class, 'update'])->whereNumber('id');
                Route::patch('/{id}/submit', [EmployeeMonthlyReportController::class, 'submit'])->whereNumber('id');
                Route::delete('/{id}', [EmployeeMonthlyReportController::class, 'destroy'])->whereNumber('id');
            });

        Route::prefix('employee/performance-scores')->group(function () {
            Route::get('/leaderboard', [EmployeePerformanceScoreController::class, 'leaderboard']);
                Route::get('/',            [EmployeePerformanceScoreController::class, 'index']);
                Route::get('/{id}',        [EmployeePerformanceScoreController::class, 'show'])->whereNumber('id');
            });

        Route::prefix('employee/shifts')->group(function () {
            Route::get('/today', [EmployeeShiftController::class, 'today']);
            Route::get('/date/{date}', [EmployeeShiftController::class, 'byDate']);
                Route::get('/schedule', [EmployeeShiftController::class, 'schedule']);
            });

            Route::prefix('employee/schedules')->group(function () {
                Route::get('/',                  [EmployeeSchedulesController::class, 'index']);
                Route::get('/invitations',       [EmployeeSchedulesController::class, 'invitations']);
                Route::get('/{id}',              [EmployeeSchedulesController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/respond',     [EmployeeSchedulesController::class, 'respond'])->whereNumber('id');
            });

        Route::prefix('employee/loans')->group(function () {
            Route::get('/active', [EmployeeLoanController::class, 'active']);
            Route::get('/', [EmployeeLoanController::class, 'index']);
            Route::get('/{id}', [EmployeeLoanController::class, 'show'])->whereNumber('id');
            Route::post('/', [EmployeeLoanController::class, 'store']);
            Route::put('/{id}/cancel', [EmployeeLoanController::class, 'cancel'])->whereNumber('id');
                Route::get('/{id}/payments', [EmployeeLoanController::class, 'paymentHistory'])->whereNumber('id');
            });

            Route::prefix('employee/payrolls')->group(function () {
                Route::get('/', [EmployeePayrollController::class, 'index']);
                Route::get('/{id}', [EmployeePayrollController::class, 'show'])->whereNumber('id');
            });

        Route::prefix('employee/leaves')->group(function () {
                Route::get('/', [EmployeeLeaveController::class, 'index']);
                Route::post('/', [EmployeeLeaveController::class, 'store']);
                Route::get('/{id}', [EmployeeLeaveController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/cancel', [EmployeeLeaveController::class, 'cancel'])->whereNumber('id');
            });

        Route::prefix('employee/overtimes')->group(function () {
                Route::get('/', [EmployeeOvertimeRequestController::class, 'index']);
                Route::post('/', [EmployeeOvertimeRequestController::class, 'store']);
                Route::get('/{id}', [EmployeeOvertimeRequestController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/cancel', [EmployeeOvertimeRequestController::class, 'cancel'])->whereNumber('id');
            });

        Route::prefix('employee/holidays')->group(function () {
                Route::get('/', [EmployeeHolidayController::class, 'index']);
                Route::get('/{id}', [EmployeeHolidayController::class, 'show'])->whereNumber('id');
            });
        });

        // =======================
        // 🧑‍💼 HR / ADMIN COMPANY
        // =======================
        Route::middleware('context:company,hr')->group(function () {

            Route::get('/hr/summary-stats', [HrCompanyDashboardController::class, 'summary']);

        Route::prefix('hr/analytics')->group(function () {
            Route::get('/monthly', [HrCompanyAnalyticsController::class, 'monthly']);
            Route::get('/employee/{userId}', [HrCompanyAnalyticsController::class, 'employeeDetail']);
                Route::get('/attendance-recap', [HrCompanyAnalyticsController::class, 'attendanceRecap']);
            });

        Route::prefix('hr/settings')->group(function () {
            Route::get('/company', [HrCompanySettingController::class, 'show']);
            Route::put('/company', [HrCompanySettingController::class, 'update']);
            Route::post('/company/logo', [HrCompanySettingController::class, 'uploadLogo']);
            Route::get('/company/employees', [HrCompanySettingController::class, 'employees']);
                Route::get('/company/departments', [HrCompanySettingController::class, 'departments']);
            });

        Route::prefix('hr/attendances')->group(function () {
                Route::get('/settings', [HrCompanyAttendanceController::class, 'settings']);
            Route::post('/settings', [HrCompanyAttendanceController::class, 'updateSettings']);
                Route::get('/employees', [HrCompanyAttendanceController::class, 'employeesToday']);
                Route::post('/employees/mark', [HrCompanyAttendanceController::class, 'markEmployeeAttendance']);
            Route::get('/employees/today', [HrCompanyAttendanceController::class, 'todaySummary']);
                Route::get('/employees/export',                 [HrCompanyAttendanceController::class, 'exportAllPdf']);
                Route::get('/employees/{id}/history', [HrCompanyAttendanceController::class, 'employeeHistory'])->whereNumber('id');
            Route::get('/employees/{id}/history/export',    [HrCompanyAttendanceController::class, 'exportEmployeePdf'])->whereNumber('id');
            });

            Route::prefix('hr/employees')->group(function () {
                Route::get('/export',  [HrCompanyEmployeeController::class, 'export']);
                Route::get('/', [HrCompanyEmployeeController::class, 'index']);
                Route::post('/', [HrCompanyEmployeeController::class, 'store']);
                Route::get('/{id}', [HrCompanyEmployeeController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [HrCompanyEmployeeController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [HrCompanyEmployeeController::class, 'destroy'])->whereNumber('id');
            });

            Route::prefix('hr/permissions')->group(function () {
                Route::get('/export',         [HrCompanyPermissionController::class, 'export']);
                Route::get('/', [HrCompanyPermissionController::class, 'index']);
                Route::get('/{id}', [HrCompanyPermissionController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/approve', [HrCompanyPermissionController::class, 'approve'])->whereNumber('id');
                Route::post('/{id}/reject', [HrCompanyPermissionController::class, 'reject'])->whereNumber('id');
            });

            Route::prefix('hr/shifts')->group(function () {
                Route::get('/export',           [HrCompanyShiftController::class, 'export']);
                Route::get('/', [HrCompanyShiftController::class, 'index']);
                Route::post('/', [HrCompanyShiftController::class, 'store']);
                Route::get('/{id}', [HrCompanyShiftController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [HrCompanyShiftController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [HrCompanyShiftController::class, 'destroy'])->whereNumber('id');
                Route::post('/{id}/set-default', [HrCompanyShiftController::class, 'setDefault'])->whereNumber('id');
            });

        Route::prefix('hr/shift-groups')->group(function () {
                Route::get('/', [HrCompanyShiftGroupController::class, 'index']);
                Route::post('/', [HrCompanyShiftGroupController::class, 'store']);
                Route::get('/{id}', [HrCompanyShiftGroupController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [HrCompanyShiftGroupController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [HrCompanyShiftGroupController::class, 'destroy'])->whereNumber('id');

            Route::get('/{id}/users', [HrCompanyShiftGroupUserController::class, 'index'])->whereNumber('id');
                Route::post('/{id}/users/attach', [HrCompanyShiftGroupUserController::class, 'attach'])->whereNumber('id');
                Route::post('/{id}/users/detach', [HrCompanyShiftGroupUserController::class, 'detach'])->whereNumber('id');

            Route::get('/{id}/assignments', [HrCompanyShiftGroupAssignmentController::class, 'index'])->whereNumber('id');
                Route::post('/{id}/assignments', [HrCompanyShiftGroupAssignmentController::class, 'store'])->whereNumber('id');

            Route::put('/shift-group-assignments/{id}', [HrCompanyShiftGroupAssignmentController::class, 'update'])->whereNumber('id');
                Route::delete('/shift-group-assignments/{id}', [HrCompanyShiftGroupAssignmentController::class, 'destroy'])->whereNumber('id');

            Route::get('/users/{userId}/shift-overrides', [HrCompanyUserShiftOverrideController::class, 'index'])->whereNumber('userId');
                Route::post('/users/{userId}/shift-overrides', [HrCompanyUserShiftOverrideController::class, 'store'])->whereNumber('userId');
                Route::delete('/user-shift-overrides/{id}', [HrCompanyUserShiftOverrideController::class, 'destroy'])->whereNumber('id');
                Route::patch('/user-shift-overrides/{id}/cancel', [HrCompanyUserShiftOverrideController::class, 'cancel'])->whereNumber('id');
            });

        Route::prefix('hr/loans')->group(function () {
            Route::get('/summary', [HrCompanyLoanController::class, 'summary']);
            Route::get('/export',                    [HrCompanyLoanController::class, 'export']);
            Route::get('/', [HrCompanyLoanController::class, 'index']);
            Route::get('/{id}', [HrCompanyLoanController::class, 'show'])->whereNumber('id');
            Route::post('/', [HrCompanyLoanController::class, 'store']);
            Route::put('/{id}/approve', [HrCompanyLoanController::class, 'approve'])->whereNumber('id');
            Route::put('/{id}/reject', [HrCompanyLoanController::class, 'reject'])->whereNumber('id');
            Route::put('/{id}/cancel', [HrCompanyLoanController::class, 'cancel'])->whereNumber('id');
            Route::get('/{id}/payments', [HrCompanyLoanController::class, 'paymentHistory'])->whereNumber('id');
            Route::post('/{id}/payments', [HrCompanyLoanController::class, 'recordPayment'])->whereNumber('id');
                Route::delete('/{id}/payments/{paymentId}', [HrCompanyLoanController::class, 'deletePayment'])->whereNumber('id');
            });

        Route::prefix('hr/payrolls')->name('payrolls.')->group(function () {
                Route::post('generate', [HrCompanyPayrollController::class, 'generate'])->name('generate');
                Route::get('summary',   [HrCompanyPayrollController::class, 'summary'])->name('summary');

            Route::get('/',       [HrCompanyPayrollController::class, 'index'])->name('index');
                Route::post('/',      [HrCompanyPayrollController::class, 'store'])->name('store');
                Route::get('{id}',    [HrCompanyPayrollController::class, 'show'])->name('show')->whereNumber('id');
                Route::put('{id}',    [HrCompanyPayrollController::class, 'update'])->name('update')->whereNumber('id');
                Route::delete('{id}', [HrCompanyPayrollController::class, 'destroy'])->name('destroy')->whereNumber('id');

            Route::patch('{id}/approve',   [HrCompanyPayrollController::class, 'approve'])->name('approve')->whereNumber('id');
                Route::patch('{id}/mark-paid', [HrCompanyPayrollController::class, 'markAsPaid'])->name('mark-paid')->whereNumber('id');

            Route::get('{id}/slip', [HrCompanyPayrollController::class, 'slip'])->name('slip')->whereNumber('id');

            Route::prefix('{payrollId}/components')->name('components.')->whereNumber('payrollId')->group(function () {
                    Route::get('/',    [HrCompanyPayrollComponentController::class, 'index'])->name('index');
                    Route::post('/',   [HrCompanyPayrollComponentController::class, 'store'])->name('store');
                Route::post('bulk', [HrCompanyPayrollComponentController::class, 'storeBulk'])->name('bulk');
                    Route::put('{componentId}',    [HrCompanyPayrollComponentController::class, 'update'])->name('update')->whereNumber('componentId');
                    Route::delete('{componentId}', [HrCompanyPayrollComponentController::class, 'destroy'])->name('destroy')->whereNumber('componentId');
                });
            });

        Route::prefix('hr/holidays')->group(function () {
                Route::get('/export',  [HrCompanyHolidayController::class, 'export']);
                Route::get('/', [HrCompanyHolidayController::class, 'index']);
                Route::post('/', [HrCompanyHolidayController::class, 'store']);
                Route::get('/{id}', [HrCompanyHolidayController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [HrCompanyHolidayController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [HrCompanyHolidayController::class, 'destroy'])->whereNumber('id');
            });

        Route::prefix('hr/leaves')->group(function () {
                Route::get('/export',        [HrCompanyLeaveController::class, 'export']);
                Route::get('/', [HrCompanyLeaveController::class, 'index']);
                Route::get('/{id}', [HrCompanyLeaveController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/approve', [HrCompanyLeaveController::class, 'approve'])->whereNumber('id');
                Route::post('/{id}/reject', [HrCompanyLeaveController::class, 'reject'])->whereNumber('id');
            });

        Route::prefix('hr/overtimes')->group(function () {
                Route::get('/export',          [HrCompanyOvertimeRequestController::class, 'export']);
                Route::get('/', [HrCompanyOvertimeRequestController::class, 'index']);
                Route::get('/{id}', [HrCompanyOvertimeRequestController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/approve', [HrCompanyOvertimeRequestController::class, 'approve'])->whereNumber('id');
                Route::post('/{id}/reject', [HrCompanyOvertimeRequestController::class, 'reject'])->whereNumber('id');
            });

        Route::prefix('hr/schedules')->group(function () {
            Route::get('/export',                        [HrCompanyScheduleController::class, 'export']);
                Route::get('/',             [HrCompanyScheduleController::class, 'index']);
                Route::post('/',            [HrCompanyScheduleController::class, 'store']);
                Route::get('/{id}',        [HrCompanyScheduleController::class, 'show'])->whereNumber('id');
                Route::put('/{id}',        [HrCompanyScheduleController::class, 'update'])->whereNumber('id');
            Route::delete('/{id}',     [HrCompanyScheduleController::class, 'destroy'])->whereNumber('id');
                Route::get('/{id}/participants',              [HrCompanyScheduleController::class, 'getParticipants'])->whereNumber('id');
                Route::post('/{id}/participants',             [HrCompanyScheduleController::class, 'addParticipants'])->whereNumber('id');
                Route::delete('/{id}/participants/{userId}',  [HrCompanyScheduleController::class, 'removeParticipant'])->whereNumber('id');
            });

        Route::prefix('hr/notes')->group(function () {
            Route::get('/summary',   [HrCompanyNotesController::class, 'summary']);
                Route::get('/export',  [HrCompanyNotesController::class, 'export']);
                Route::get('/',          [HrCompanyNotesController::class, 'index']);
                Route::post('/',         [HrCompanyNotesController::class, 'store']);
                Route::get('/{id}',      [HrCompanyNotesController::class, 'show'])->whereNumber('id');
                Route::post('/{id}',     [HrCompanyNotesController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}',   [HrCompanyNotesController::class, 'destroy'])->whereNumber('id');
            });

            Route::prefix('hr/daily-reports')->group(function () {
            Route::get('/summary',   [HrCompanyDailyReportController::class, 'summary']);
                Route::get('/today',     [HrCompanyDailyReportController::class, 'today']);
            Route::get('/employees',            [HrCompanyDailyReportController::class, 'employees']);
            Route::get('/export',               [HrCompanyDailyReportController::class, 'export']);
                Route::get('/',          [HrCompanyDailyReportController::class, 'index']);
                Route::get('/{id}',      [HrCompanyDailyReportController::class, 'show'])->whereNumber('id');
            });

            Route::prefix('hr/monthly-reports')->group(function () {
            Route::get('/summary',        [HrCompanyMonthlyReportController::class, 'summary']);
                Route::get('/export',         [HrCompanyMonthlyReportController::class, 'export']);
                Route::get('/',               [HrCompanyMonthlyReportController::class, 'index']);
                Route::get('/{id}',           [HrCompanyMonthlyReportController::class, 'show'])->whereNumber('id');
                Route::patch('/{id}/approve', [HrCompanyMonthlyReportController::class, 'approve'])->whereNumber('id');
                Route::patch('/{id}/reject',  [HrCompanyMonthlyReportController::class, 'reject'])->whereNumber('id');
            });

            Route::prefix('hr/performance-scores')->group(function () {
            Route::get('/leaderboard',  [HrCompanyPerformanceScoreController::class, 'leaderboard']);
                Route::get('/export',      [HrCompanyPerformanceScoreController::class, 'export']);
                Route::get('/',             [HrCompanyPerformanceScoreController::class, 'index']);
                Route::post('/generate',    [HrCompanyPerformanceScoreController::class, 'generate']);
                Route::get('/{id}',         [HrCompanyPerformanceScoreController::class, 'show'])->whereNumber('id');
            });
        });
    });


// KODE 3
Route::prefix('pesantren')
    ->middleware(['auth:sanctum', 'context:pesantren'])
    ->group(function () {

    Route::middleware('context:pesantren,ustadz')->group(function () {

        Route::get('/dashboard', [PesantrenDashboardController::class, 'ustadz']);

        Route::prefix('attendances')->group(function () {
            Route::get('/settings',  [PesantrenUstadzAttendanceController::class, 'settings']);
            Route::post('/settings', [PesantrenUstadzAttendanceController::class, 'updateSettings']);

                Route::post('/check-in',    [PesantrenUstadzAttendanceController::class, 'checkIn']);
                Route::post('/check-out',   [PesantrenUstadzAttendanceController::class, 'checkOut']);
                Route::get('/is-checkin',   [PesantrenUstadzAttendanceController::class, 'isCheckedIn']);
                Route::get('/history',      [PesantrenUstadzAttendanceController::class, 'history']);
                Route::get('/summary',      [PesantrenUstadzAttendanceController::class, 'summary']);
                Route::get('/export',       [PesantrenUstadzAttendanceController::class, 'exportAllPdf']);

            Route::get('/santri',                      [PesantrenUstadzAttendanceController::class, 'santriToday']);
                Route::post('/santri/mark',                [PesantrenUstadzAttendanceController::class, 'markSantriAttendance']);
                Route::get('/santri/{id}/history',         [PesantrenUstadzAttendanceController::class, 'santriHistory'])->whereNumber('id');
                Route::get('/santri/{id}/history/export',  [PesantrenUstadzAttendanceController::class, 'exportSantriPdf'])->whereNumber('id');
            });

        Route::prefix('analytics')->group(function () {
                Route::get('/monthly',           [PesantrenAnalyticsController::class, 'monthly']);
                Route::get('/santri/{santriId}', [PesantrenAnalyticsController::class, 'santriDetail'])->whereNumber('santriId');
                Route::get('/attendance-recap',  [PesantrenAnalyticsController::class, 'attendanceRecap']);
            });

        Route::prefix('settings')->group(function () {
                Route::get('/pesantren',        [PesantrenSettingController::class, 'show']);
                Route::put('/pesantren',        [PesantrenSettingController::class, 'update']);
                Route::post('/pesantren/logo',  [PesantrenSettingController::class, 'uploadLogo']);
                Route::get('/pesantren/santri', [PesantrenSettingController::class, 'santriList']);
                Route::get('/pesantren/kamar',  [PesantrenSettingController::class, 'kamarList']);
            });

        Route::prefix('santri')->group(function () {
                Route::get('/export',  [PesantrenSantriController::class, 'export']);
                Route::get('/',        [PesantrenSantriController::class, 'index']);
                Route::post('/',       [PesantrenSantriController::class, 'store']);
                Route::get('/{id}',    [PesantrenSantriController::class, 'show'])->whereNumber('id');
                Route::put('/{id}',    [PesantrenSantriController::class, 'update'])->whereNumber('id');
            Route::delete('/{id}', [PesantrenSantriController::class, 'destroy'])->whereNumber('id');
                Route::get('/{id}/attendance',  [PesantrenSantriController::class, 'attendance'])->whereNumber('id');
                Route::get('/{id}/permissions', [PesantrenSantriController::class, 'permissions'])->whereNumber('id');
            });

        Route::prefix('permissions/santri')->group(function () {
                Route::get('/export',        [PesantrenUstadzSantriPermissionController::class, 'export']);
                Route::get('/',              [PesantrenUstadzSantriPermissionController::class, 'index']);
                Route::get('/{id}',          [PesantrenUstadzSantriPermissionController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/approve', [PesantrenUstadzSantriPermissionController::class, 'approve'])->whereNumber('id');
                Route::post('/{id}/reject',  [PesantrenUstadzSantriPermissionController::class, 'reject'])->whereNumber('id');
            });

        Route::prefix('schedules')->group(function () {
                Route::get('/export',  [PesantrenSchedulesController::class, 'export']);
            Route::get('/today',   [PesantrenSchedulesController::class, 'today']);
                Route::get('/',        [PesantrenSchedulesController::class, 'index']);
                Route::post('/',       [PesantrenSchedulesController::class, 'store']);
                Route::get('/{id}',    [PesantrenSchedulesController::class, 'show'])->whereNumber('id');
                Route::put('/{id}',    [PesantrenSchedulesController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [PesantrenSchedulesController::class, 'destroy'])->whereNumber('id');
            Route::post('/{id}/status', [PesantrenSchedulesController::class, 'updateStatus'])->whereNumber('id');
                Route::get('/{id}/participants',                 [PesantrenSchedulesController::class, 'getParticipants'])->whereNumber('id');
                Route::post('/{id}/participants',                [PesantrenSchedulesController::class, 'addParticipants'])->whereNumber('id');
                Route::delete('/{id}/participants/{santriId}',   [PesantrenSchedulesController::class, 'removeParticipant'])->whereNumber('id')->whereNumber('santriId');
            });

        Route::prefix('notes/santri')->group(function () {
            Route::get('/summary', [PesantrenNotesController::class, 'summary']);
                Route::get('/export',  [PesantrenNotesController::class, 'export']);
                Route::get('/',        [PesantrenNotesController::class, 'index']);
                Route::post('/',       [PesantrenNotesController::class, 'store']);
                Route::get('/{id}',    [PesantrenNotesController::class, 'show'])->whereNumber('id');
                Route::post('/{id}',   [PesantrenNotesController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [PesantrenNotesController::class, 'destroy'])->whereNumber('id');
            });

        Route::prefix('daily-reports')->group(function () {
            Route::get('/summary', [PesantrenDailyReportController::class, 'summary']);
            Route::get('/today',   [PesantrenDailyReportController::class, 'today']);
                Route::get('/export',  [PesantrenDailyReportController::class, 'export']);
            Route::get('/santri',  [PesantrenDailyReportController::class, 'santriReports']);
                Route::get('/',        [PesantrenDailyReportController::class, 'index']);
                Route::get('/{id}',    [PesantrenDailyReportController::class, 'show'])->whereNumber('id');
            });

        Route::prefix('monthly-reports')->group(function () {
            Route::get('/summary',        [PesantrenMonthlyReportController::class, 'summary']);
                Route::get('/export',         [PesantrenMonthlyReportController::class, 'export']);
            Route::get('/santri',         [PesantrenMonthlyReportController::class, 'santriReports']);
                Route::get('/',               [PesantrenMonthlyReportController::class, 'index']);
                Route::get('/{id}',           [PesantrenMonthlyReportController::class, 'show'])->whereNumber('id');
                Route::patch('/{id}/approve', [PesantrenMonthlyReportController::class, 'approve'])->whereNumber('id');
                Route::patch('/{id}/reject',  [PesantrenMonthlyReportController::class, 'reject'])->whereNumber('id');
            });

        Route::prefix('performance')->group(function () {
            Route::get('/leaderboard', [PesantrenPerformanceController::class, 'leaderboard']);
                Route::get('/export',      [PesantrenPerformanceController::class, 'export']);
                Route::get('/',            [PesantrenPerformanceController::class, 'index']);
                Route::post('/generate',   [PesantrenPerformanceController::class, 'generate']);
                Route::get('/{id}',        [PesantrenPerformanceController::class, 'show'])->whereNumber('id');
            });

        Route::prefix('holidays')->group(function () {
                Route::get('/export',  [PesantrenHolidayController::class, 'export']);
                Route::get('/',        [PesantrenHolidayController::class, 'index']);
                Route::post('/',       [PesantrenHolidayController::class, 'store']);
                Route::get('/{id}',    [PesantrenHolidayController::class, 'show'])->whereNumber('id');
                Route::put('/{id}',    [PesantrenHolidayController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [PesantrenHolidayController::class, 'destroy'])->whereNumber('id');
            });

        Route::prefix('mutabaah')->group(function () {
                Route::get('/rekap',  [PesantrenMutabaahController::class, 'rekap']);
                Route::get('/export', [PesantrenMutabaahController::class, 'export']);
            Route::get('/today',  [PesantrenMutabaahController::class, 'today']);
                Route::get('/santri/{santriId}',          [PesantrenMutabaahController::class, 'santriKartu'])->whereNumber('santriId');
            Route::get('/santri/{santriId}/progress', [PesantrenMutabaahController::class, 'santriProgress'])->whereNumber('santriId');
                Route::get('/',       [PesantrenMutabaahController::class, 'index']);
                Route::post('/',      [PesantrenMutabaahController::class, 'store']);
                Route::get('/{id}',   [PesantrenMutabaahController::class, 'show'])->whereNumber('id');
                Route::put('/{id}',   [PesantrenMutabaahController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [PesantrenMutabaahController::class, 'destroy'])->whereNumber('id');
                Route::post('/{id}/sign', [PesantrenMutabaahController::class, 'sign'])->whereNumber('id');
            });

        Route::prefix('prayers')->group(function () {
                Route::get('/today',  [PesantrenPrayerController::class, 'today']);
                Route::get('/next',    [PesantrenPrayerController::class, 'next']);
                Route::get('/methods', [PesantrenPrayerController::class, 'methods']);
                Route::get('/monthly', [PesantrenPrayerController::class, 'monthly']);
                Route::get('/{date}', [PesantrenPrayerController::class, 'byDate'])
                    ->where('date', '^\d{4}-\d{2}-\d{2}$');
            });

        Route::prefix('quran')->group(function () {
            Route::get('/surah',                      [Pesantrenqurancontroller::class, 'surahList']);
            Route::get('/surah/{number}',             [Pesantrenqurancontroller::class, 'surahDetail']);
            Route::get('/ayat/{ref}',                 [Pesantrenqurancontroller::class, 'ayat']);
            Route::get('/halaman/{page}',             [Pesantrenqurancontroller::class, 'halaman']);
            Route::get('/search',                     [Pesantrenqurancontroller::class, 'search']);
            Route::get('/mutabaah/{id}',              [Pesantrenqurancontroller::class, 'mutabaahDetail']);
            Route::get('/santri/{santriId}/kartu',    [Pesantrenqurancontroller::class, 'kartuSantri']);
                Route::get('/santri/{santriId}/halaman-dibaca', [Pesantrenqurancontroller::class, 'halamanDibaca']);
            });
    });


    Route::middleware('context:pesantren,santri')->group(function () {

        Route::get('/santri/dashboard', [Santridashboardcontroller::class, 'index']);

        Route::prefix('santri/attendances')->group(function () {
                Route::post('/check-in',      [SantriAttendanceController::class, 'checkIn']);
                Route::post('/check-out',     [SantriAttendanceController::class, 'checkOut']);
                Route::get('/is-checkin',     [SantriAttendanceController::class, 'isCheckedIn']);
                Route::get('/history',        [SantriAttendanceController::class, 'history']);
                Route::get('/summary',        [SantriAttendanceController::class, 'summary']);
                Route::post('/register-face', [SantriAttendanceController::class, 'registerFace']);
            });

        Route::prefix('santri/permissions')->group(function () {
                Route::get('/',             [SantriPermissionController::class, 'index']);
                Route::post('/',            [SantriPermissionController::class, 'store']);
                Route::get('/{id}',         [SantriPermissionController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/cancel', [SantriPermissionController::class, 'cancel'])->whereNumber('id');
            });

        Route::prefix('santri/notes')->group(function () {
            Route::get('/summary',     [SantriNotesController::class, 'summary']);
                Route::get('/',            [SantriNotesController::class, 'index']);
                Route::get('/{id}',        [SantriNotesController::class, 'show'])->whereNumber('id');
                Route::patch('/{id}/read', [SantriNotesController::class, 'markRead'])->whereNumber('id');
            });

        Route::prefix('santri/daily-reports')->group(function () {
            Route::get('/today',   [SantriDailyReportController::class, 'today']);
            Route::get('/summary', [SantriDailyReportController::class, 'summary']);
                Route::get('/export',  [SantriDailyReportController::class, 'export']);
                Route::get('/',        [SantriDailyReportController::class, 'index']);
            Route::post('/',       [SantriDailyReportController::class, 'store']);
                Route::get('/{id}',    [SantriDailyReportController::class, 'show'])->whereNumber('id');
            Route::post('/{id}',   [SantriDailyReportController::class, 'update'])->whereNumber('id');
            });

        Route::prefix('santri/monthly-reports')->group(function () {
            Route::get('/summary',       [SantriMonthlyReportController::class, 'summary']);
                Route::get('/export',        [SantriMonthlyReportController::class, 'export']);
                Route::get('/',              [SantriMonthlyReportController::class, 'index']);
                Route::post('/',             [SantriMonthlyReportController::class, 'store']);
                Route::get('/{id}',          [SantriMonthlyReportController::class, 'show'])->whereNumber('id');
                Route::post('/{id}',         [SantriMonthlyReportController::class, 'update'])->whereNumber('id');
                Route::patch('/{id}/submit', [SantriMonthlyReportController::class, 'submit'])->whereNumber('id');
                Route::delete('/{id}',       [SantriMonthlyReportController::class, 'destroy'])->whereNumber('id');
            });

        Route::prefix('santri/performance')->group(function () {
            Route::get('/leaderboard', [Santriperformancecontroller::class, 'leaderboard']);
                Route::get('/',            [Santriperformancecontroller::class, 'index']);
                Route::get('/{id}',        [Santriperformancecontroller::class, 'show'])->whereNumber('id');
            });

        Route::prefix('santri/schedules')->group(function () {
            Route::get('/today',         [SantriSchedulesController::class, 'today']);
            Route::get('/invitations',   [SantriSchedulesController::class, 'invitations']);
                Route::get('/',              [SantriSchedulesController::class, 'index']);
                Route::get('/{id}',          [SantriSchedulesController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/respond', [SantriSchedulesController::class, 'respond'])->whereNumber('id');
            });

        Route::prefix('santri/holidays')->group(function () {
                Route::get('/',     [Santriholidaycontroller::class, 'index']);
                Route::get('/{id}', [Santriholidaycontroller::class, 'show'])->whereNumber('id');
            });

        Route::prefix('santri/mutabaah')->group(function () {
            Route::get('/progress', [SantriMutabaahController::class, 'progress']);
            Route::get('/export',   [SantriMutabaahController::class, 'export']);
            Route::get('/',         [SantriMutabaahController::class, 'index']);
                Route::get('/{id}',     [SantriMutabaahController::class, 'show'])->whereNumber('id');
            });

        Route::prefix('prayers')->group(function () {
                Route::get('/today',  [SantriPrayerController::class, 'today']);
                Route::get('/next',    [SantriPrayerController::class, 'next']);
                Route::get('/monthly', [SantriPrayerController::class, 'monthly']);
                Route::get('/{date}', [SantriPrayerController::class, 'byDate'])
                    ->where('date', '^\d{4}-\d{2}-\d{2}$');
            });

        Route::prefix('quran')->group(function () {
            Route::get('/surah',          [Santriqurancontroller::class, 'surahList']);
            Route::get('/surah/{number}', [Santriqurancontroller::class, 'surahDetail']);
            Route::get('/ayat/{ref}',     [Santriqurancontroller::class, 'ayat']);
            Route::get('/halaman/{page}', [Santriqurancontroller::class, 'halaman']);
            Route::get('/progress',       [Santriqurancontroller::class, 'progress']);
                Route::get('/sesi/{id}',      [Santriqurancontroller::class, 'sesiDetail']);
            });
    });

    });


Route::prefix('school')
    ->middleware(['auth:sanctum', 'context:school'])
    ->group(function () {

    // ═══════════════════════════════════════════════════════════
    // ADMIN SEKOLAH — kelola master data
    // ═══════════════════════════════════════════════════════════
    Route::middleware('context:school,admin')->prefix('admin')->group(function () {

        Route::get('dashboard/summary', [AdminDashboardController::class, 'summary']);

        // Kelola akun guru & wali
        Route::apiResource('staff', StaffController::class)->except(['destroy']);
        Route::delete('staff/{staff}', [StaffController::class, 'destroy']);
        Route::post('staff/{staff}/reset-password', [StaffController::class, 'resetPassword']);

        Route::apiResource('classes', ClassRoomController::class);

        // PENTING: route statis 'students/boarding-unassigned' HARUS didaftarkan
        // SEBELUM apiResource('students', ...) — kalau tidak, akan ketutup oleh
        // GET students/{student} (show) karena Laravel mencocokkan urutan pendaftaran.
        Route::get('students/boarding-unassigned', [RoomAssignmentController::class, 'unassignedBoardingStudents']);

        Route::apiResource('students', StudentController::class);
        Route::post('students/{student}/guardians', [StudentController::class, 'attachGuardian']);
        Route::delete('students/{student}/guardians/{user}', [StudentController::class, 'detachGuardian']);
        Route::post('students/{student}/move-room', [RoomAssignmentController::class, 'moveRoom']);

        Route::post('classes/{class}/teachers', [TeacherAssignmentController::class, 'attach']);
        Route::delete('classes/{class}/teachers/{user}', [TeacherAssignmentController::class, 'detach']);

        Route::apiResource('devices', AdminAttendanceDeviceController::class);
        Route::post('devices/{device}/regenerate-token', [AdminAttendanceDeviceController::class, 'regenerateToken']);

        Route::get('permissions', [AdminStudentPermissionController::class, 'index']);
        Route::patch('permissions/{permission}/review', [AdminStudentPermissionController::class, 'review']);

        Route::get('attendance-report', [AdminAttendanceDeviceController::class, 'report']);

        Route::apiResource('dormitories', DormitoryController::class);
        Route::apiResource('dormitories.rooms', DormitoryRoomController::class)->shallow();

        Route::post('rooms/{room}/assign', [RoomAssignmentController::class, 'assign']);
        Route::post('assignments/{assignment}/checkout', [RoomAssignmentController::class, 'checkout']);
        Route::get('dormitories/{dormitory}/occupants', [RoomAssignmentController::class, 'occupants']);

        Route::get('tahfidz-report', [AdminTahfidzController::class, 'report']);

        Route::get('mutabaah-report', [AdminMutabaahController::class, 'report']);

        Route::prefix('boarding-permissions')->group(function () {
            Route::get('/', [AdminBoardingPermissionController::class, 'index']);
            Route::patch('/{permission}/review', [AdminBoardingPermissionController::class, 'review']);
            Route::patch('/{permission}/checkin', [AdminBoardingPermissionController::class, 'checkin']);
        });

        Route::apiResource('subjects', SubjectController::class);

        Route::apiResource('curriculums', CurriculumController::class);
        Route::post('curriculums/{curriculum}/subjects', [CurriculumSubjectController::class, 'attach']);
        Route::delete('curriculums/{curriculum}/subjects/{curriculumSubject}', [CurriculumSubjectController::class, 'detach']);
        Route::get('curriculums/{curriculum}/subjects', [CurriculumSubjectController::class, 'index']);

        Route::apiResource('lesson-schedules', LessonScheduleController::class);
        Route::get('classes/{class}/lesson-schedules', [LessonScheduleController::class, 'byClass']);
        Route::apiResource('academic-events', AcademicEventController::class);

        Route::get('grades-report', [AdminGradeController::class, 'report']);

        Route::get('class-promotions/preview', [ClassPromotionController::class, 'preview']);
        Route::post('class-promotions/process', [ClassPromotionController::class, 'process']);
        Route::get('class-promotions/history', [ClassPromotionController::class, 'history']);

        Route::apiResource('bill-types', BillTypeController::class);

        Route::prefix('student-bills')->group(function () {
            Route::get('/', [StudentBillController::class, 'index']);
            Route::post('/generate', [StudentBillController::class, 'generate']);
            Route::post('/{bill}/payments', [StudentBillController::class, 'recordPayment']);
        });

        Route::get('finance-report', [FinanceReportController::class, 'index']);

        Route::prefix('student-mutations')->group(function () {
            Route::get('/', [StudentMutationController::class, 'index']);
            Route::post('/masuk', [StudentMutationController::class, 'recordMasuk']);
            Route::post('/keluar', [StudentMutationController::class, 'recordKeluar']);
        });

        Route::apiResource('ppdb-periods', PpdbPeriodController::class);

        Route::prefix('ppdb-applicants')->group(function () {
            Route::get('/', [PpdbApplicantController::class, 'index']);
            Route::post('/{applicant}/test-scores', [PpdbApplicantController::class, 'recordTestScore']);
            Route::patch('/{applicant}/announce', [PpdbApplicantController::class, 'announce']);
            Route::patch('/{applicant}/daftar-ulang', [PpdbApplicantController::class, 'daftarUlang']);
            Route::post('/{applicant}/convert', [PpdbApplicantController::class, 'convertToStudent']);
        });

        Route::prefix('uks')->group(function () {
            Route::get('health-records', [AdminUksController::class, 'listHealthRecords']);
            Route::post('health-records', [AdminUksController::class, 'saveHealthRecord']);
            Route::get('visits', [AdminUksController::class, 'listVisits']);
            Route::post('visits', [AdminUksController::class, 'recordVisit']);
        });

        Route::apiResource('extracurriculars', ExtracurricularController::class);
        Route::post('extracurriculars/{extracurricular}/members', [ExtracurricularController::class, 'addMember']);
        Route::delete('extracurriculars/{extracurricular}/members/{student}', [ExtracurricularController::class, 'removeMember']);

        Route::prefix('achievements')->group(function () {
            Route::get('/', [StudentAchievementController::class, 'index']);
            Route::post('/', [StudentAchievementController::class, 'store']);
            Route::delete('/{achievement}', [StudentAchievementController::class, 'destroy']);
        });
    });

    // ═══════════════════════════════════════════════════════════
    // GURU — kelas yang diampu & operasional absen
    // ═══════════════════════════════════════════════════════════
    Route::middleware('context:school,guru')->prefix('guru')->group(function () {

        Route::get('dashboard/summary', [GuruDashboardController::class, 'summary']);

        Route::get('my-classes', [GuruClassController::class, 'index']);
        Route::get('my-classes/{class}/students', [GuruClassController::class, 'students']);

        Route::get('my-classes/{class}/attendance', [GuruAttendanceController::class, 'index']);
        Route::post('my-classes/{class}/attendance', [GuruAttendanceController::class, 'store']);
        Route::patch('attendance/{attendance}', [GuruAttendanceController::class, 'update']);

        Route::get('permissions', [GuruStudentPermissionController::class, 'index']);
        Route::patch('permissions/{permission}/review', [GuruStudentPermissionController::class, 'review']);

        Route::prefix('tahfidz')->group(function () {
            Route::get('/', [GuruTahfidzController::class, 'index']);
            Route::post('/', [GuruTahfidzController::class, 'store']);
            Route::put('/{setoran}', [GuruTahfidzController::class, 'update']);
            Route::delete('/{setoran}', [GuruTahfidzController::class, 'destroy']);
        });

        Route::prefix('mutabaah')->group(function () {
            Route::get('/', [GuruMutabaahController::class, 'index']);
            Route::post('/', [GuruMutabaahController::class, 'store']);
            Route::put('/{record}', [GuruMutabaahController::class, 'update']);
        });

        Route::get('subjects', [GuruSubjectController::class, 'index']);

        Route::get('curriculums/active', [GuruCurriculumController::class, 'active']);

        Route::get('my-schedule', [GuruLessonScheduleController::class, 'index']);
        Route::get('academic-events', [GuruAcademicEventController::class, 'index']);

        Route::prefix('grades')->group(function () {
            Route::get('/', [GuruGradeController::class, 'index']);
            Route::post('/', [GuruGradeController::class, 'store']);
            Route::post('/bulk', [GuruGradeController::class, 'storeBulk']);
            Route::put('/{grade}', [GuruGradeController::class, 'update']);
            Route::delete('/{grade}', [GuruGradeController::class, 'destroy']);
        });

        Route::prefix('materials')->group(function () {
            Route::get('/', [GuruMaterialController::class, 'index']);
            Route::post('/', [GuruMaterialController::class, 'store']);
            Route::delete('/{material}', [GuruMaterialController::class, 'destroy']);
        });

        Route::prefix('assignments')->group(function () {
            Route::get('/', [GuruAssignmentController::class, 'index']);
            Route::post('/', [GuruAssignmentController::class, 'store']);
            Route::delete('/{assignment}', [GuruAssignmentController::class, 'destroy']);
            Route::get('/{assignment}/submissions', [GuruAssignmentController::class, 'submissions']);
            Route::patch('/submissions/{submission}/grade', [GuruAssignmentController::class, 'grade']);
        });

        Route::prefix('uks')->group(function () {
            Route::get('health-records/{student}', [GuruUksController::class, 'healthRecord']);
            Route::post('visits', [GuruUksController::class, 'recordVisit']);
            Route::get('visits', [GuruUksController::class, 'listVisits']);
        });

        Route::prefix('my-extracurriculars')->group(function () {
            Route::get('/', [GuruExtracurricularController::class, 'index']);
            Route::get('/{extracurricular}/members', [GuruExtracurricularController::class, 'members']);
            Route::post('/{extracurricular}/attendance', [GuruExtracurricularController::class, 'storeAttendance']);
            Route::get('/{extracurricular}/attendance', [GuruExtracurricularController::class, 'getAttendance']);
        });
    });

    // ═══════════════════════════════════════════════════════════
    // WALI / ORANG TUA — pantau anak, ajukan izin
    // ═══════════════════════════════════════════════════════════
    Route::middleware('context:school,wali')->prefix('wali')->group(function () {

        Route::get('dashboard/summary', [WaliDashboardController::class, 'summary']);

        Route::get('my-children', [WaliStudentController::class, 'index']);
        Route::get('my-children/{student}', [WaliStudentController::class, 'show']);

        Route::get('my-children/{student}/attendance', [WaliAttendanceController::class, 'index']);

        Route::get('my-children/{student}/permissions', [WaliPermissionController::class, 'index']);
        Route::post('my-children/{student}/permissions', [WaliPermissionController::class, 'store']);
        Route::post('permissions/upload-attachment', [WaliPermissionController::class, 'uploadAttachment']);

        Route::prefix('boarding-permissions')->group(function () {
            Route::get('/', [WaliBoardingPermissionController::class, 'index']);
            Route::post('/', [WaliBoardingPermissionController::class, 'store']);
        });

        Route::get('academic-events', [WaliAcademicEventController::class, 'index']);

        Route::get('my-children/{student}/grades', [WaliGradeController::class, 'index']);

        Route::get('my-children/{student}/materials', [WaliMaterialController::class, 'index']);
        Route::get('my-children/{student}/assignments', [WaliAssignmentController::class, 'index']);
        Route::post('assignments/{assignment}/submit', [WaliAssignmentController::class, 'submit']);

        Route::get('my-children/{student}/bills', [WaliBillController::class, 'index']);

        Route::get('my-children/{student}/health', [WaliUksController::class, 'index']);

        Route::get('my-children/{student}/extracurriculars', [WaliExtracurricularController::class, 'index']);
        Route::get('my-children/{student}/achievements', [WaliExtracurricularController::class, 'achievements']);
    });
    });


/*
|--------------------------------------------------------------------------
| KIOSK — TIDAK pakai auth:sanctum / ContextMiddleware sama sekali.
| Auth-nya device_token lewat middleware VerifyDeviceToken.
| Taruh di luar grup 'school' di atas.
|--------------------------------------------------------------------------
*/
Route::prefix('kiosk')
    ->middleware(['verify.device'])
    ->group(function () {
    Route::get('ping', [KioskAuthController::class, 'ping']);
    Route::get('classes', [KioskAttendanceController::class, 'classes']);
    Route::get('classes/{class}/students', [KioskAttendanceController::class, 'students']);
        Route::post('attendance', [KioskAttendanceController::class, 'store']);
    });


// END ROUTE NEWS 2 #################################################################################
