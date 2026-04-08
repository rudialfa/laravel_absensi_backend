<?php

namespace App\Http\Controllers\Backend\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;

class UserScheduleController extends Controller
{
    public function index(Request $request)
    {
        //code tambahan
         $query = Schedule::where('user_id', $this->userId())
            ->where('company_id', $this->companyId());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        ///end code tambahan

        $schedules = Schedule::where('user_id', Auth::id())
            ->orderBy('start_datetime', 'asc')
            ->paginate(10);

        return view('pages.user.schedules.index', compact('schedules'));
    }

    public function create()
    {
        return view('pages.user.schedules.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'           => 'required|string',
            'description'     => 'nullable|string',
            'start_datetime'  => 'required|date',
            'reminder_offsets'=> 'nullable|array',
            'location'        => 'nullable|array',
        ]);

        Schedule::create([
            'user_id'         => Auth::id(),
            'title'           => $request->title,
            'description'     => $request->description,
            'start_datetime'  => $request->start_datetime,
            'reminder_offsets'=> $request->reminder_offsets ? json_encode($request->reminder_offsets) : null,
            'location'        => $request->location ? json_encode($request->location) : null,
            'is_task_duty'    => $request->is_task_duty ? 1 : 0,
        ]);

        return redirect()->route('user.schedules.index')
            ->with('success', 'Jadwal berhasil ditambahkan');
    }

    public function edit($id)
    {
        $schedule = Schedule::where('user_id', Auth::id())->findOrFail($id);

        return view('pages.user.schedules.edit', compact('schedule'));
    }

    public function update(Request $request, $id)
    {
        $schedule = Schedule::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'title'           => 'required|string',
            'description'     => 'nullable|string',
            'start_datetime'  => 'required|date',
            'reminder_offsets'=> 'nullable|array',
            'location'        => 'nullable|array',
            'status'          => 'required|in:upcoming,done,canceled',
        ]);

        $schedule->update([
            'title'           => $request->title,
            'description'     => $request->description,
            'start_datetime'  => $request->start_datetime,
            'reminder_offsets'=> $request->reminder_offsets ? json_encode($request->reminder_offsets) : null,
            'location'        => $request->location ? json_encode($request->location) : null,
            'is_task_duty'    => $request->is_task_duty ? 1 : 0,
            'status'          => $request->status,
        ]);

        return redirect()->route('user.schedules.index')
            ->with('success', 'Jadwal berhasil diperbarui');
    }

    public function destroy($id)
    {
        $schedule = Schedule::where('user_id', Auth::id())->findOrFail($id);
        $schedule->delete();

        return back()->with('success', 'Jadwal berhasil dihapus');
    }
}
