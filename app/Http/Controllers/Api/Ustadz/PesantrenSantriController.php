<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PesantrenSantriController extends Controller
{
    public function index()
    {
        return User::where('role', 'santri')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'position' => 'required',    // KELAS
            'department' => 'required',  // KAMAR
        ]);

        $santri = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'position' => $request->position,
            'department' => $request->department,
            'role' => 'santri',
            'company_id' => auth()->user()->company_id,
        ]);

        return response()->json([
            'status' => true,
            'data' => $santri
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $santri = User::where('id', $id)
            ->where('role', 'santri')
            ->firstOrFail();

        $santri->update([
            'name' => $request->name,
            'position' => $request->position,
            'department' => $request->department,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Santri updated'
        ]);
    }

    // delete santri
    public function destroy($id)
    {
        $santri = User::where('id', $id)
            ->where('role', 'santri')
            ->firstOrFail();

        $santri->delete();

        return response()->json([
            'status' => true,
            'message' => 'Santri deleted'
        ]);
    }
}
