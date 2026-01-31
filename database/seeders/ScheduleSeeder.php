<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ustadz = User::where('role', 'ustadz')->first();

        Schedule::create([
            'user_id' => $ustadz->id,
            'title' => 'Pelajaran Fiqih',
            'description' => 'Fiqih dasar santri',
            'start_datetime' => Carbon::today(),
            'location' => [
                'name' => 'Ruang Kelas A'
            ],
            'status' => 'upcoming',
        ]);

        Schedule::create([
            'user_id' => $ustadz->id,
            'title' => 'Tahfidz',
            'description' => 'Hafalan Al-Qur\'an',
            'start_datetime' => Carbon::today()->setTime(10, 00),
            'location' => [
                'name' => 'Masjid'
            ],
            'status' => 'upcoming',
        ]);
    }
}
