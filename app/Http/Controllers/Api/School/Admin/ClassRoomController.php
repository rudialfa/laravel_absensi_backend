<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreClassRoomRequest;
use App\Models\ClassRoom;
use Illuminate\Http\Request;

class ClassRoomController extends Controller
{
    /**
     * GET /api/school/admin/classes
     */
    public function index(Request $request)
    {
        $classes = ClassRoom::where('company_id', $request->user()->company_id)
            ->with('homeroomTeacher:id,name')
            ->withCount('students')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->paginate(20);

        return response()->json([
            'status'  => true,
            'message' => 'Data kelas berhasil diambil',
            'data'    => $classes,
        ]);
    }

    /**
     * POST /api/school/admin/classes
     */
    public function store(StoreClassRoomRequest $request)
    {
        $data = $request->validated();
        $data['company_id'] = $request->user()->company_id;

        $class = ClassRoom::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Kelas berhasil dibuat',
            'data'    => $class,
        ], 201);
    }

    /**
     * GET /api/school/admin/classes/{class}
     */
    public function show(ClassRoom $class)
    {
        $this->authorize('view', $class);

        $class->load(['homeroomTeacher:id,name', 'teachers:id,name', 'students']);

        return response()->json([
            'status'  => true,
            'message' => 'Detail kelas berhasil diambil',
            'data'    => $class,
        ]);
    }

    /**
     * PUT /api/school/admin/classes/{class}
     */
    public function update(StoreClassRoomRequest $request, ClassRoom $class)
    {
        $this->authorize('update', $class);

        $class->update($request->validated());

        return response()->json([
            'status'  => true,
            'message' => 'Kelas berhasil diperbarui',
            'data'    => $class,
        ]);
    }

    /**
     * DELETE /api/school/admin/classes/{class}
     */
    public function destroy(ClassRoom $class)
    {
        $this->authorize('delete', $class);

        $class->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Kelas dihapus',
            'data'    => null,
        ]);
    }
}
