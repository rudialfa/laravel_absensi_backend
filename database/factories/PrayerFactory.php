<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prayer>
 */
class PrayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $dayOffset = 0;

        $cities = ['Bandung', 'Jakarta', 'Surabaya', 'Yogyakarta', 'Medan', 'Makassar', 'Semarang', 'Palembang', 'Denpasar', 'Malang'];

        return [
            'date'    => Carbon::today()->addDays($dayOffset++),
            'city'    => $this->faker->unique()->randomElement($cities),
            'fajr'    => '04:30:00',
            'dzuhur'  => '12:00:00',
            'ashar'   => '15:15:00',
            'maghrib' => '18:00:00',
            'isya'    => '19:10:00',
            'source'  => 'factory',
        ];
    }
}
