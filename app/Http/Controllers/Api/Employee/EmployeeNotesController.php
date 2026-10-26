<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeNotesController extends Controller
{
    private const USER_COLS = 'id,name,position,department,image_url';

    private function ensureEmployee(): void
    {
        if (!Auth::check() || Auth::user()->role !== 'employee') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus employee)',
            ], 403));
        }
    }

    private function myQuery()
    {
        return Note::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id()); // hanya catatan untuk saya
    }

    // ============================================================
    // GET /api/company/employee/notes
    // filter: type, is_read, start, end, month, year, search, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureEmployee();

        $query = $this->myQuery()->with('creator:' . self::USER_COLS);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('is_read')) {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }

        // Sebelumnya whereBetween('created_at', [start, end]) → catatan di tanggal "end"
        // setelah jam 00:00 tidak ikut. Sekarang end mencakup seharian penuh.
        if ($request->filled('start')) {
            $query->where('created_at', '>=', Carbon::parse($request->start)->startOfDay());
        }
        if ($request->filled('end')) {
            $query->where('created_at', '<=', Carbon::parse($request->end)->endOfDay());
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at', $request->year);
        }

        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('note', 'like', "%{$keyword}%");
            });
        }

        $notes = $query->orderByDesc('created_at')
            ->paginate((int) $request->get('per_page', 15));

        $notes->getCollection()->transform(fn($n) => $n->toApiArray());

        return response()->json([
            'status'       => true,
            'message'      => 'Berhasil mengambil data catatan',
            'unread_count' => $this->myQuery()->where('is_read', false)->count(),
            'data'         => $notes,
        ]);
    }

    // ============================================================
    // GET /api/company/employee/notes/summary?month=&year=
    // ============================================================
    public function summary(Request $request)
    {
        $this->ensureEmployee();

        $query = $this->myQuery();

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at', $request->year);
        }

        // Pakai tanda kutip tunggal: kutip ganda bisa error kalau MySQL pakai mode ANSI_QUOTES
        $row = $query->selectRaw("
            COUNT(*)                                                 as total_notes,
            SUM(CASE WHEN type = 'warning'     THEN 1 ELSE 0 END)   as total_warning,
            SUM(CASE WHEN type = 'praise'      THEN 1 ELSE 0 END)   as total_praise,
            SUM(CASE WHEN type = 'performance' THEN 1 ELSE 0 END)   as total_performance,
            SUM(CASE WHEN type = 'absence'     THEN 1 ELSE 0 END)   as total_absence,
            SUM(CASE WHEN type = 'general'     THEN 1 ELSE 0 END)   as total_general,
            SUM(CASE WHEN is_read = 0          THEN 1 ELSE 0 END)   as total_unread
        ")->first();

        return response()->json([
            'status' => true,
            'data'   => [
                'total_notes'       => (int) ($row->total_notes ?? 0),
                'total_warning'     => (int) ($row->total_warning ?? 0),
                'total_praise'      => (int) ($row->total_praise ?? 0),
                'total_performance' => (int) ($row->total_performance ?? 0),
                'total_absence'     => (int) ($row->total_absence ?? 0),
                'total_general'     => (int) ($row->total_general ?? 0),
                'total_unread'      => (int) ($row->total_unread ?? 0),
            ],
        ]);
    }

    // ============================================================
    // GET /api/company/employee/notes/{id}
    // Otomatis ditandai sudah dibaca saat dibuka
    // ============================================================
    public function show($id)
    {
        $this->ensureEmployee();

        $note = $this->myQuery()->with('creator:' . self::USER_COLS)->findOrFail($id);

        if (!$note->is_read) {
            $note->update(['is_read' => true, 'read_at' => now()]);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Detail catatan',
            'data'    => $note->toApiArray(),
        ]);
    }

    // ============================================================
    // PATCH /api/company/employee/notes/{id}/read
    // ============================================================
    public function markRead($id)
    {
        $this->ensureEmployee();

        $note = $this->myQuery()->findOrFail($id);

        if (!$note->is_read) {
            $note->update(['is_read' => true, 'read_at' => now()]);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Catatan ditandai sudah dibaca',
            'data'    => $note->fresh('creator:' . self::USER_COLS)->toApiArray(),
        ]);
    }

    // ============================================================
    // PATCH /api/company/employee/notes/read-all   (BARU)
    // Tandai semua catatan sudah dibaca
    // ============================================================
    public function markAllRead()
    {
        $this->ensureEmployee();

        $count = $this->myQuery()
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'status'  => true,
            'message' => $count . ' catatan ditandai sudah dibaca',
            'data'    => ['updated' => $count],
        ]);
    }
}
