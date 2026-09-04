<?php

namespace App\Http\Requests\School;

use App\Enums\School\AttendanceStatus;
use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKioskAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi device dicek terpisah lewat middleware VerifyDeviceToken,
        // bukan lewat Gate/Policy biasa (device bukan Authenticatable user).
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id'    => 'required|exists:class_rooms,id',
            'student_id'  => 'required|exists:students,id',
            'status'      => ['required', Rule::enum(AttendanceStatus::class)],
            // File asli dari kamera kiosk — bukan path string lagi.
            // Nullable karena guru tetap bisa absen manual tanpa foto
            // (misal kamera kiosk lagi rusak, jangan sampai alur absen macet total).
            'photo'       => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ];
    }

    /**
     * Cegah device kiosk sekolah A mengirim data murid/kelas milik sekolah B
     * (tenant isolation) — sekaligus pastikan murid memang bagian dari
     * kelas yang dikirim, bukan kelas lain.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $device = $this->attributes->get('attendanceDevice');

            if (!$device) {
                $validator->errors()->add('class_id', 'Device tidak terverifikasi.');
                return;
            }

            $class   = ClassRoom::find($this->input('class_id'));
            $student = Student::find($this->input('student_id'));

            if ($class && $class->company_id !== $device->company_id) {
                $validator->errors()->add('class_id', 'Kelas ini bukan milik sekolah pada device ini.');
            }

            if ($student && $student->company_id !== $device->company_id) {
                $validator->errors()->add('student_id', 'Murid ini bukan bagian dari sekolah pada device ini.');
            }

            if ($student && (int) $this->input('class_id') !== (int) $student->class_id) {
                $validator->errors()->add('student_id', 'Murid ini bukan bagian dari kelas yang dipilih.');
            }
        });
    }
}
