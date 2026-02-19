<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shift;

class HrCompanyShiftController extends Controller
{

    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;
        if (!$companyId) {
            abort(response()->json(['status' => false, 'message' => 'Company ID tidak ditemukan'], 422));
        }
        return (int) $companyId;
    }

    public function index()
    {
        $this->ensureHr();

        $data = Shift::query()
            ->where('company_id', $this->companyId())
            ->orderByDesc('is_default')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'List shifts',
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureHr();

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'grace_period_minutes' => 'nullable|integer|min:0|max:180',
            'is_default' => 'nullable|boolean',
        ]);

        $shift = Shift::create([
            'company_id' => $this->companyId(),
            'name' => $validated['name'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'grace_period_minutes' => $validated['grace_period_minutes'] ?? 15,
            'is_default' => (bool)($validated['is_default'] ?? false),
        ]);

        // jika dibuat default, nonaktifkan default lain
        if ($shift->is_default) {
            Shift::where('company_id', $this->companyId())
                ->where('id', '!=', $shift->id)
                ->update(['is_default' => false]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shift dibuat',
            'data' => $shift,
        ], 201);
    }

    public function show($id)
    {
        $this->ensureHr();

        $shift = Shift::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail shift',
            'data' => $shift,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->ensureHr();

        $shift = Shift::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:80',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i',
            'grace_period_minutes' => 'sometimes|nullable|integer|min:0|max:180',
            'is_default' => 'sometimes|nullable|boolean',
        ]);

        $shift->fill($validated);
        $shift->save();

        if (($validated['is_default'] ?? null) === true) {
            Shift::where('company_id', $this->companyId())
                ->where('id', '!=', $shift->id)
                ->update(['is_default' => false]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Shift diupdate',
            'data' => $shift,
        ]);
    }

    public function destroy($id)
    {
        $this->ensureHr();

        $shift = Shift::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $shift->delete();

        return response()->json([
            'status' => true,
            'message' => 'Shift dihapus',
        ]);
    }

    public function setDefault($id)
    {
        $this->ensureHr();

        $shift = Shift::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        Shift::where('company_id', $this->companyId())->update(['is_default' => false]);

        $shift->is_default = true;
        $shift->save();

        return response()->json([
            'status' => true,
            'message' => 'Shift dijadikan default',
            'data' => $shift,
        ]);
    }
}
