<?php

namespace Database\Factories;

use App\Enums\School\AttendanceStatus;
use App\Models\Company;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentAttendanceFactory extends Factory
{
    public function definition(): array
    {
        // Distribusi realistis: kebanyakan hadir, sisanya kecil kemungkinan absen
        $status = $this->faker->randomElement([
            AttendanceStatus::Hadir,
            AttendanceStatus::Hadir,
            AttendanceStatus::Hadir,
            AttendanceStatus::Hadir,
            AttendanceStatus::Hadir,
            AttendanceStatus::Hadir,
            AttendanceStatus::Terlambat,
            AttendanceStatus::Izin,
            AttendanceStatus::Sakit,
            AttendanceStatus::Alpa,
        ]);

        return [
            'company_id'      => Company::factory(),
            'class_id'        => null,
            'student_id'      => Student::factory(),
            'date'            => now()->toDateString(),
            'status'          => $status,
            'check_in_time'   => $status->isPresent() ? $this->faker->time('H:i:s', '08:00:00') : null,
            'photo_evidence'  => $status->isPresent() ? 'attendance-photos/' . $this->faker->uuid() . '.jpg' : null,
        ];
    }
}
