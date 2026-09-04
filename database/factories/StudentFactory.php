<?php

namespace Database\Factories;

use App\Enums\School\Gender;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    public function definition(): array
    {
        $gender = $this->faker->randomElement(Gender::cases());

        return [
            'company_id'    => Company::factory(),
            'class_id'      => null, // di-set eksplisit di seeder saat assign ke kelas
            'nis'           => $this->faker->unique()->numerify('NIS-#####'),
            'nisn'          => $this->faker->optional(0.7)->numerify('##########'),
            'name'          => $gender === Gender::Laki
                ? $this->faker->firstNameMale() . ' ' . $this->faker->lastName()
                : $this->faker->firstNameFemale() . ' ' . $this->faker->lastName(),
            'gender'        => $gender,
            'birth_place'   => $this->faker->city(),
            'birth_date'    => $this->faker->dateTimeBetween('-12 years', '-6 years'),
            'address'       => $this->faker->address(),
            'is_boarding'   => false,
            'enrolled_at'   => now()->subMonths($this->faker->numberBetween(1, 24)),
            'is_active'     => true,
        ];
    }

    /**
     * Murid yang tinggal di asrama — dipakai khusus skenario SD Pondok.
     */
    public function boarding(): static
    {
        return $this->state(fn() => ['is_boarding' => true]);
    }
}
