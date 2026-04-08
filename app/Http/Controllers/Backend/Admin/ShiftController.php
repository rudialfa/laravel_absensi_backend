<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Shift;
use App\Models\Company;

class ShiftController extends Controller
{
     public function index()
    {
        $shifts = Shift::with('company')->orderBy('id', 'DESC')->get();
        return view('pages.admin.shifts.index', compact('shifts'));
    }

    public function create()
    {
        $companies = Company::all();
        return view('pages.admin.shifts.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'grace_period_minutes' => 'required|integer|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        Shift::create([
            'company_id' => $request->company_id,
            'name' => $request->name,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'grace_period_minutes' => $request->grace_period_minutes,
            'is_default' => $request->is_default ? 1 : 0,
        ]);

        return redirect()->route('admin.shifts.index')->with('success', 'Shift berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $shift = Shift::findOrFail($id);
        $companies = Company::all();
        return view('pages.admin.shifts.edit', compact('shift', 'companies'));
    }

    public function update(Request $request, $id)
    {
        $shift = Shift::findOrFail($id);

        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'grace_period_minutes' => 'required|integer|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        $shift->update([
            'company_id' => $request->company_id,
            'name' => $request->name,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'grace_period_minutes' => $request->grace_period_minutes,
            'is_default' => $request->is_default ? 1 : 0,
        ]);

        return redirect()->route('admin.shifts.index')->with('success', 'Shift berhasil diperbarui.');
    }

    public function destroy($id)
    {
        Shift::findOrFail($id)->delete();
        return redirect()->route('admin.shifts.index')->with('success', 'Shift berhasil dihapus.');
    }
}
