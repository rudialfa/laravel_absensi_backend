<?php

namespace Database\Seeders;

use App\Models\Prayer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrayerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('prayers')->truncate();

        Prayer::factory(10)->create();
    }
}
