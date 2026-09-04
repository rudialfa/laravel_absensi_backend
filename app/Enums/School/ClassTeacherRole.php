<?php

namespace App\Enums\School;

enum ClassTeacherRole: string
{
    case WaliKelas = 'wali_kelas';
    case GuruMapel = 'guru_mapel';

    public function label(): string
    {
        return match ($this) {
            self::WaliKelas => 'Wali Kelas',
            self::GuruMapel => 'Guru Mata Pelajaran',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
