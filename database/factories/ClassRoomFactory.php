<?php

namespace Database\Factories;

use App\Models\ClassRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassRoomFactory extends Factory
{
    protected $model = ClassRoom::class;

    public function definition(): array
    {
        return [
            'name'          => '1A',
            'grade_level'   => 1,
            'academic_year' => '2026/2027',
            'is_active'     => true,
        ];
    }
}
