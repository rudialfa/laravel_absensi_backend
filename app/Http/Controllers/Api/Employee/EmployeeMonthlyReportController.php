<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class EmployeeMonthlyReportController extends Controller
{
    // // ─── HELPER: Upload attachment ────────────────────────────────────────────
    // private function uploadAttachment($file): string
    // {
    //     $destinationPath = public_path('image/monthly-reports');

    //     if (!File::exists($destinationPath)) {
    //         File::makeDirectory($destinationPath, 0755, true);
    //     }

    //     $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
    //     $file->move($destinationPath, $fileName);

    //     return 'image/monthly-reports/' . $fileName;
    // }

    // private function deleteAttachment(?string $path): void
    // {
    //     if ($path && File::exists(public_path($path))) {
    //         File::delete(public_path($path));
    //     }
    // }

    // // ─── GET /employee/monthly-reports ────────────────────────────────────────
    // // Lihat semua laporan bulanan milik sendiri
    // public function index(Request $request)
    // {
    //     $query = MonthlyReport::with('approver:id,name')
    //         ->where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id());

    //     if ($request->filled('month')) {
    //         $query->where('month', $request->month);
    //     }

    //     if ($request->filled('year')) {
    //         $query->where('year', $request->year);
    //     }

    //     // Filter status: draft | submitted | approved | rejected
    //     if ($request->filled('status')) {
    //         $query->where('status', $request->status);
    //     }

    //     $reports = $query->orderByDesc('year')
    //         ->orderByDesc('month')
    //         ->paginate($request->get('per_page', 15));

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Berhasil mengambil data laporan bulanan',
    //         'data'    => $reports,
    //     ]);
    // }

    // // ─── GET /employee/monthly-reports/summary ────────────────────────────────
    // // Ringkasan laporan (berapa approved, rejected, draft, avg score)
    // public function summary(Request $request)
    // {
    //     $reports = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->when($request->filled('year'), fn($q) => $q->where('year', $request->year))
    //         ->get();

    //     $stats = [
    //         'total'     => $reports->count(),
    //         'approved'  => $reports->where('status', 'approved')->count(),
    //         'rejected'  => $reports->where('status', 'rejected')->count(),
    //         'submitted' => $reports->where('status', 'submitted')->count(),
    //         'draft'     => $reports->where('status', 'draft')->count(),
    //         'avg_score' => round($reports->where('status', 'approved')->avg('score') ?? 0, 2),
    //     ];

    //     return response()->json([
    //         'status' => true,
    //         'data'   => $stats,
    //     ]);
    // }

    // // ─── GET /employee/monthly-reports/{id} ───────────────────────────────────
    // public function show($id)
    // {
    //     $report = MonthlyReport::with('approver:id,name')
    //         ->where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->findOrFail($id);

    //     return response()->json([
    //         'status' => true,
    //         'data'   => $report,
    //     ]);
    // }

    // // ─── POST /employee/monthly-reports ───────────────────────────────────────
    // // Buat laporan baru (sebagai draft)
    // public function store(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'month'      => 'required|integer|between:1,12',
    //         'year'       => 'required|integer',
    //         'content'    => 'required|string',
    //         'attachment' => 'nullable|image|mimes:jpg,jpeg,png,pdf|max:5120',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
    //     }

    //     // Cek sudah ada laporan bulan ini belum
    //     $exists = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->where('month', $request->month)
    //         ->where('year', $request->year)
    //         ->exists();

    //     if ($exists) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Laporan bulan ' . $request->month . '/' . $request->year . ' sudah ada',
    //         ], 422);
    //     }

    //     $data = [
    //         'company_id' => Auth::user()->company_id,
    //         'user_id'    => Auth::id(),
    //         'month'      => $request->month,
    //         'year'       => $request->year,
    //         'content'    => $request->content,
    //         'status'     => 'draft',
    //     ];

    //     if ($request->hasFile('attachment')) {
    //         $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
    //     }

    //     $report = MonthlyReport::create($data);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Laporan berhasil dibuat sebagai draft',
    //         'data'    => $report,
    //     ], 201);
    // }

    // // ─── POST /employee/monthly-reports/{id} ──────────────────────────────────
    // // Edit laporan — hanya bisa jika masih draft atau rejected
    // // Pakai POST bukan PUT karena ada kemungkinan file upload
    // public function update(Request $request, $id)
    // {
    //     $report = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->findOrFail($id);

    //     // Hanya bisa edit jika draft atau rejected
    //     if (!in_array($report->status, ['draft', 'rejected'])) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Laporan yang sudah disubmit / diapprove tidak bisa diedit',
    //         ], 422);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'content'    => 'sometimes|string',
    //         'attachment' => 'nullable|image|mimes:jpg,jpeg,png,pdf|max:5120',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
    //     }

    //     $data = [];

    //     if ($request->filled('content')) {
    //         $data['content'] = $request->content;
    //     }

    //     if ($request->hasFile('attachment')) {
    //         $this->deleteAttachment($report->attachment);
    //         $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
    //     }

    //     // Jika rejected dan diedit, kembalikan ke draft
    //     if ($report->status === 'rejected') {
    //         $data['status']      = 'draft';
    //         $data['approved_by'] = null;
    //         $data['approved_at'] = null;
    //         $data['score']       = 0;
    //     }

    //     $report->update($data);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Laporan berhasil diupdate',
    //         'data'    => $report->fresh('approver:id,name'),
    //     ]);
    // }

    // // ─── PATCH /employee/monthly-reports/{id}/submit ──────────────────────────
    // // Submit laporan ke HR (dari draft → submitted)
    // public function submit($id)
    // {
    //     $report = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->findOrFail($id);

    //     if ($report->status !== 'draft') {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Hanya laporan berstatus draft yang bisa disubmit',
    //         ], 422);
    //     }

    //     $report->update(['status' => 'submitted']);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Laporan berhasil disubmit ke HR',
    //         'data'    => $report->fresh(),
    //     ]);
    // }

    // // ─── DELETE /employee/monthly-reports/{id} ────────────────────────────────
    // // Hapus laporan — hanya bisa jika masih draft
    // public function destroy($id)
    // {
    //     $report = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->findOrFail($id);

    //     if ($report->status !== 'draft') {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Hanya laporan berstatus draft yang bisa dihapus',
    //         ], 422);
    //     }

    //     $this->deleteAttachment($report->attachment);
    //     $report->delete();

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Laporan berhasil dihapus',
    //     ]);
    // }

    private function uploadAttachment($file): string
    {
        $destinationPath = public_path('image/monthly-reports');
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($destinationPath, $fileName);
        return 'image/monthly-reports/' . $fileName;
    }

    private function deleteAttachment(?string $path): void
    {
        if ($path && File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }

    // GET /employee/monthly-reports
    public function index(Request $request)
    {
        $query = MonthlyReport::with('approver:id,name')
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id());

        if ($request->filled('month'))  $query->where('month', $request->month);
        if ($request->filled('year'))   $query->where('year', $request->year);
        if ($request->filled('status')) $query->where('status', $request->status);

        $reports = $query->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data laporan bulanan',
            'data'    => $reports,
        ]);
    }

    // GET /employee/monthly-reports/summary
    public function summary(Request $request)
    {
        $reports = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->when($request->filled('year'), fn($q) => $q->where('year', $request->year))
            ->get();

        return response()->json([
            'status' => true,
            'data'   => [
                'total'     => $reports->count(),
                'approved'  => $reports->where('status', 'approved')->count(),
                'rejected'  => $reports->where('status', 'rejected')->count(),
                'submitted' => $reports->where('status', 'submitted')->count(),
                'draft'     => $reports->where('status', 'draft')->count(),
                'avg_score' => round($reports->where('status', 'approved')->avg('score') ?? 0, 2),
            ],
        ]);
    }

    // GET /employee/monthly-reports/{id}
    public function show($id)
    {
        $report = MonthlyReport::with('approver:id,name')
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json(['status' => true, 'data' => $report]);
    }

    // POST /employee/monthly-reports — Buat draft baru
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month'       => 'required|integer|between:1,12',
            'year'        => 'required|integer',
            'target'      => 'required|string',
            'achievement' => 'required|string',
            'problem'     => 'required|string',
            'solution'    => 'required|string',
            'attachment'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        // Cek duplikat bulan
        $exists = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'Laporan bulan ' . $request->month . '/' . $request->year . ' sudah ada',
            ], 422);
        }

        $data = [
            'company_id'  => Auth::user()->company_id,
            'user_id'     => Auth::id(),
            'month'       => $request->month,
            'year'        => $request->year,
            'target'      => $request->target,
            'achievement' => $request->achievement,
            'problem'     => $request->problem,
            'solution'    => $request->solution,
            'status'      => 'draft',
        ];

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
        }

        $report = MonthlyReport::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Laporan berhasil dibuat sebagai draft',
            'data'    => $report,
        ], 201);
    }

    // POST /employee/monthly-reports/{id} — Edit (hanya draft/rejected)
    public function update(Request $request, $id)
    {
        $report = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        if (!in_array($report->status, ['draft', 'rejected'])) {
            return response()->json([
                'status'  => false,
                'message' => 'Laporan yang sudah disubmit / diapprove tidak bisa diedit',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'target'      => 'sometimes|string',
            'achievement' => 'sometimes|string',
            'problem'     => 'sometimes|string',
            'solution'    => 'sometimes|string',
            'attachment'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = array_filter([
            'target'      => $request->target,
            'achievement' => $request->achievement,
            'problem'     => $request->problem,
            'solution'    => $request->solution,
        ]);

        if ($request->hasFile('attachment')) {
            $this->deleteAttachment($report->attachment);
            $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
        }

        // Jika rejected → kembali ke draft saat diedit
        if ($report->status === 'rejected') {
            $data['status']      = 'draft';
            $data['approved_by'] = null;
            $data['approved_at'] = null;
            $data['score']       = 0;
        }

        $report->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Laporan berhasil diupdate',
            'data'    => $report->fresh('approver:id,name'),
        ]);
    }

    // PATCH /employee/monthly-reports/{id}/submit
    public function submit($id)
    {
        $report = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        if ($report->status !== 'draft') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya laporan berstatus draft yang bisa disubmit',
            ], 422);
        }

        $report->update(['status' => 'submitted']);

        return response()->json([
            'status'  => true,
            'message' => 'Laporan berhasil disubmit ke HR',
            'data'    => $report->fresh(),
        ]);
    }

    // DELETE /employee/monthly-reports/{id}
    public function destroy($id)
    {
        $report = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        if ($report->status !== 'draft') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya laporan berstatus draft yang bisa dihapus',
            ], 422);
        }

        $this->deleteAttachment($report->attachment);
        $report->delete();

        return response()->json(['status' => true, 'message' => 'Laporan berhasil dihapus']);
    }
}
