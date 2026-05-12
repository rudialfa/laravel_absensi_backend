<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Gunakan upsert agar aman dijalankan berulang kali
        // (tidak duplikat jika sudah ada datanya)
        DB::table('subscription_plans')->upsert(
            [
                [
                    'name'          => 'Trial',
                    'slug'          => 'trial',
                    'description'   => 'Coba gratis 7 hari',
                    'duration_days' => 7,
                    'price'         => 0,
                    'is_free'       => true,
                    'is_active'     => true,
                    'is_popular'    => false,
                    'saving_label'  => null,
                    'sort_order'    => 0,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                [
                    'name'          => 'Bulanan',
                    'slug'          => 'monthly',
                    'description'   => 'Paket berlangganan 30 hari',
                    'duration_days' => 30,
                    'price'         => 99000,
                    'is_free'       => false,
                    'is_active'     => true,
                    'is_popular'    => false,
                    'saving_label'  => null,
                    'sort_order'    => 1,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                [
                    'name'          => '6 Bulan',
                    'slug'          => 'biannual',
                    'description'   => 'Paket berlangganan 180 hari',
                    'duration_days' => 180,
                    'price'         => 499000,
                    'is_free'       => false,
                    'is_active'     => true,
                    'is_popular'    => true,
                    'saving_label'  => 'Hemat ~16%',
                    'sort_order'    => 2,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                [
                    'name'          => 'Tahunan',
                    'slug'          => 'yearly',
                    'description'   => 'Paket berlangganan 365 hari',
                    'duration_days' => 365,
                    'price'         => 899000,
                    'is_free'       => false,
                    'is_active'     => true,
                    'is_popular'    => false,
                    'saving_label'  => 'Hemat ~25%',
                    'sort_order'    => 3,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
            ],
            ['slug'],           // unique key — deteksi duplikat berdasarkan slug
            [                   // kolom yang di-update jika slug sudah ada
                'name',
                'description',
                'duration_days',
                'price',
                'is_free',
                'is_active',
                'is_popular',
                'saving_label',
                'sort_order',
                'updated_at',
            ]
        );
    }
}
