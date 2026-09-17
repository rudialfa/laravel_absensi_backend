<?php

namespace App\Http\Controllers\Api\School\Wali;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Http\Request;

class WaliBillController extends Controller
{
    /**
     * GET /api/school/wali/my-children/{student}/bills?status=
     */
    public function index(Request $request, Student $student)
    {
        $this->authorize('view', $student);

        $bills = StudentBill::where('student_id', $student->id)
            ->with(['billType:id,name', 'payments'])
            ->when($request->query('status'), fn($q, $s) => $q->where('status', $s))
            ->latest('due_date')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data tagihan anak berhasil diambil',
            'data'    => $bills,
        ]);
    }
}
