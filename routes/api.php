<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatMessage\ChatController;
use App\Http\Controllers\Api\Employee\EmployeeAttendanceController;
use App\Http\Controllers\Api\Employee\EmployeeDailyReportController;
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
use App\Http\Controllers\Api\HrCompany\EmployeeHolidayController;
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
use App\Http\Controllers\Api\Santri\SantriAttendanceController;
use App\Http\Controllers\Api\Santri\SantriNotesController;
use App\Http\Controllers\Api\Santri\SantriPermissionController;
use App\Http\Controllers\Api\Santri\SantriPrayerController;
use App\Http\Controllers\Api\Santri\SantriSchedulesController;
use App\Http\Controllers\Api\Ustadz\PesantrenDashboardController;
use App\Http\Controllers\Api\Ustadz\PesantrenPrayerController;
use App\Http\Controllers\Api\Ustadz\PesantrenSantriController;
use App\Http\Controllers\Api\Ustadz\PesantrenSchedulesController;
use App\Http\Controllers\Api\Ustadz\PesantrenUstadzAttendanceController;
use App\Http\Controllers\Api\Ustadz\PesantrenUstadzSantriPermissionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

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


Route::prefix('auth')->group(function () {

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
                // GET /api/company/employee/stats/summary?month=3&year=2026
                Route::get('/summary', [EmployeeAttendanceController::class, 'summary']);
            });

            Route::prefix('employee/permissions')->group(function () {
                Route::get('/', [EmployeePermissionController::class, 'index']);
                Route::post('/', [EmployeePermissionController::class, 'store']);
                Route::get('/{id}', [EmployeePermissionController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/cancel', [EmployeePermissionController::class, 'cancel'])->whereNumber('id');
            });

            // ─── NOTES (Read Only — lihat catatan yang ditujukan ke dirinya) ──────────
            Route::prefix('employee/notes')->group(function () {
                Route::get('/summary', [EmployeeNotesController::class, 'summary']); // harus sebelum /{id}
                Route::get('/',        [EmployeeNotesController::class, 'index']);
                Route::get('/{id}',    [EmployeeNotesController::class, 'show'])->whereNumber('id');
                Route::patch('/{id}/read', [EmployeeNotesController::class, 'markRead'])->whereNumber('id');
            });

            // ─── DAILY REPORTS (CRUD — submit target pagi & pencapaian sore) ─────────
            Route::prefix('employee/daily-reports')->group(function () {
                Route::get('/today',  [EmployeeDailyReportController::class, 'today']);   // harus sebelum /{id}
                Route::get('/summary', [EmployeeDailyReportController::class, 'summary']); // harus sebelum /{id}
                Route::get('/export',  [EmployeeDailyReportController::class, 'export']);
                Route::get('/',       [EmployeeDailyReportController::class, 'index']);
                Route::post('/',      [EmployeeDailyReportController::class, 'store']);    // submit target pagi
                Route::get('/{id}',   [EmployeeDailyReportController::class, 'show'])->whereNumber('id');
                Route::post('/{id}',  [EmployeeDailyReportController::class, 'update'])->whereNumber('id'); // update pencapaian sore
            });

            // ─── MONTHLY REPORTS (CRUD — buat draft, edit, submit) ───────────────────
            Route::prefix('employee/monthly-reports')->group(function () {
                Route::get('/summary', [EmployeeMonthlyReportController::class, 'summary']); // harus sebelum /{id}
                Route::get('/export',  [EmployeeMonthlyReportController::class, 'export']);
                Route::get('/',        [EmployeeMonthlyReportController::class, 'index']);
                Route::post('/',       [EmployeeMonthlyReportController::class, 'store']);
                Route::get('/{id}',    [EmployeeMonthlyReportController::class, 'show'])->whereNumber('id');
                Route::post('/{id}',   [EmployeeMonthlyReportController::class, 'update'])->whereNumber('id');
                Route::patch('/{id}/submit', [EmployeeMonthlyReportController::class, 'submit'])->whereNumber('id');
                Route::delete('/{id}', [EmployeeMonthlyReportController::class, 'destroy'])->whereNumber('id');
            });

            // ─── PERFORMANCE SCORES (Read Only — lihat skor sendiri + leaderboard) ───
            Route::prefix('employee/performance-scores')->group(function () {
                Route::get('/leaderboard', [EmployeePerformanceScoreController::class, 'leaderboard']); // harus sebelum /{id}
                Route::get('/',            [EmployeePerformanceScoreController::class, 'index']);
                Route::get('/{id}',        [EmployeePerformanceScoreController::class, 'show'])->whereNumber('id');
            });

            Route::prefix('employee/shifts')->group(function () {
                // shift aktif hari ini
                Route::get('/today', [EmployeeShiftController::class, 'today']);

                // shift aktif untuk tanggal tertentu (YYYY-MM-DD)
                Route::get('/date/{date}', [EmployeeShiftController::class, 'byDate']);

                // jadwal shift (range tanggal)
                Route::get('/schedule', [EmployeeShiftController::class, 'schedule']);
            });

            Route::prefix('employee/schedules')->group(function () {
                Route::get('/',                  [EmployeeSchedulesController::class, 'index']);
                Route::get('/invitations',       [EmployeeSchedulesController::class, 'invitations']);
                Route::get('/{id}',              [EmployeeSchedulesController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/respond',     [EmployeeSchedulesController::class, 'respond'])->whereNumber('id');
            });

            Route::prefix('employee/loans')->group(function () {

                // GET /api/company/employee/loans/active
                Route::get('/active', [EmployeeLoanController::class, 'active']);

                // List semua pinjaman milik sendiri → ?status=pending
                // GET /api/company/employee/loans
                Route::get('/', [EmployeeLoanController::class, 'index']);

                // Detail pinjaman + histori bayar + progress
                // GET /api/company/employee/loans/{id}
                Route::get('/{id}', [EmployeeLoanController::class, 'show'])->whereNumber('id');

                // Ajukan pinjaman baru (self-service) → status: pending
                // POST /api/company/employee/loans
                Route::post('/', [EmployeeLoanController::class, 'store']);

                // Batalkan pengajuan (hanya bisa saat status pending)
                // PUT /api/company/employee/loans/{id}/cancel
                Route::put('/{id}/cancel', [EmployeeLoanController::class, 'cancel'])->whereNumber('id');

                // Histori pembayaran cicilan milik sendiri
                // GET /api/company/employee/loans/{id}/payments
                Route::get('/{id}/payments', [EmployeeLoanController::class, 'paymentHistory'])->whereNumber('id');
            });

            Route::prefix('employee/payrolls')->group(function () {
                Route::get('/', [EmployeePayrollController::class, 'index']);
                Route::get('/{id}', [EmployeePayrollController::class, 'show'])->whereNumber('id');
            });

            // EMPLOYEE - LEAVES (CUTI)
            Route::prefix('employee/leaves')->group(function () {
                Route::get('/', [EmployeeLeaveController::class, 'index']);
                Route::post('/', [EmployeeLeaveController::class, 'store']);
                Route::get('/{id}', [EmployeeLeaveController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/cancel', [EmployeeLeaveController::class, 'cancel'])->whereNumber('id');
            });

            // EMPLOYEE - OVERTIME REQUESTS
            Route::prefix('employee/overtimes')->group(function () {
                Route::get('/', [EmployeeOvertimeRequestController::class, 'index']);
                Route::post('/', [EmployeeOvertimeRequestController::class, 'store']);
                Route::get('/{id}', [EmployeeOvertimeRequestController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/cancel', [EmployeeOvertimeRequestController::class, 'cancel'])->whereNumber('id');
            });

            // EMPLOYEE - COMPANY HOLIDAYS
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

                // GET /api/company/hr/analytics/monthly?month=3&year=2026
                // → breakdown harian + ringkasan + top terlambat + top alpha
                Route::get('/monthly', [HrCompanyAnalyticsController::class, 'monthly']);

                // GET /api/company/hr/analytics/employee/{userId}?month=3&year=2026
                // → detail laporan per-karyawan (absensi + izin + cuti)
                Route::get('/employee/{userId}', [HrCompanyAnalyticsController::class, 'employeeDetail']);

                // GET /api/company/hr/analytics/attendance-recap?start=&end=&per_page=
                // → rekap tabel absensi range tanggal (maks 93 hari), paginated
                Route::get('/attendance-recap', [HrCompanyAnalyticsController::class, 'attendanceRecap']);
            });

            // ==============================================================
            // 3. PENGATURAN PERUSAHAAN
            // ==============================================================
            Route::prefix('hr/settings')->group(function () {

                // GET    /api/company/hr/settings/company  → ambil data perusahaan
                Route::get('/company', [HrCompanySettingController::class, 'show']);

                // PUT    /api/company/hr/settings/company  → update data perusahaan
                Route::put('/company', [HrCompanySettingController::class, 'update']);

                // POST   /api/company/hr/settings/company/logo  → upload logo
                Route::post('/company/logo', [HrCompanySettingController::class, 'uploadLogo']);

                // GET    /api/company/hr/settings/company/employees?search=&department=&per_page=
                // → list karyawan (untuk tabel di pengaturan)
                Route::get('/company/employees', [HrCompanySettingController::class, 'employees']);

                // GET    /api/company/hr/settings/company/departments
                // → list departemen unik
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
                Route::get('/employees/{id}/history/export',    [HrCompanyAttendanceController::class, 'exportEmployeePdf'])->whereNumber('id'); // BARU
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

            // versi new
            Route::prefix('hr/shift-groups')->group(function () {
                Route::get('/', [HrCompanyShiftGroupController::class, 'index']);
                Route::post('/', [HrCompanyShiftGroupController::class, 'store']);
                Route::get('/{id}', [HrCompanyShiftGroupController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [HrCompanyShiftGroupController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [HrCompanyShiftGroupController::class, 'destroy'])->whereNumber('id');

                // Members
                Route::get('/{id}/users', [HrCompanyShiftGroupUserController::class, 'index'])->whereNumber('id');

                // attach: bisa lewat user_ids (checklist) ATAU filter departemen/position (bulk)
                Route::post('/{id}/users/attach', [HrCompanyShiftGroupUserController::class, 'attach'])->whereNumber('id');
                Route::post('/{id}/users/detach', [HrCompanyShiftGroupUserController::class, 'detach'])->whereNumber('id');

                // Assignments (set shift + range tanggal untuk group)
                Route::get('/{id}/assignments', [HrCompanyShiftGroupAssignmentController::class, 'index'])->whereNumber('id');
                Route::post('/{id}/assignments', [HrCompanyShiftGroupAssignmentController::class, 'store'])->whereNumber('id');


                // Update / delete assignment by assignment id
                Route::put('/shift-group-assignments/{id}', [HrCompanyShiftGroupAssignmentController::class, 'update'])->whereNumber('id');
                Route::delete('/shift-group-assignments/{id}', [HrCompanyShiftGroupAssignmentController::class, 'destroy'])->whereNumber('id');

                /**
                 * USER SHIFT OVERRIDES (pengecualian per user)
                 */
                Route::get('/users/{userId}/shift-overrides', [HrCompanyUserShiftOverrideController::class, 'index'])->whereNumber('userId');
                Route::post('/users/{userId}/shift-overrides', [HrCompanyUserShiftOverrideController::class, 'store'])->whereNumber('userId');
                Route::delete('/user-shift-overrides/{id}', [HrCompanyUserShiftOverrideController::class, 'destroy'])->whereNumber('id');
                Route::patch('/user-shift-overrides/{id}/cancel', [HrCompanyUserShiftOverrideController::class, 'cancel'])->whereNumber('id');
            });

            Route::prefix('hr/loans')->group(function () {
                // Dashboard ringkasan
                // GET /api/hr/loans/summary
                Route::get('/summary', [HrCompanyLoanController::class, 'summary']);

                Route::get('/export',                    [HrCompanyLoanController::class, 'export']);

                // List semua pinjaman  → ?status=pending  ?user_id=5  ?payment_type=scheduled_date
                // GET /api/hr/loans
                Route::get('/', [HrCompanyLoanController::class, 'index']);

                // Detail pinjaman + histori bayar + progress
                // GET /api/hr/loans/{id}
                Route::get('/{id}', [HrCompanyLoanController::class, 'show'])->whereNumber('id');

                // HR buat pinjaman langsung untuk employee (sebelum self-service aktif)
                // POST /api/hr/loans
                Route::post('/', [HrCompanyLoanController::class, 'store']);

                // Approve pinjaman (pending → active), bisa override monthly_installment
                // PUT /api/hr/loans/{id}/approve
                Route::put('/{id}/approve', [HrCompanyLoanController::class, 'approve'])->whereNumber('id');

                // Reject pinjaman (wajib isi alasan)
                // PUT /api/hr/loans/{id}/reject
                Route::put('/{id}/reject', [HrCompanyLoanController::class, 'reject'])->whereNumber('id');

                // Cancel pinjaman (pending atau active → canceled)
                // PUT /api/hr/loans/{id}/cancel
                Route::put('/{id}/cancel', [HrCompanyLoanController::class, 'cancel'])->whereNumber('id');

                // Histori bayar detail + summary (total_paid, progress %, total_diff)
                // GET /api/hr/loans/{id}/payments
                Route::get('/{id}/payments', [HrCompanyLoanController::class, 'paymentHistory'])->whereNumber('id');

                // Input pembayaran cicilan (partial OK)
                // POST /api/hr/loans/{id}/payments
                Route::post('/{id}/payments', [HrCompanyLoanController::class, 'recordPayment'])->whereNumber('id');

                // Hapus record bayar jika salah input → balance otomatis dikembalikan
                // DELETE /api/hr/loans/{id}/payments/{paymentId}
                Route::delete('/{id}/payments/{paymentId}', [HrCompanyLoanController::class, 'deletePayment'])->whereNumber('id');
            });

            Route::prefix('hr/payrolls')->name('payrolls.')->group(function () {

                // Statis — harus di atas /{id} agar tidak bentrok
                Route::post('generate', [HrCompanyPayrollController::class, 'generate'])->name('generate');
                Route::get('summary',   [HrCompanyPayrollController::class, 'summary'])->name('summary');

                // CRUD standar
                Route::get('/',       [HrCompanyPayrollController::class, 'index'])->name('index');
                Route::post('/',      [HrCompanyPayrollController::class, 'store'])->name('store');
                Route::get('{id}',    [HrCompanyPayrollController::class, 'show'])->name('show')->whereNumber('id');
                Route::put('{id}',    [HrCompanyPayrollController::class, 'update'])->name('update')->whereNumber('id');
                Route::delete('{id}', [HrCompanyPayrollController::class, 'destroy'])->name('destroy')->whereNumber('id');

                // Workflow status
                Route::patch('{id}/approve',   [HrCompanyPayrollController::class, 'approve'])->name('approve')->whereNumber('id');
                Route::patch('{id}/mark-paid', [HrCompanyPayrollController::class, 'markAsPaid'])->name('mark-paid')->whereNumber('id');

                // Slip gaji
                Route::get('{id}/slip', [HrCompanyPayrollController::class, 'slip'])->name('slip')->whereNumber('id');

                // ─────────────────────────────────────────────────────────
                // PAYROLL COMPONENTS (nested)
                // ─────────────────────────────────────────────────────────
                Route::prefix('{payrollId}/components')->name('components.')->whereNumber('payrollId')->group(function () {

                    Route::get('/',    [HrCompanyPayrollComponentController::class, 'index'])->name('index');
                    Route::post('/',   [HrCompanyPayrollComponentController::class, 'store'])->name('store');
                    Route::post('bulk', [HrCompanyPayrollComponentController::class, 'storeBulk'])->name('bulk');

                    // FIX: whereNumber('componentId') — bukan 'id'
                    Route::put('{componentId}',    [HrCompanyPayrollComponentController::class, 'update'])->name('update')->whereNumber('componentId');
                    Route::delete('{componentId}', [HrCompanyPayrollComponentController::class, 'destroy'])->name('destroy')->whereNumber('componentId');
                });
            });


            // HR - COMPANY HOLIDAYS
            Route::prefix('hr/holidays')->group(function () {
                Route::get('/export',  [HrCompanyHolidayController::class, 'export']);
                Route::get('/', [HrCompanyHolidayController::class, 'index']);
                Route::post('/', [HrCompanyHolidayController::class, 'store']);
                Route::get('/{id}', [HrCompanyHolidayController::class, 'show'])->whereNumber('id');
                Route::put('/{id}', [HrCompanyHolidayController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [HrCompanyHolidayController::class, 'destroy'])->whereNumber('id');
            });

            // HR - LEAVES (approve/reject)
            Route::prefix('hr/leaves')->group(function () {
                Route::get('/export',        [HrCompanyLeaveController::class, 'export']);
                Route::get('/', [HrCompanyLeaveController::class, 'index']);
                Route::get('/{id}', [HrCompanyLeaveController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/approve', [HrCompanyLeaveController::class, 'approve'])->whereNumber('id');
                Route::post('/{id}/reject', [HrCompanyLeaveController::class, 'reject'])->whereNumber('id');
            });

            // HR - OVERTIMES (approve/reject)
            Route::prefix('hr/overtimes')->group(function () {
                Route::get('/export',          [HrCompanyOvertimeRequestController::class, 'export']);
                Route::get('/', [HrCompanyOvertimeRequestController::class, 'index']);
                Route::get('/{id}', [HrCompanyOvertimeRequestController::class, 'show'])->whereNumber('id');
                Route::post('/{id}/approve', [HrCompanyOvertimeRequestController::class, 'approve'])->whereNumber('id');
                Route::post('/{id}/reject', [HrCompanyOvertimeRequestController::class, 'reject'])->whereNumber('id');
            });

            // HR SCHEDULES
            Route::prefix('hr/schedules')->group(function () {
                Route::get('/export',                        [HrCompanyScheduleController::class, 'export']);
                // CRUD Jadwal
                Route::get('/',             [HrCompanyScheduleController::class, 'index']);
                Route::post('/',            [HrCompanyScheduleController::class, 'store']);
                Route::get('/{id}',        [HrCompanyScheduleController::class, 'show'])->whereNumber('id');
                Route::put('/{id}',        [HrCompanyScheduleController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}',     [HrCompanyScheduleController::class, 'destroy'])->whereNumber('id');

                // Participants
                Route::get('/{id}/participants',              [HrCompanyScheduleController::class, 'getParticipants'])->whereNumber('id');
                Route::post('/{id}/participants',             [HrCompanyScheduleController::class, 'addParticipants'])->whereNumber('id');
                Route::delete('/{id}/participants/{userId}',  [HrCompanyScheduleController::class, 'removeParticipant'])->whereNumber('id');
            });

            // HR NOTES
            Route::prefix('hr/notes')->group(function () {
                Route::get('/summary',   [HrCompanyNotesController::class, 'summary']);   // harus sebelum /{id}
                Route::get('/export',  [HrCompanyNotesController::class, 'export']);
                Route::get('/',          [HrCompanyNotesController::class, 'index']);
                Route::post('/',         [HrCompanyNotesController::class, 'store']);
                Route::get('/{id}',      [HrCompanyNotesController::class, 'show'])->whereNumber('id');
                Route::post('/{id}',     [HrCompanyNotesController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}',   [HrCompanyNotesController::class, 'destroy'])->whereNumber('id');
            });

            Route::prefix('hr/daily-reports')->group(function () {
                Route::get('/summary',   [HrCompanyDailyReportController::class, 'summary']);  // harus sebelum /{id}
                Route::get('/today',     [HrCompanyDailyReportController::class, 'today']);
                Route::get('/employees',            [HrCompanyDailyReportController::class, 'employees']);   // BARU
                Route::get('/export',               [HrCompanyDailyReportController::class, 'export']);      // BARU: download PDF
                Route::get('/',          [HrCompanyDailyReportController::class, 'index']);
                Route::get('/{id}',      [HrCompanyDailyReportController::class, 'show'])->whereNumber('id');
            });

            Route::prefix('hr/monthly-reports')->group(function () {
                Route::get('/summary',        [HrCompanyMonthlyReportController::class, 'summary']); // harus sebelum /{id}
                Route::get('/export',         [HrCompanyMonthlyReportController::class, 'export']);
                Route::get('/',               [HrCompanyMonthlyReportController::class, 'index']);
                Route::get('/{id}',           [HrCompanyMonthlyReportController::class, 'show'])->whereNumber('id');
                Route::patch('/{id}/approve', [HrCompanyMonthlyReportController::class, 'approve'])->whereNumber('id');
                Route::patch('/{id}/reject',  [HrCompanyMonthlyReportController::class, 'reject'])->whereNumber('id');
            });

            Route::prefix('hr/performance-scores')->group(function () {
                Route::get('/leaderboard',  [HrCompanyPerformanceScoreController::class, 'leaderboard']); // harus sebelum /{id}
                Route::get('/export',      [HrCompanyPerformanceScoreController::class, 'export']);
                Route::get('/',             [HrCompanyPerformanceScoreController::class, 'index']);
                Route::post('/generate',    [HrCompanyPerformanceScoreController::class, 'generate']);
                Route::get('/{id}',         [HrCompanyPerformanceScoreController::class, 'show'])->whereNumber('id');
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
