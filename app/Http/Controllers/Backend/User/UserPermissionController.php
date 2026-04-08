<?php

namespace App\Http\Controllers\Backend\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;

class UserPermissionController extends Controller
{
      public function index()
    {
        $permissions = Permission::where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('pages.user.permissions.index', compact('permissions'));
    }

    public function create()
    {
        return view('pages.user.permissions.create');
    }

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'date_permission' => 'required|date',
    //         'reason'          => 'required|string',
    //         'image'           => 'nullable|image|max:2048'
    //     ]);

    //     $fileName = null;

    //     if ($request->hasFile('image')) {
    //         $fileName = time() . '-' . uniqid() . '.' . $request->image->extension();
    //         $request->image->move(public_path('image/permission'), $fileName);
    //     }

    //     Permission::create([
    //         'user_id'         => Auth::id(),
    //         'date_permission' => $request->date_permission,
    //         'reason'          => $request->reason,
    //         'image'           => $fileName,
    //     ]);

    //     return redirect()->route('user.permissions.index')
    //         ->with('success', 'Pengajuan izin berhasil dikirim');
    // }


    ///code 2 store
        public function store(Request $request)
    {
        $request->validate([
            'date_permission' => 'required|date',
            'reason' => 'required|string',
            'image' => 'nullable|image|max:2048'
        ]);

        $filePath = null;

        if ($request->hasFile('image')) {
            $filePath = $request->file('image')->store('permissions', 'public');
        }

        Permission::create([
            'user_id' => $this->userId(),
            'company_id' => $this->companyId(),
            'date_permission' => $request->date_permission,
            'reason' => $request->reason,
            'image' => $filePath,
        ]);

        return redirect()->route('user.permissions.index')
            ->with('success', 'Pengajuan izin berhasil dikirim');
    }
    
    public function edit($id)
    {
        $permission = Permission::where('user_id', Auth::id())->findOrFail($id);

        if ($permission->is_approved) {
            return back()->with('error', 'Izin yang sudah disetujui tidak bisa diedit.');
        }

        return view('pages.user.permissions.edit', compact('permission'));
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::where('user_id', Auth::id())->findOrFail($id);

        if ($permission->is_approved) {
            return back()->with('error', 'Izin yang sudah disetujui tidak bisa diedit.');
        }

        $request->validate([
            'date_permission' => 'required|date',
            'reason'          => 'required|string',
            'image'           => 'nullable|image|max:2048'
        ]);

        $fileName = $permission->image;

        if ($request->hasFile('image')) {
            if ($permission->image && file_exists(public_path('image/permission/'.$permission->image))) {
                unlink(public_path('image/permission/'.$permission->image));
            }

            $fileName = time() . '-' . uniqid() . '.' . $request->image->extension();
            $request->image->move(public_path('image/permission'), $fileName);
        }

        $permission->update([
            'date_permission' => $request->date_permission,
            'reason'          => $request->reason,
            'image'           => $fileName,
        ]);

        return redirect()->route('user.permissions.index')
            ->with('success', 'Pengajuan izin berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $permission = Permission::where('user_id', Auth::id())->findOrFail($id);

        if ($permission->is_approved) {
            return back()->with('error', 'Izin yang sudah disetujui tidak bisa dihapus.');
        }

        if ($permission->image && file_exists(public_path('image/permission/'.$permission->image))) {
            unlink(public_path('image/permission/'.$permission->image));
        }

        $permission->delete();

        return back()->with('success', 'Pengajuan izin berhasil dihapus.');
    }
}
