<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'attendance_photos' => [
            // FIX: default sebelumnya salah ketik 'public' (bukan nama driver
            // yang valid). Driver yang benar untuk simpan file lokal adalah
            // 'local' — sama seperti disk 'public' bawaan Laravel di atas.
            'driver'     => env('SCHOOL_PHOTO_DRIVER', 'local'),
            'root'       => storage_path('app/public/attendance-photos'),
            'url'        => env('APP_URL') . '/storage/attendance-photos',
            'visibility' => 'public',
            'throw'      => false,

            // Kalau nanti pindah ke S3 (disarankan untuk production skala besar,
            // karena foto absen harian bisa menumpuk banyak), tinggal ganti
            // SCHOOL_PHOTO_DRIVER=s3 di .env dan isi kredensial S3 seperti biasa
            // (driver 's3' otomatis baca AWS_* env yang sudah ada di project).
        ],

        // CATATAN: karena driver-nya 'local', jangan lupa jalankan sekali:
        //   php artisan storage:link
        // supaya folder storage/app/public bisa diakses publik lewat /storage/...

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
