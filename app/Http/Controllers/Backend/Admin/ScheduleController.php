<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = Schedule::with('user')->latest()->paginate(20);
        return view('pages.admin.schedules.index', compact('schedules'));
    }

    public function create()
    {
        $users = User::where('role', 'user')->get();
        return view('pages.admin.schedules.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'title'           => 'required|string',
            'description'     => 'nullable|string',
            'start_datetime'  => 'required|date_format:Y-m-d\TH:i',
            'reminder_offsets' => 'nullable|string',
            'location'        => 'nullable|string',
            'is_task_duty'    => 'nullable|in:on,1,0',
            'status'          => 'required|in:upcoming,done,canceled',
        ]);

        // reminder offsets
        $reminderOffsets = null;
        if (!empty($request->reminder_offsets)) {
            $reminderOffsets = array_map(
                'intval',
                array_filter(explode(',', $request->reminder_offsets), 'is_numeric')
            );
        }

        // location JSON
        // $location = null;
        // if (!empty($request->location)) {
        //     $location = json_decode($request->location, true);

        //     if (json_last_error() !== JSON_ERROR_NONE) {
        //         return back()->withErrors(['location' => 'Format JSON tidak valid']);
        //     }
        // }


        ///code 2
        $location = null;

        if (!empty($request->location)) {
            $jsonString = trim($request->location); // 🔥 FIX penting

            $location = json_decode($jsonString, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors([
                    'location' => 'Format JSON harus seperti: {"lat": -7.12, "lng": 110.22}'
                ]);
            }
        }

        Log::info('STORE SCHEDULE', $request->all());

        Schedule::create([
            'user_id'         => $request->user_id,
            'title'           => $request->title,
            'description'     => $request->description,
            'start_datetime'  => $request->start_datetime,
            'reminder_offsets' => $reminderOffsets,
            'location'        => $location,
            'is_task_duty'    => $request->has('is_task_duty') ? 1 : 0,
            'status'          => $request->status,
        ]);

        return redirect()->route('admin.schedules.index')
            ->with('success', 'Schedule berhasil ditambahkan');
    }
    public function edit($id)
    {
        $schedule = Schedule::findOrFail($id);
        $users = User::where('role', 'user')->get();

        return view('pages.admin.schedules.edit', compact('schedule', 'users'));
    }

    public function update(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        $validated = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'title'           => 'required|string',
            'description'     => 'nullable|string',
            'start_datetime'  => 'required|date_format:Y-m-d\TH:i',
            'reminder_offsets' => 'nullable|string',
            'location'        => 'nullable|string',
            'is_task_duty'    => 'nullable|boolean',
            'status'          => 'required|in:upcoming,done,canceled',
        ]);

        $reminderOffsets = null;
        if (!empty($request->reminder_offsets)) {
            $reminderOffsets = array_map(
                'intval',
                array_filter(explode(',', $request->reminder_offsets), 'is_numeric')
            );
        }

        $location = null;
        if (!empty($request->location)) {
            $location = json_decode($request->location, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['location' => 'Format JSON tidak valid']);
            }
        }

        Log::info('UPDATE SCHEDULE', $request->all());

        $schedule->update([
            'user_id'         => $request->user_id,
            'title'           => $request->title,
            'description'     => $request->description,
            'start_datetime'  => $request->start_datetime,
            'reminder_offsets' => $reminderOffsets,
            'location'        => $location,
            'is_task_duty'    => $request->has('is_task_duty') ? 1 : 0,
            'status'          => $request->status,
        ]);

        return redirect()->route('admin.schedules.index')
            ->with('success', 'Schedule berhasil diupdate');
    }
    public function destroy($id)
    {
        Schedule::findOrFail($id)->delete();
        return redirect()->route('admin.schedules.index')->with('success', 'Schedule deleted successfully!');
    }
}
