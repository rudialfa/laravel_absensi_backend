<?php

namespace Database\Seeders;

use App\Enums\School\ClassTeacherRole;
use App\Models\AttendanceDevice;
use App\Models\ClassRoom;
use App\Models\Company;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SchoolDemoSeeder extends Seeder
{
    /**
     * Password default semua akun demo — ganti/hapus seeder ini di production.
     */
    private const DEMO_PASSWORD = 'password123';

    public function run(): void
    {
        $this->seedSchool(
            name: 'SD Negeri Harapan Bangsa',
            isBoarding: false,
            classCount: 3,
            studentsPerClass: 15,
        );

        $this->seedSchool(
            name: 'SD Islam Terpadu Pondok Ilmu',
            isBoarding: true,
            classCount: 2,
            studentsPerClass: 12,
        );

        $this->command->info('Seeding modul sekolah selesai. Password semua akun demo: ' . self::DEMO_PASSWORD);
    }

    private function seedSchool(string $name, bool $isBoarding, int $classCount, int $studentsPerClass): void
    {
        // 1. Company (sekolah)
        $company = Company::create([
            'name'       => $name,
            'email'      => \Illuminate\Support\Str::slug($name) . '@sekolah.test',
            'address'    => 'Jl. Pendidikan No. 1',
            'latitude'   => -7.6,
            'longitude'  => 109.6,
            'radius_km'  => 0.5,
            'time_in'    => '07:00:00',
            'time_out'   => '13:00:00',
            'type'       => 'school',
            'is_boarding' => $isBoarding,
            'timezone'   => 'Asia/Jakarta',
        ]);

        // 2. Admin sekolah
        $admin = User::create([
            'name'       => 'Admin ' . $name,
            'email'      => 'admin@' . \Illuminate\Support\Str::slug($name) . '.test',
            'password'   => Hash::make(self::DEMO_PASSWORD),
            'role'       => 'admin',
            'company_id' => $company->id,
        ]);

        // 3. Kelas + guru + murid + wali per kelas
        for ($i = 1; $i <= $classCount; $i++) {
            $class = ClassRoom::factory()->create([
                'company_id'  => $company->id,
                'name'        => $i . 'A',
                'grade_level' => $i,
            ]);

            // Guru wali kelas
            $guru = User::create([
                'name'       => "Bu Guru Kelas {$class->name}",
                'email'      => "guru.{$class->id}@" . \Illuminate\Support\Str::slug($name) . '.test',
                'password'   => Hash::make(self::DEMO_PASSWORD),
                'role'       => 'guru',
                'company_id' => $company->id,
            ]);

            $class->update(['homeroom_teacher_id' => $guru->id]);
            $class->classTeachers()->create([
                'user_id'       => $guru->id,
                'role_in_class' => ClassTeacherRole::WaliKelas,
            ]);

            // Kiosk device khusus kelas ini
            AttendanceDevice::factory()->create([
                'company_id' => $company->id,
                'class_id'   => $class->id,
                'name'       => "Kiosk Kelas {$class->name}",
            ]);

            // Murid + wali
            for ($j = 1; $j <= $studentsPerClass; $j++) {
                $student = Student::factory()
                    ->when($isBoarding && $j <= 3, fn($factory) => $factory->boarding())
                    ->create([
                        'company_id' => $company->id,
                        'class_id'   => $class->id,
                    ]);

                // 1 wali per murid (ayah), sebagian dapat tambahan ibu
                $ayah = User::create([
                    'name'       => 'Wali dari ' . $student->name,
                    'email'      => 'wali.' . $student->id . '@' . \Illuminate\Support\Str::slug($name) . '.test',
                    'password'   => Hash::make(self::DEMO_PASSWORD),
                    'role'       => 'wali',
                    'company_id' => $company->id,
                ]);

                $student->guardians()->attach($ayah->id, [
                    'relationship'          => 'ayah',
                    'is_primary'            => true,
                    'can_submit_permission' => true,
                ]);

                // Absen 7 hari terakhir per murid — biar dashboard rekap langsung ada datanya
                for ($d = 6; $d >= 0; $d--) {
                    StudentAttendance::factory()->create([
                        'company_id' => $company->id,
                        'class_id'   => $class->id,
                        'student_id' => $student->id,
                        'date'       => now()->subDays($d)->toDateString(),
                    ]);
                }
            }
        }

        $this->command->info("Sekolah '{$name}' selesai di-seed. Login admin: {$admin->email}");
    }
}
