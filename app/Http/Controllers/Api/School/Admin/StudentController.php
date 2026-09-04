<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreStudentRequest;
use App\Http\Requests\School\UpdateStudentRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * GET /api/school/admin/students?class_id=&search=&page=
     */
    public function index(Request $request)
    {
        $students = Student::where('company_id', $request->user()->company_id)
            ->with('classRoom:id,name')
            ->when($request->query('class_id'), fn($q, $classId) => $q->where('class_id', $classId))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%");
                });
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate(20);

        return response()->json([
            'status'  => true,
            'message' => 'Data murid berhasil diambil',
            'data'    => $students,
        ]);
    }

    /**
     * POST /api/school/admin/students
     */
    public function store(StoreStudentRequest $request)
    {
        $data = $request->validated();
        $data['company_id'] = $request->user()->company_id;

        $student = Student::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Murid berhasil ditambahkan',
            'data'    => $student,
        ], 201);
    }

    /**
     * GET /api/school/admin/students/{student}
     */
    public function show(Student $student)
    {
        $this->authorize('view', $student);

        $student->load(['classRoom:id,name', 'guardians:id,name,phone,email']);

        return response()->json([
            'status'  => true,
            'message' => 'Detail murid berhasil diambil',
            'data'    => $student,
        ]);
    }

    /**
     * PUT /api/school/admin/students/{student}
     */
    public function update(UpdateStudentRequest $request, Student $student)
    {
        $this->authorize('update', $student);

        $student->update($request->validated());

        return response()->json([
            'status'  => true,
            'message' => 'Data murid berhasil diperbarui',
            'data'    => $student,
        ]);
    }

    /**
     * DELETE /api/school/admin/students/{student}
     */
    public function destroy(Student $student)
    {
        $this->authorize('delete', $student);

        // Soft delete — histori absen murid ini tetap perlu dipertahankan untuk laporan lama
        $student->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Murid dihapus',
            'data'    => null,
        ]);
    }

    /**
     * POST /api/school/admin/students/{student}/guardians
     * Hubungkan akun wali (role=wali) ke murid ini.
     */
    public function attachGuardian(Request $request, Student $student)
    {
        $this->authorize('update', $student);

        $data = $request->validate([
            'user_id'                => 'required|exists:users,id',
            'relationship'           => 'required|in:ayah,ibu,wali_lain',
            'is_primary'             => 'boolean',
            'can_submit_permission'  => 'boolean',
        ]);

        $guardianUser = User::findOrFail($data['user_id']);
        abort_if($guardianUser->role !== 'wali', 422, 'User yang dipilih bukan berrole wali');
        abort_if($guardianUser->company_id !== $student->company_id, 422, 'Wali harus dari sekolah yang sama');

        $student->guardians()->syncWithoutDetaching([
            $data['user_id'] => [
                'relationship'          => $data['relationship'],
                'is_primary'            => $data['is_primary'] ?? false,
                'can_submit_permission' => $data['can_submit_permission'] ?? true,
            ],
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Wali berhasil dihubungkan ke murid',
            'data'    => $student->load('guardians:id,name,phone,email'),
        ]);
    }

    /**
     * DELETE /api/school/admin/students/{student}/guardians/{user}
     */
    public function detachGuardian(Student $student, User $user)
    {
        $this->authorize('update', $student);

        $student->guardians()->detach($user->id);

        return response()->json([
            'status'  => true,
            'message' => 'Wali berhasil dilepas dari murid',
            'data'    => null,
        ]);
    }
}
