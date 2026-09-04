<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AttendanceDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id'         => Company::factory(),
            'class_id'           => null,
            'name'               => 'Kiosk ' . $this->faker->word(),
            'device_token'       => Str::random(64),
            'device_identifier'  => strtoupper($this->faker->bothify('DEV-????-####')),
            'is_active'          => true,
            'last_seen_at'       => now(),
        ];
    }
}
