<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeNotesController extends Controller
{
    // ─── GET /employee/notes ──────────────────────────────────────────────────
    // Lihat semua catatan yang ditujukan ke dirinya sendiri
    public function index(Request $request)
    {
        $query = Note::with('creator:id,name')
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id()); // hanya milik sendiri

        // Filter tipe: warning | praise | performance | absence | general
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter status baca
        if ($request->filled('is_read')) {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter range tanggal
        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('created_at', [$request->start, $request->end]);
        }

        // Filter bulanan
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at', $request->year);
        }

        // Search keyword
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('note', 'like', "%{$keyword}%");
            });
        }

        $notes = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data catatan',
            'data'    => $notes,
        ]);
    }

    // ─── GET /employee/notes/summary ──────────────────────────────────────────
    // Ringkasan catatan milik sendiri
    public function summary(Request $request)
    {
        $query = Note::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id());

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at', $request->year);
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

    // ─── GET /employee/notes/{id} ─────────────────────────────────────────────
    // Detail satu catatan — otomatis mark as read
    public function show($id)
    {
        $note = Note::with('creator:id,name')
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id()) // hanya milik sendiri
            ->findOrFail($id);

        // Auto mark as read saat dibuka
        if (!$note->is_read) {
            $note->update(['is_read' => true]);
        }

        return response()->json([
            'status' => true,
            'data'   => $note,
        ]);
    }

    // ─── PATCH /employee/notes/{id}/read ──────────────────────────────────────
    // Manual mark as read
    public function markRead($id)
    {
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
