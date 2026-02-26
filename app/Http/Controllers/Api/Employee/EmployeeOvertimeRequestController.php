<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class EmployeeOvertimeRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $q = OvertimeRequest::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->with(['approver:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $q->whereDate('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('date', '<=', $request->to);
        }

        return response()->json([
            'status' => true,
            'message' => 'List overtime requests',
            'data' => $q->paginate((int)($request->get('per_page', 10))),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $data = OvertimeRequest::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->with(['attendance', 'approver:id,name'])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Overtime request detail',
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $v = Validator::make($request->all(), [
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string'],
            'attendance_id' => ['nullable', 'integer', 'exists:attendances,id'],

            // ✅ DARI string -> file image
            'evidence_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status' => false,
                'message' => $v->errors()->first(),
                'errors' => $v->errors(),
            ], 422);
        }

        // Hitung minutes dari start-end, jika end < start anggap lewat tengah malam
        $start = Carbon::createFromFormat('H:i', $request->start_time);
        $end = Carbon::createFromFormat('H:i', $request->end_time);

        if ($end->lessThan($start)) {
            $end->addDay();
        }

        $minutes = $start->diffInMinutes($end);

        if ($minutes <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'Durasi lembur tidak valid.',
            ], 422);
        }

        // Guard kalau kamu pakai UNIQUE (company_id,user_id,date)
        $exists = OvertimeRequest::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $request->date)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Kamu sudah punya pengajuan lembur pada tanggal tersebut.',
            ], 409);
        }

        // Jika attendance_id diisi, pastikan milik user & company
        $attendanceId = $request->attendance_id;
        if ($attendanceId) {
            $attendanceOk = Attendance::query()
                ->where('id', $attendanceId)
                ->where('user_id', $user->id)
                ->when(Schema::hasColumn('attendances', 'company_id'), function ($q) use ($user) {
                    $q->where('company_id', $user->company_id);
                })
                ->exists();

            if (!$attendanceOk) {
                return response()->json([
                    'status' => false,
                    'message' => 'attendance_id tidak valid untuk user ini.',
                ], 422);
            }
        }

        // ✅ HANDLE UPLOAD FILE (gunakan key evidence_image, bukan image)
        $imagePath = null;
        if ($request->hasFile('evidence_image')) {
            // folder: public/image/permission
            $destinationPath = public_path('image/permission');

            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $file = $request->file('evidence_image');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $file->move($destinationPath, $fileName);

            $imagePath = 'image/permission/' . $fileName;
        }

        $overtime = OvertimeRequest::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'attendance_id' => $attendanceId,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'minutes' => $minutes,
            'reason' => $request->reason,

            // ✅ simpan path hasil upload
            'evidence_image' => $imagePath,

            'status' => 'pending',
        ]);

        // ✅ optional: jadikan full URL biar Flutter tinggal Image.network
        $overtime->evidence_image = $overtime->evidence_image
            ? asset($overtime->evidence_image)
            : null;

        return response()->json([
            'status' => true,
            'message' => 'Pengajuan lembur berhasil dibuat',
            'data' => $overtime,
        ], 201);
    }

    public function cancel(Request $request, int $id)
    {
        $user = $request->user();

        $overtime = OvertimeRequest::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        if ($overtime->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'Hanya pengajuan lembur status pending yang bisa dibatalkan.',
            ], 422);
        }

        $overtime->update(['status' => 'canceled']);

        return response()->json([
            'status' => true,
            'message' => 'Pengajuan lembur dibatalkan',
            'data' => $overtime,
        ]);
    }
}
