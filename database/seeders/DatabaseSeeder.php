<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\User::factory(10)->create();

        User::factory()->create([
            'name' => 'Rudi Admin',
            'email' => 'rudi@absensi.com',
            'password' => Hash::make('12345678'),
            'role' => 'company',
        ]);

        \App\Models\Company::create([
            'name' => 'PT. CTA-BYTE',
            'email' => 'cta@absensi.com',
            'address' => 'Jl. indonesia raya',
            'latitude' => '-7.4656239',
            'longitude' => '109.5364833',
            'radius_km' => '0.5',
            'time_in' => '08:00',
            'time_out' => '17:00',
        ]);

        $this->call([
            AttendanceSeeder::class,
            PermissionSeeder::class,
            CompanySeeder::class,
            UserSeeder::class,
            PrayerSeeder::class,
            ScheduleSeeder::class,
        ]);
    }
}
