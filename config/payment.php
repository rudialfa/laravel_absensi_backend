<?php

// config/payment.php
// Setelah update .env jalankan: php artisan config:clear

return [

    // ──────────────────────────────────────────────────────────
    // BCA
    // ──────────────────────────────────────────────────────────
    'bca' => [

        // Sandbox  : https://sandbox.bca.co.id
        // Production: https://api.bca.co.id
        'base_url' => env('BCA_BASE_URL', 'https://sandbox.bca.co.id'),

        // Dari developer.bca.co.id → My Apps
        'client_id'     => env('BCA_CLIENT_ID'),
        'client_secret' => env('BCA_CLIENT_SECRET'),

        // Untuk HMAC signature endpoint non-SNAP (legacy)
        'api_key'    => env('BCA_API_KEY'),
        'api_secret' => env('BCA_API_SECRET'),

        // Partner Service ID: 8 karakter dengan LEFT-PADDING SPASI
        // Didapat dari BCA saat onboarding sebagai biller VA
        // Contoh kode perusahaan "12345" → isi "   12345" (3 spasi + 5 digit)
        // WAJIB pakai tanda kutip " di .env karena ada spasi di depan
        'partner_service_id' => env('BCA_PARTNER_SERVICE_ID'),

        // Private Key RSA untuk Signature Asymmetric (dipakai saat get access token)
        //
        // Ada 2 cara menyimpan private key, pilih salah satu:
        //
        // CARA 1 — File PEM (lebih aman untuk production):
        //   1. Upload file ke: storage/keys/bca_private.pem
        //   2. Tambahkan ke .gitignore: /storage/keys/
        //   3. Kosongkan BCA_PRIVATE_KEY di .env
        //
        // CARA 2 — String di .env (mudah untuk development):
        //   Isi BCA_PRIVATE_KEY dengan isi file PEM, ganti newline dengan \n
        //   Contoh: "-----BEGIN RSA PRIVATE KEY-----\nMIIEo...\n-----END RSA PRIVATE KEY-----\n"
        //   str_replace('\n', "\n") di VaPaymentService akan handle unescape-nya otomatis
        'private_key' => file_exists(storage_path('keys/bca_private.pem'))
            ? file_get_contents(storage_path('keys/bca_private.pem'))
            : env('BCA_PRIVATE_KEY'),
    ],

    // ──────────────────────────────────────────────────────────
    // MANDIRI
    // ──────────────────────────────────────────────────────────
    'mandiri' => [

        // Sandbox  : https://sandbox.mandiri.co.id (sesuaikan dengan doc Mandiri)
        // Production: URL production dari portal Mandiri
        'base_url' => env('MANDIRI_BASE_URL', 'https://sandbox.mandiri.co.id'),

        // Dari portal developer Mandiri
        'client_id'     => env('MANDIRI_CLIENT_ID'),
        'client_secret' => env('MANDIRI_CLIENT_SECRET'),

        // Kode perusahaan sebagai prefix nomor VA Mandiri
        // Contoh: "70012" → nomor VA = "70012" + customerNo
        'company_code' => env('MANDIRI_COMPANY_CODE'),
    ],

    // ──────────────────────────────────────────────────────────
    // UMUM
    // ──────────────────────────────────────────────────────────

    // Berapa jam batas waktu pembayaran invoice
    // Default 24 jam — customer punya waktu 24 jam untuk bayar VA
    'invoice_due_hours' => env('INVOICE_DUE_HOURS', 24),
];
