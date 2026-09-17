<?php

namespace App\Http\Controllers\Api\School\Wali;

use App\Http\Controllers\Controller;
use App\Models\HealthRecord;
use App\Models\Student;
use App\Models\UksVisit;
use Illuminate\Http\Request;

class WaliUksController extends Controller
{
    public function index(Request $request, Student $student)
    {
        $this->authorize('view', $student);

        $record = HealthRecord::where('student_id', $student->id)->first();
        $visits = UksVisit::where('student_id', $student->id)->latest('visited_at')->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data kesehatan anak berhasil diambil',
            'data'    => ['health_record' => $record, 'visits' => $visits],
        ]);
    }
}
