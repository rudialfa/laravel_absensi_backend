<?php

namespace Database\Factories;

use App\Enums\School\PermissionStatus;
use App\Enums\School\PermissionType;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentPermissionFactory extends Factory
{
    public function definition(): array
    {
        $type = $this->faker->randomElement(PermissionType::cases());

        return [
            'student_id'       => Student::factory(),
            'date_permission'  => now()->addDays($this->faker->numberBetween(0, 5))->toDateString(),
            'type'             => $type,
            'reason'           => $type === PermissionType::Sakit
                ? $this->faker->randomElement(['Demam', 'Flu', 'Sakit perut', 'Kontrol ke dokter'])
                : $this->faker->randomElement(['Acara keluarga', 'Mudik', 'Urusan keluarga']),
            'status'           => PermissionStatus::Pending,
        ];
    }
}
