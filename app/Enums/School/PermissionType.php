<?php

namespace App\Enums\School;

enum PermissionType: string
{
    case Izin  = 'izin';
    case Sakit = 'sakit';

    public function label(): string
    {
        return match ($this) {
            self::Izin  => 'Izin',
            self::Sakit => 'Sakit',
        };
    }

    /**
     * Konversi ke AttendanceStatus yang setara — dipakai saat izin
     * di-approve dan perlu dicatat ke student_attendances.
     */
    public function toAttendanceStatus(): AttendanceStatus
    {
        return match ($this) {
            self::Izin  => AttendanceStatus::Izin,
            self::Sakit => AttendanceStatus::Sakit,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
