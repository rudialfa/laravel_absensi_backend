<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::where('type', 'company')->first();
        $pesantren = Company::where('type', 'pesantren')->first();

        // COMPANY
        User::create([
            'name' => 'Rudi HR',
            'email' => 'hr@ositech.com',
            'password' => Hash::make('123456'),
            'role' => 'hr',
            'company_id' => $company->id,
            'position' => 'HR Manager'
        ]);

        User::create([
            'name' => 'Budi Employee',
            'email' => 'employee@ositech.com',
            'password' => Hash::make('123456'),
            'role' => 'employee',
            'company_id' => $company->id,
            'position' => 'Staff IT'
        ]);

        // PESANTREN
        User::create([
            'name' => 'Ustadz Ahmad',
            'email' => 'ustadz@tpq.com',
            'password' => Hash::make('123456'),
            'role' => 'ustadz',
            'company_id' => $pesantren->id
        ]);

        User::create([
            'name' => 'Santri Ali',
            'email' => 'santri@tpq.com',
            'password' => Hash::make('123456'),
            'role' => 'santri',
            'company_id' => $pesantren->id
        ]);

        // SCHOOL — dihapus dari sini.
        // Akun admin/guru/wali untuk company type 'school' sekarang
        // sepenuhnya di-generate oleh SchoolDemoSeeder.php, sesuai
        // SchoolRole enum (admin, guru, wali).
    }
}
