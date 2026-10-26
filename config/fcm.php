<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (HTTP v1)
    |--------------------------------------------------------------------------
    |
    | File service account dari Firebase Console:
    |   Project settings → Service accounts → Generate new private key
    |
    | Project ini sudah punya file-nya di: storage/app/firebase-credentials.json
    | (JANGAN di-commit ke git)
    |
    | Catatan: API lama FCM (server key / "legacy") sudah dimatikan Google
    | sejak Juni 2024. Wajib pakai HTTP v1 dengan service account.
    */

    'credentials' => env('FCM_CREDENTIALS', storage_path('app/firebase-credentials.json')),

    // Kosongkan untuk memakai project_id dari file service account
    'project_id' => env('FCM_PROJECT_ID'),

    // Harus sama dengan channel id yang dibuat di aplikasi Android
    'android_channel_id' => env('FCM_ANDROID_CHANNEL', 'high_importance_channel'),

    // Jam pengingat H-1 libur (WIB)
    'holiday_reminder_time' => env('FCM_HOLIDAY_REMINDER_TIME', '17:00'),
];
