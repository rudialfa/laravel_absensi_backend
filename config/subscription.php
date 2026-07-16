<?php

/**
 * Role yang jadi "penanggung jawab billing" per tipe company —
 * cuma role ini yang boleh: lihat detail VA/tagihan pending, mulai trial,
 * dan pilih paket (perpanjang langganan).
 *
 * Tambahkan/ubah sesuai kebutuhan untuk tipe yang belum ada di sini.
 * Tipe yang tidak terdaftar akan fallback ke 'default'.
 */
return [
    'billing_roles' => [
        'company'      => 'hr',
        'pesantren'    => 'ustadz',
        'school'       => 'teacher',
        'hospital'     => 'doctor',

        // Belum dikonfirmasi — sementara pakai default di bawah,
        // sesuaikan kalau role penanggung jawab billing-nya beda.
        // 'government'   => 'hr',
        // 'factory'      => 'hr',
        // 'retail'       => 'hr',
        // 'restaurant'   => 'hr',
        // 'training'     => 'hr',
        // 'organization' => 'hr',
        // 'transport'    => 'hr',
        // 'remote'       => 'hr',
        // 'sports'       => 'hr',
    ],

    // Dipakai kalau tipe company tidak ada di mapping atas
    'default_billing_role' => 'hr',
];
