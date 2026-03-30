<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SantriNotesController extends Controller
{
    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function ensureSantri(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Santri)',
            ], 403));
        }
    }

    // ============================================================
    // INDEX — GET /api/pesantren/santri/notes
    // Sejajar: EmployeeNotesController::index()
    // Query: type, is_read, start, end, month, year, search, per_page
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $query = Note::with('creator:id,name')
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id());

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('is_read')) {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('created_at', [$request->start, $request->end]);
        }
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at',  $request->year);
        }
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('note',  'like', "%{$keyword}%");
            });
        }

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data catatan',
            'data'    => $query->orderByDesc('created_at')
                ->paginate((int) $request->get('per_page', 15)),
        ]);
    }

    // ============================================================
    // SUMMARY — GET /api/pesantren/santri/notes/summary
    // Sejajar: EmployeeNotesController::summary()
    // ============================================================
    public function summary(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $query = Note::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id());

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at',  $request->year);
        }

        $summary = $query->selectRaw('
            COUNT(*)                                                   as total_notes,
            SUM(CASE WHEN type = "warning"     THEN 1 ELSE 0 END)     as total_warning,
            SUM(CASE WHEN type = "praise"      THEN 1 ELSE 0 END)     as total_praise,
            SUM(CASE WHEN type = "performance" THEN 1 ELSE 0 END)     as total_performance,
            SUM(CASE WHEN type = "absence"     THEN 1 ELSE 0 END)     as total_absence,
            SUM(CASE WHEN is_read = 0          THEN 1 ELSE 0 END)     as total_unread
        ')->first();

        return response()->json([
            'status' => true,
            'data'   => $summary,
        ]);
    }

    // ============================================================
    // SHOW — GET /api/pesantren/santri/notes/{id}
    // Sejajar: EmployeeNotesController::show()
    // Auto mark as read saat dibuka
    // ============================================================
    public function show(int $id): JsonResponse
    {
        $this->ensureSantri();

        $note = Note::with('creator:id,name')
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        // Auto mark as read
        if (!$note->is_read) {
            $note->update(['is_read' => true]);
        }

        return response()->json([
            'status' => true,
            'data'   => $note,
        ]);
    }

    // ============================================================
    // MARK READ — PATCH /api/pesantren/santri/notes/{id}/read
    // Sejajar: EmployeeNotesController::markRead()
    // ============================================================
    public function markRead(int $id): JsonResponse
    {
        $this->ensureSantri();

        $note = Note::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $note->update(['is_read' => true]);

        return response()->json([
            'status'  => true,
            'message' => 'Catatan ditandai sudah dibaca',
        ]);
    }
}
