<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // SUPERADMIN
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@ositech.com',
            'password' => Hash::make('123456'),
            'role' => 'superadmin',
            'company_id' => null,
        ]);
    }
}
