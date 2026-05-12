<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Prayer;

class PrayerController extends Controller
{
    public function index()
    {
        $prayers = Prayer::orderBy('date', 'desc')->paginate(20);
        return view('pages.admin.prayers.index', compact('prayers'));
    }

    public function create()
    {
        return view('pages.admin.prayers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'city' => 'required|string',
            'date' => 'required|date',
            'fajr' => 'nullable|date_format:H:i',
            'dzuhur' => 'nullable|date_format:H:i',
            'ashar' => 'nullable|date_format:H:i',
            'maghrib' => 'nullable|date_format:H:i',
            'isya' => 'nullable|date_format:H:i',
            'source' => 'nullable|string',
        ]);

        Prayer::create($request->all());

        return redirect()->route('admin.prayers.index')->with('success', 'Prayer schedule added!');
    }

    public function edit($id)
    {
        $prayer = Prayer::findOrFail($id);
        return view('pages.admin.prayers.edit', compact('prayer'));
    }

    public function update(Request $request, $id)
    {
        $prayer = Prayer::findOrFail($id);
        
        $request->validate([
            'city' => 'required|string',
            'date' => 'required|date',
            'fajr' => 'nullable|date_format:H:i',
            'dzuhur' => 'nullable|date_format:H:i',
            'ashar' => 'nullable|date_format:H:i',
            'maghrib' => 'nullable|date_format:H:i',
            'isya' => 'nullable|date_format:H:i',
            'source' => 'nullable|string',
        ]);

        $prayer->update($request->all());

        return redirect()->route('admin.prayers.index')->with('success', 'Prayer schedule updated!');
    }

    public function destroy($id)
    {
        Prayer::findOrFail($id)->delete();
        return redirect()->route('admin.prayers.index')->with('success', 'Prayer schedule deleted!');
    }
}
