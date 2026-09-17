<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillType;
use Illuminate\Http\Request;

class BillTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = BillType::where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->get();

        return response()->json(['status' => true, 'message' => 'Data jenis tagihan berhasil diambil', 'data' => $types]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'periode'         => 'required|in:bulanan,sekali',
            'default_amount'  => 'required|numeric|min:0',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $type = BillType::create($data);

        return response()->json(['status' => true, 'message' => 'Jenis tagihan berhasil dibuat', 'data' => $type], 201);
    }

    public function show(BillType $billType)
    {
        $this->authorize('view', $billType);
        return response()->json(['status' => true, 'message' => 'Detail jenis tagihan berhasil diambil', 'data' => $billType]);
    }

    public function update(Request $request, BillType $billType)
    {
        $this->authorize('update', $billType);

        $data = $request->validate([
            'name'           => 'sometimes|string|max:100',
            'periode'        => 'sometimes|in:bulanan,sekali',
            'default_amount' => 'sometimes|numeric|min:0',
            'is_active'      => 'sometimes|boolean',
        ]);

        $billType->update($data);

        return response()->json(['status' => true, 'message' => 'Jenis tagihan berhasil diperbarui', 'data' => $billType]);
    }

    public function destroy(BillType $billType)
    {
        $this->authorize('delete', $billType);
        $billType->delete();
        return response()->json(['status' => true, 'message' => 'Jenis tagihan dihapus', 'data' => null]);
    }
}
