<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class PesantrenNotesController extends Controller
{
    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function ensureUstadz(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'ustadz') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Ustadz)',
            ], 403));
        }
    }

    private function uploadImage($file): string
    {
        $destinationPath = public_path('image/notes');
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($destinationPath, $fileName);
        return 'image/notes/' . $fileName;
    }

    private function deleteImage(?string $imagePath): void
    {
        if ($imagePath && File::exists(public_path($imagePath))) {
            File::delete(public_path($imagePath));
        }
    }

    private function bulanLabel(int $month): string
    {
        return [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ][$month] ?? (string) $month;
    }

    // ============================================================
    // INDEX — GET /api/pesantren/notes/santri
    // Sejajar: HrCompanyNotesController::index()
    // Query: user_id (santri_id), type, is_read, department (kelas),
    //        start, end, month, year, search, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureUstadz();

        $query = Note::with([
            'user:id,name,position,department,image_url',
            'creator:id,name',
        ])
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'));

        if ($request->filled('user_id'))    $query->where('user_id', $request->user_id);
        if ($request->filled('type'))       $query->where('type', $request->type);
        if ($request->filled('is_read')) {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('department')) {
            $query->whereHas('user', fn($q) => $q->where('department', $request->department));
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
            'message' => 'Berhasil mengambil data catatan santri',
            'data'    => $query->orderByDesc('created_at')
                ->paginate((int) $request->get('per_page', 15)),
        ]);
    }

    // ============================================================
    // SUMMARY — GET /api/pesantren/notes/santri/summary
    // Sejajar: HrCompanyNotesController::summary()
    // ============================================================
    public function summary(Request $request)
    {
        $this->ensureUstadz();

        $summary = Note::where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->when(
                $request->filled('month') && $request->filled('year'),
                fn($q) => $q->whereMonth('created_at', $request->month)
                    ->whereYear('created_at',  $request->year)
            )
            ->selectRaw('
                user_id,
                COUNT(*)                                                   as total_notes,
                SUM(CASE WHEN type = "warning"     THEN 1 ELSE 0 END)     as total_warning,
                SUM(CASE WHEN type = "praise"      THEN 1 ELSE 0 END)     as total_praise,
                SUM(CASE WHEN type = "performance" THEN 1 ELSE 0 END)     as total_performance,
                SUM(CASE WHEN type = "absence"     THEN 1 ELSE 0 END)     as total_absence,
                SUM(CASE WHEN is_read = 0          THEN 1 ELSE 0 END)     as total_unread
            ')
            ->groupBy('user_id')
            ->with('user:id,name,department,position,image_url')
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $summary,
        ]);
    }

    // ============================================================
    // SHOW — GET /api/pesantren/notes/santri/{id}
    // Sejajar: HrCompanyNotesController::show()
    // ============================================================
    public function show(int $id)
    {
        $this->ensureUstadz();

        $note = Note::with([
            'user:id,name,position,department,image_url',
            'creator:id,name',
        ])
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data'   => $note,
        ]);
    }

    // ============================================================
    // STORE — POST /api/pesantren/notes/santri
    // Sejajar: HrCompanyNotesController::store()
    // ============================================================
    public function store(Request $request)
    {
        $this->ensureUstadz();

        $validator = Validator::make($request->all(), [
            'user_id'            => 'required|exists:users,id',
            'title'              => 'required|string|max:255',
            'note'               => 'required|string',
            'type'               => 'required|in:warning,praise,performance,absence,general',
            'image_url'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'target_achievement' => 'nullable|string',
            'reason'             => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data               = $validator->validated();
        $data['company_id'] = Auth::user()->company_id;
        $data['created_by'] = Auth::id();

        if ($request->hasFile('image_url')) {
            $data['image_url'] = $this->uploadImage($request->file('image_url'));
        }

        $note = Note::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Catatan santri berhasil dibuat',
            'data'    => $note->load(['user:id,name', 'creator:id,name']),
        ], 201);
    }

    // ============================================================
    // UPDATE — POST /api/pesantren/notes/santri/{id}
    // Sejajar: HrCompanyNotesController::update()
    // ============================================================
    public function update(Request $request, int $id)
    {
        $this->ensureUstadz();

        $note = Note::where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title'              => 'sometimes|string|max:255',
            'note'               => 'sometimes|string',
            'type'               => 'sometimes|in:warning,praise,performance,absence,general',
            'image_url'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'target_achievement' => 'nullable|string',
            'reason'             => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('image_url')) {
            $this->deleteImage($note->image_url);
            $data['image_url'] = $this->uploadImage($request->file('image_url'));
        }

        $note->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Catatan santri berhasil diupdate',
            'data'    => $note->fresh(['user:id,name', 'creator:id,name']),
        ]);
    }

    // ============================================================
    // DESTROY — DELETE /api/pesantren/notes/santri/{id}
    // Sejajar: HrCompanyNotesController::destroy()
    // ============================================================
    public function destroy(int $id)
    {
        $this->ensureUstadz();

        $note = Note::where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->findOrFail($id);

        $this->deleteImage($note->image_url);
        $note->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Catatan santri berhasil dihapus',
        ]);
    }

    // ============================================================
    // EXPORT — GET /api/pesantren/notes/santri/export
    // Sejajar: HrCompanyNotesController::export()
    // Query: type, user_id, month, year
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureUstadz();

        $validator = Validator::make($request->all(), [
            'month' => 'nullable|integer|between:1,12',
            'year'  => 'nullable|integer|min:2020|max:2099',
            'type'  => 'nullable|in:warning,praise,performance,absence,general',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Note::with([
            'user:id,name,position,department',
            'creator:id,name',
        ])
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'));

        if ($request->filled('type'))    $query->where('type', $request->type);
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at',  $request->year);
        }

        $notes = $query->orderByDesc('created_at')->get();

        $stats = [
            'total'       => $notes->count(),
            'warning'     => $notes->where('type', 'warning')->count(),
            'praise'      => $notes->where('type', 'praise')->count(),
            'performance' => $notes->where('type', 'performance')->count(),
            'absence'     => $notes->where('type', 'absence')->count(),
            'general'     => $notes->where('type', 'general')->count(),
            'unread'      => $notes->where('is_read', false)->count(),
        ];

        $month       = $request->month;
        $year        = $request->year ?? now()->year;
        $periodLabel = $month
            ? $this->bulanLabel((int) $month) . ' ' . $year
            : 'Semua Periode';
        $typeLabel   = $request->filled('type') ? ' - ' . ucfirst($request->type) : '';
        $fileName    = 'catatan-santri-' . now()->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pesantren_notes', [
            'company'     => Auth::user()->company ?? (object)['name' => ''],
            'periodLabel' => $periodLabel . $typeLabel,
            'notes'       => $notes,
            'stats'       => $stats,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
