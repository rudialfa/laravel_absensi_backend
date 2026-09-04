<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batas Waktu Terlambat
    |--------------------------------------------------------------------------
    |
    | Jam batas murid dianggap "hadir" tepat waktu. Kalau absen dicatat
    | setelah jam ini, sistem otomatis menyarankan status "terlambat"
    | (tetap bisa di-override manual oleh guru di kiosk/dashboard).
    | Format: H:i:s
    |
    */
    'late_threshold' => env('SCHOOL_LATE_THRESHOLD', '07:15:00'),

    /*
    |--------------------------------------------------------------------------
    | Device Kiosk
    |--------------------------------------------------------------------------
    |
    | device_token_length : panjang token acak untuk autentikasi kiosk.
    | ping_stale_minutes   : device dianggap "offline"/bermasalah kalau tidak
    |                        ping (last_seen_at) lebih dari sekian menit —
    |                        dipakai untuk badge status di dashboard admin.
    |
    */
    'device_token_length' => env('SCHOOL_DEVICE_TOKEN_LENGTH', 64),
    'device_ping_stale_minutes' => env('SCHOOL_DEVICE_PING_STALE_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Foto Bukti Absen
    |--------------------------------------------------------------------------
    |
    | Disk & path penyimpanan foto yang diambil kiosk saat absen.
    | Disk mengikuti definisi di config/filesystems.php (misal 'public' atau 's3').
    |
    */
    'photo_disk' => env('SCHOOL_PHOTO_DISK', 'public'),
    'photo_path' => 'attendance-photos',

    /*
    |--------------------------------------------------------------------------
    | Retensi Foto
    |--------------------------------------------------------------------------
    |
    | Foto absen harian bisa menumpuk cepat (1 foto per murid per hari).
    | Foto lebih tua dari sekian hari akan dihapus oleh scheduled command
    | (lihat poin #18-19: cleanup command), datanya sendiri (record
    | student_attendances) TETAP disimpan, cuma file foto-nya yang dibuang.
    |
    */
    'photo_retention_days' => env('SCHOOL_PHOTO_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Pengajuan Izin/Sakit
    |--------------------------------------------------------------------------
    |
    | min_days_before : 0 artinya wali boleh ajukan izin untuk hari ini juga
    |                   (misal anak mendadak sakit pagi itu).
    | max_days_ahead  : batas berapa hari ke depan wali boleh ajukan izin
    |                   sekaligus (mencegah pengajuan iseng/jauh ke depan).
    |
    */
    'permission_min_days_before' => env('SCHOOL_PERMISSION_MIN_DAYS_BEFORE', 0),
    'permission_max_days_ahead'  => env('SCHOOL_PERMISSION_MAX_DAYS_AHEAD', 14),

    /*
    |--------------------------------------------------------------------------
    | Tahun Ajaran Default
    |--------------------------------------------------------------------------
    |
    | Dipakai saat admin bikin kelas baru tanpa isi academic_year manual.
    | Asumsi tahun ajaran baru dimulai bulan Juli — sebelum itu masih
    | dianggap tahun ajaran sebelumnya.
    |
    */
    'academic_year_start_month' => 7,

    /*
    |--------------------------------------------------------------------------
    | Batas Data untuk SD Pondok
    |--------------------------------------------------------------------------
    |
    | max_boarding_ratio_warning : dipakai admin dashboard buat kasih notice
    |                              kalau jumlah murid boarding sudah mendekati
    |                              kapasitas asrama (bukan hard-limit, cuma warning).
    |
    */
    'boarding_capacity_warning_threshold' => env('SCHOOL_BOARDING_CAPACITY_WARNING', 0.9),

];
