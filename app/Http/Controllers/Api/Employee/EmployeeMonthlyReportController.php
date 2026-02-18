<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\MonthlyReport;
use Illuminate\Support\Carbon;

class EmployeeMonthlyReportController extends Controller
{
    private function ensureEmployee(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus employee)',
            ], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;

        if (!$companyId) {
            abort(response()->json([
                'status' => false,
                'message' => 'Company ID tidak ditemukan',
            ], 422));
        }

        return (int) $companyId;
    }

    private function nowCompanyTz(): Carbon
    {
        $tz = Company::query()->whereKey($this->companyId())->value('timezone') ?: 'Asia/Jakarta';
        return Carbon::now($tz);
    }

    // status untuk frontend: apakah wajib isi laporan bulanan?
    public function status()
    {
        $this->ensureEmployee();

        $now = $this->nowCompanyTz();
        $is25 = ((int) $now->day === 25);

        $exists = MonthlyReport::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->where('year', (int) $now->year)
            ->where('month', (int) $now->month)
            ->exists();

        return response()->json([
            'status' => true,
            'message' => 'Status laporan bulanan',
            'data' => [
                'today' => $now->toDateString(),
                'is_25th' => $is25,
                'must_fill' => $is25 && !$exists,
                'filled' => $exists,
                'year' => (int) $now->year,
                'month' => (int) $now->month,
            ],
        ]);
    }

    // submit laporan (hanya 25, dan 1x per bulan)
    public function store(Request $request)
    {
        $this->ensureEmployee();

        $now = $this->nowCompanyTz();
        if ((int) $now->day !== 25) {
            return response()->json([
                'status' => false,
                'message' => 'Laporan bulanan hanya bisa diisi pada tanggal 25.',
            ], 422);
        }

        $validated = $request->validate([
            'target' => 'required|string',
            'achievement' => 'required|string',
            'problem' => 'required|string',
            'solution' => 'required|string',
        ]);

        $report = MonthlyReport::updateOrCreate(
            [
                'company_id' => $this->companyId(),
                'user_id' => auth()->id(),
                'year' => (int) $now->year,
                'month' => (int) $now->month,
            ],
            [
                'target' => $validated['target'],
                'achievement' => $validated['achievement'],
                'problem' => $validated['problem'],
                'solution' => $validated['solution'],
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Laporan bulanan tersimpan',
            'data' => $report,
        ], 201);
    }

    // lihat laporan bulan ini (optional)
    public function current()
    {
        $this->ensureEmployee();

        $now = $this->nowCompanyTz();

        $report = MonthlyReport::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->where('year', (int) $now->year)
            ->where('month', (int) $now->month)
            ->first();

        return response()->json([
            'status' => true,
            'message' => 'Laporan bulanan bulan ini',
            'data' => $report,
        ]);
    }
}
