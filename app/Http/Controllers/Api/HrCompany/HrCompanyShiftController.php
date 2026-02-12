<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shift;

class HrCompanyShiftController extends Controller
{
    private function ensureHr()
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    public function index()
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $data = Shift::where('company_id', $companyId)->orderByDesc('id')->get();

        return response()->json(['status' => true, 'message' => 'List shifts', 'data' => $data]);
    }

    public function store(Request $request)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'is_default' => 'nullable|boolean',
        ]);

        $shift = Shift::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'is_default' => (bool)($validated['is_default'] ?? false),
        ]);

        if ($shift->is_default) {
            Shift::where('company_id', $companyId)->where('id', '!=', $shift->id)->update(['is_default' => false]);
        }

        return response()->json(['status' => true, 'message' => 'Shift dibuat', 'data' => $shift], 201);
    }

    public function show($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $shift = Shift::where('company_id', $companyId)->findOrFail($id);

        return response()->json(['status' => true, 'message' => 'Detail shift', 'data' => $shift]);
    }

    public function update(Request $request, $id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $shift = Shift::where('company_id', $companyId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i',
            'is_default' => 'nullable|boolean',
        ]);

        foreach (['name', 'start_time', 'end_time'] as $f) {
            if (array_key_exists($f, $validated)) $shift->$f = $validated[$f];
        }
        if (array_key_exists('is_default', $validated)) $shift->is_default = (bool)$validated['is_default'];

        $shift->save();

        if ($shift->is_default) {
            Shift::where('company_id', $companyId)->where('id', '!=', $shift->id)->update(['is_default' => false]);
        }

        return response()->json(['status' => true, 'message' => 'Shift diupdate', 'data' => $shift]);
    }

    public function destroy($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $shift = Shift::where('company_id', $companyId)->findOrFail($id);
        $shift->delete();

        return response()->json(['status' => true, 'message' => 'Shift dihapus']);
    }

    public function setDefault($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $shift = Shift::where('company_id', $companyId)->findOrFail($id);

        Shift::where('company_id', $companyId)->update(['is_default' => false]);
        $shift->is_default = true;
        $shift->save();

        return response()->json(['status' => true, 'message' => 'Shift default diset', 'data' => $shift]);
    }
}
