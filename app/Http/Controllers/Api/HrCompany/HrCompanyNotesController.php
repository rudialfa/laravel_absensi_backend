<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HrCompanyNotesController extends Controller
{
    private const USER_COLS = 'id,name,position,department,image_url';

    // ----------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------

    // Sebelumnya tidak ada cek role
    private function ensureHr(): void
    {
        if (!Auth::check() || Auth::user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId(): int
    {
        return (int) Auth::user()->company_id;
    }

    private function relations(): array
    {
        return ['user:' . self::USER_COLS, 'creator:' . self::USER_COLS];
    }

    private function validationError($validator)
    {
        return response()->json([
            'status'  => false,
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422);
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

    private const MESSAGES = [
        'user_id.required' => 'Karyawan wajib dipilih.',
        'user_id.exists'   => 'Karyawan tidak ditemukan di perusahaan ini.',
        'title.required'   => 'Judul catatan wajib diisi.',
        'note.required'    => 'Isi catatan wajib diisi.',
        'type.required'    => 'Jenis catatan wajib dipilih.',
        'image_url.image'  => 'Lampiran harus berupa gambar.',
        'image_url.max'    => 'Gambar maksimal 2 MB.',
    ];

    // ============================================================
    // GET /api/company/hr/notes
    // filter: user_id, type, is_read, department, start, end, month, year, search, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureHr();

        $query = Note::with($this->relations())->where('company_id', $this->companyId());

        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('type'))    $query->where('type', $request->type);
        if ($request->filled('is_read')) {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('department')) {
            $query->whereHas('user', fn($q) => $q->where('department', $request->department));
        }
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
                    ->orWhere('note', 'like', "%{$keyword}%")
                    ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$keyword}%"));
            });
        }

        $notes = $query->orderByDesc('created_at')->paginate((int) $request->get('per_page', 15));
        $notes->getCollection()->transform(fn($n) => $n->toApiArray());

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data catatan',
            'data'    => $notes,
        ]);
    }

    // ============================================================
    // GET /api/company/hr/notes/summary?month=&year=
    // Ringkasan per karyawan
    // ============================================================
    public function summary(Request $request)
    {
        $this->ensureHr();

        $rows = Note::where('company_id', $this->companyId())
            ->when($request->filled('month') && $request->filled('year'), function ($q) use ($request) {
                $q->whereMonth('created_at', $request->month)
                    ->whereYear('created_at', $request->year);
            })
            ->selectRaw("
                user_id,
                COUNT(*)                                                 as total_notes,
                SUM(CASE WHEN type = 'warning'     THEN 1 ELSE 0 END)   as total_warning,
                SUM(CASE WHEN type = 'praise'      THEN 1 ELSE 0 END)   as total_praise,
                SUM(CASE WHEN type = 'performance' THEN 1 ELSE 0 END)   as total_performance,
                SUM(CASE WHEN type = 'absence'     THEN 1 ELSE 0 END)   as total_absence,
                SUM(CASE WHEN type = 'general'     THEN 1 ELSE 0 END)   as total_general,
                SUM(CASE WHEN is_read = 0          THEN 1 ELSE 0 END)   as total_unread
            ")
            ->groupBy('user_id')
            ->with('user:' . self::USER_COLS)
            ->get()
            ->map(fn($r) => [
                'user_id'           => (int) $r->user_id,
                'user'              => $r->user ? [
                    'id'         => $r->user->id,
                    'name'       => $r->user->name,
                    'position'   => $r->user->position,
                    'department' => $r->user->department,
                    'image_url'  => $r->user->image_url ? asset($r->user->image_url) : null,
                ] : null,
                'total_notes'       => (int) $r->total_notes,
                'total_warning'     => (int) $r->total_warning,
                'total_praise'      => (int) $r->total_praise,
                'total_performance' => (int) $r->total_performance,
                'total_absence'     => (int) $r->total_absence,
                'total_general'     => (int) $r->total_general,
                'total_unread'      => (int) $r->total_unread,
            ])
            ->sortByDesc('total_notes')
            ->values();

        return response()->json([
            'status' => true,
            'data'   => $rows,
        ]);
    }

    // ============================================================
    // GET /api/company/hr/notes/{id}
    // ============================================================
    public function show($id)
    {
        $this->ensureHr();

        $note = Note::with($this->relations())
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Detail catatan',
            'data'    => $note->toApiArray(),
        ]);
    }

    // ============================================================
    // POST /api/company/hr/notes   (multipart)
    // field: user_id, title, note, type, image_url (file, opsional),
    //        target_achievement, reason
    // ============================================================
    public function store(Request $request)
    {
        $this->ensureHr();

        $validator = Validator::make($request->all(), [
            // Sebelumnya exists:users,id → bisa kirim catatan ke karyawan COMPANY LAIN
            'user_id'            => [
                'required',
                Rule::exists('users', 'id')
                    ->where('company_id', $this->companyId())
                    ->where('role', 'employee'),
            ],
            'title'              => 'required|string|max:255',
            'note'               => 'required|string|max:5000',
            'type'               => ['required', Rule::in(Note::TYPES)],
            'image_url'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'target_achievement' => 'nullable|string|max:2000',
            'reason'             => 'nullable|string|max:2000',
        ], self::MESSAGES);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = collect($validator->validated())->except('image_url')->all();
        $data['company_id'] = $this->companyId();
        $data['created_by'] = Auth::id();
        $data['is_read']    = false;

        if ($request->hasFile('image_url')) {
            $data['image_url'] = $this->uploadImage($request->file('image_url'));
        }

        try {
            $note = Note::create($data);
        } catch (\Throwable $e) {
            $this->deleteImage($data['image_url'] ?? null);
            throw $e;
        }

        return response()->json([
            'status'  => true,
            'message' => 'Catatan berhasil dibuat',
            'data'    => $note->load($this->relations())->toApiArray(),
        ], 201);
    }

    // ============================================================
    // POST /api/company/hr/notes/{id}   (multipart)
    // field: title, note, type, image_url (file), remove_image (1),
    //        target_achievement, reason
    // ============================================================
    public function update(Request $request, $id)
    {
        $this->ensureHr();

        $note = Note::where('company_id', $this->companyId())->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title'              => 'sometimes|required|string|max:255',
            'note'               => 'sometimes|required|string|max:5000',
            'type'               => ['sometimes', 'required', Rule::in(Note::TYPES)],
            'image_url'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'remove_image'       => 'nullable|boolean',
            'target_achievement' => 'nullable|string|max:2000',
            'reason'             => 'nullable|string|max:2000',
        ], self::MESSAGES);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        // Sebelumnya: image_url tanpa file ikut masuk validated() sebagai null
        // → gambar lama terhapus dari database tapi file-nya tertinggal.
        $data = collect($validator->validated())->except(['image_url', 'remove_image'])->all();

        if ($request->hasFile('image_url')) {
            $this->deleteImage($note->image_url);
            $data['image_url'] = $this->uploadImage($request->file('image_url'));
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($note->image_url);
            $data['image_url'] = null;
        }

        // Isi berubah → tandai belum dibaca lagi supaya karyawan tahu ada perubahan
        if (array_intersect_key($data, array_flip(['title', 'note', 'type']))) {
            $data['is_read'] = false;
            $data['read_at'] = null;
        }

        $note->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Catatan berhasil diupdate',
            'data'    => $note->fresh($this->relations())->toApiArray(),
        ]);
    }

    // ============================================================
    // DELETE /api/company/hr/notes/{id}
    // ============================================================
    public function destroy($id)
    {
        $this->ensureHr();

        $note = Note::where('company_id', $this->companyId())->findOrFail($id);

        $this->deleteImage($note->image_url);
        $note->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Catatan berhasil dihapus',
        ]);
    }

    // ============================================================
    // EXPORT PDF
    // GET /api/company/hr/notes/export?type=&user_id=&month=&year=
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureHr();

        $validator = Validator::make($request->all(), [
            'month' => 'nullable|integer|between:1,12',
            'year'  => 'nullable|integer|min:2020|max:2099',
            'type'  => ['nullable', Rule::in(Note::TYPES)],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $query = Note::with(['user:id,name,position,department', 'creator:id,name'])
            ->where('company_id', $this->companyId());

        if ($request->filled('type'))    $query->where('type', $request->type);
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('created_at', $request->month)
                ->whereYear('created_at', $request->year);
        } elseif ($request->filled('year')) {
            $query->whereYear('created_at', $request->year);
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

        $month       = $request->month ? (int) $request->month : null;
        $year        = $request->year ?? now()->year;
        $periodLabel = $month
            ? (Note::MONTH_NAMES[$month] ?? $month) . ' ' . $year
            : ($request->filled('year') ? 'Tahun ' . $year : 'Semua Periode');

        $typeLabel = $request->filled('type')
            ? ' - ' . (Note::TYPE_LABELS[$request->type] ?? $request->type)
            : '';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_notes', [
            'company'     => Auth::user()->company ?? (object) ['name' => ''],
            'periodLabel' => $periodLabel . $typeLabel,
            'notes'       => $notes,
            'stats'       => $stats,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download('notes-' . now()->format('Y-m-d') . '.pdf');
    }
}
