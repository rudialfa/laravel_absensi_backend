<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Note;
use App\Models\Company;
use App\Models\MonthlyReport;
use Illuminate\Support\Carbon;

class EmployeeNotesController extends Controller
{

    // private function ensureEmployee(): void
    // {
    //     if (!auth()->check() || auth()->user()->role !== 'employee') {
    //         abort(response()->json([
    //             'status' => false,
    //             'message' => 'Akses ditolak (khusus employee)',
    //         ], 403));
    //     }
    // }

    // private function companyId(): int
    // {
    //     $companyId = auth()->user()->company_id ?? null;

    //     if (!$companyId) {
    //         abort(response()->json([
    //             'status' => false,
    //             'message' => 'Company ID tidak ditemukan',
    //         ], 422));
    //     }

    //     return $companyId;
    // }

    // public function index()
    // {
    //     $this->ensureEmployee();

    //     $data = Note::query()
    //         ->where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id())
    //         ->orderByDesc('id')
    //         ->paginate(20);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'List notes saya',
    //         'data' => $data,
    //     ]);
    // }

    // public function store(Request $request)
    // {
    //     $this->ensureEmployee();

    //     $validated = $request->validate([
    //         'title' => 'required|string|max:120',
    //         'note'  => 'required|string',
    //     ]);

    //     $note = Note::create([
    //         'company_id' => $this->companyId(),
    //         'user_id'    => auth()->id(),
    //         'title'      => $validated['title'],
    //         'note'       => $validated['note'],
    //     ]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Note dibuat',
    //         'data' => $note,
    //     ], 201);
    // }

    // public function show($id)
    // {
    //     $this->ensureEmployee();

    //     $note = Note::query()
    //         ->where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id())
    //         ->findOrFail($id);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Detail note',
    //         'data' => $note,
    //     ]);
    // }

    // public function update(Request $request, $id)
    // {
    //     $this->ensureEmployee();

    //     $note = Note::query()
    //         ->where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id())
    //         ->findOrFail($id);

    //     $validated = $request->validate([
    //         'title' => 'sometimes|required|string|max:120',
    //         'note'  => 'sometimes|required|string',
    //     ]);

    //     if (array_key_exists('title', $validated)) $note->title = $validated['title'];
    //     if (array_key_exists('note', $validated))  $note->note  = $validated['note'];

    //     $note->save();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Note diupdate',
    //         'data' => $note,
    //     ]);
    // }

    // public function destroy($id)
    // {
    //     $this->ensureEmployee();

    //     $note = Note::query()
    //         ->where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id())
    //         ->findOrFail($id);

    //     $note->delete();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Note dihapus',
    //     ]);
    // }

    // kode 2 revisi
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
        $companyId = $this->companyId();
        $tz = Company::query()->whereKey($companyId)->value('timezone') ?: 'Asia/Jakarta';
        return Carbon::now($tz);
    }

    private function requireMonthlyReportIf25th(): void
    {
        $now = $this->nowCompanyTz();

        // hanya berlaku tanggal 25
        if ((int) $now->day !== 25) return;

        $exists = MonthlyReport::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->where('year', (int) $now->year)
            ->where('month', (int) $now->month)
            ->exists();

        if (!$exists) {
            abort(response()->json([
                'status' => false,
                'message' => 'Tanggal 25 wajib mengisi Laporan Bulanan terlebih dahulu.',
            ], 422));
        }
    }

    // =========================
    // LIST (paginate)
    // =========================
    public function index(Request $request)
    {
        $this->ensureEmployee();

        $data = Note::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'List notes saya',
            'data' => $data,
        ]);
    }

    // =========================
    // STORE (HARIAN)
    // =========================
    public function store(Request $request)
    {
        $this->ensureEmployee();

        // ✅ gate: tanggal 25 harus isi laporan bulanan dulu
        $this->requireMonthlyReportIf25th();

        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'note'  => 'required|string',
        ]);

        $note = Note::create([
            'company_id' => $this->companyId(),
            'user_id'    => auth()->id(),
            'title'      => $validated['title'],
            'note'       => $validated['note'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Note dibuat',
            'data' => $note,
        ], 201);
    }

    // =========================
    // DETAIL
    // =========================
    public function show($id)
    {
        $this->ensureEmployee();

        $note = Note::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail note',
            'data' => $note,
        ]);
    }
}
