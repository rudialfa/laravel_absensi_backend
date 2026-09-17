<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\PpdbPeriod;
use Illuminate\Http\Request;

class PpdbPeriodController extends Controller
{
    public function index(Request $request)
    {
        $periods = PpdbPeriod::where('company_id', $request->user()->company_id)
            ->withCount('applicants')
            ->latest()
            ->get();

        return response()->json(['status' => true, 'message' => 'Data periode PPDB berhasil diambil', 'data' => $periods]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'academic_year'       => 'required|string|max:20',
            'registration_start'  => 'required|date',
            'registration_end'    => 'required|date|after_or_equal:registration_start',
            'quota'               => 'nullable|integer|min:1',
        ]);

        $data['company_id'] = $request->user()->company_id;

        // Nonaktifkan periode aktif lain — 1 sekolah cuma boleh 1 periode aktif
        PpdbPeriod::where('company_id', $data['company_id'])->update(['is_active' => false]);
        $data['is_active'] = true;

        $period = PpdbPeriod::create($data);

        return response()->json(['status' => true, 'message' => 'Periode PPDB berhasil dibuat', 'data' => $period], 201);
    }

    public function show(PpdbPeriod $ppdbPeriod)
    {
        $this->authorize('view', $ppdbPeriod);
        return response()->json(['status' => true, 'message' => 'Detail periode berhasil diambil', 'data' => $ppdbPeriod]);
    }

    public function update(Request $request, PpdbPeriod $ppdbPeriod)
    {
        $this->authorize('update', $ppdbPeriod);

        $data = $request->validate([
            'name'                => 'sometimes|string|max:150',
            'registration_start'  => 'sometimes|date',
            'registration_end'    => 'sometimes|date|after_or_equal:registration_start',
            'quota'               => 'nullable|integer|min:1',
            'is_active'           => 'sometimes|boolean',
        ]);

        $ppdbPeriod->update($data);

        return response()->json(['status' => true, 'message' => 'Periode PPDB berhasil diperbarui', 'data' => $ppdbPeriod]);
    }

    public function destroy(PpdbPeriod $ppdbPeriod)
    {
        $this->authorize('update', $ppdbPeriod);
        $ppdbPeriod->delete();
        return response()->json(['status' => true, 'message' => 'Periode PPDB dihapus', 'data' => null]);
    }
}
