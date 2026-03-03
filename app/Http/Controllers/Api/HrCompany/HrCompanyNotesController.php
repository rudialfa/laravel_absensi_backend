<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class HrCompanyNotesController extends Controller
{
    // ─── HELPER: Upload image ke public/image/notes ───────────────────────────
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

    // ─── HELPER: Hapus image lama dari public ─────────────────────────────────
    private function deleteImage(?string $imagePath): void
    {
        if ($imagePath && File::exists(public_path($imagePath))) {
            File::delete(public_path($imagePath));
        }
    }

    // ─── GET /hr/notes ────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Note::with([
            'user:id,name,position,department,image_url',
            'creator:id,name',
        ])
            ->where('company_id', Auth::user()->company_id);

        // Filter per karyawan
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter tipe: warning | praise | performance | absence | general
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter status baca
        if ($request->filled('is_read')) {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter per departemen
        if ($request->filled('department')) {
            $query->whereHas('user', fn($q) => $q->where('department', $request->department));
        }

        // Filter range tanggal (mingguan / custom)
        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('created_at', [$request->start, $request->end]);
        }

        // Filter bulanan
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at', $request->year);
        }

        // Search keyword di title atau isi catatan
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

    // ─── GET /hr/notes/summary ────────────────────────────────────────────────
    public function summary(Request $request)
    {
        $summary = Note::where('company_id', Auth::user()->company_id)
            ->when($request->filled('month') && $request->filled('year'), function ($q) use ($request) {
                $q->whereMonth('created_at', $request->month)
                    ->whereYear('created_at', $request->year);
            })
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

    // ─── GET /hr/notes/{id} ───────────────────────────────────────────────────
    public function show($id)
    {
        $note = Note::with([
            'user:id,name,position,department,image_url',
            'creator:id,name',
        ])
            ->where('company_id', Auth::user()->company_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data'   => $note,
        ]);
    }

    // ─── POST /hr/notes ───────────────────────────────────────────────────────
    public function store(Request $request)
    {
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
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
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
            'message' => 'Catatan berhasil dibuat',
            'data'    => $note->load(['user:id,name', 'creator:id,name']),
        ], 201);
    }

    // ─── POST /hr/notes/{id} ──────────────────────────────────────────────────
    // Pakai POST bukan PUT/PATCH karena ada kemungkinan file upload
    public function update(Request $request, $id)
    {
        $note = Note::where('company_id', Auth::user()->company_id)
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
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('image_url')) {
            $this->deleteImage($note->image_url);
            $data['image_url'] = $this->uploadImage($request->file('image_url'));
        }

        $note->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Catatan berhasil diupdate',
            'data'    => $note->fresh(['user:id,name', 'creator:id,name']),
        ]);
    }

    // ─── DELETE /hr/notes/{id} ────────────────────────────────────────────────
    public function destroy($id)
    {
        $note = Note::where('company_id', Auth::user()->company_id)
            ->findOrFail($id);

        $this->deleteImage($note->image_url);
        $note->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Catatan berhasil dihapus',
        ]);
    }
}
