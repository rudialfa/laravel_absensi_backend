<?php

namespace App\Enums\School;

enum SchoolRole: string
{
    case Admin = 'admin';
    case Guru  = 'guru';
    case Wali  = 'wali';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin Sekolah',
            self::Guru  => 'Guru',
            self::Wali  => 'Wali / Orang Tua',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
