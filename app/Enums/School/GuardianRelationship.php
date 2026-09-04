<?php

namespace App\Enums\School;

enum GuardianRelationship: string
{
    case Ayah    = 'ayah';
    case Ibu     = 'ibu';
    case WaliLain = 'wali_lain';

    public function label(): string
    {
        return match ($this) {
            self::Ayah     => 'Ayah',
            self::Ibu      => 'Ibu',
            self::WaliLain => 'Wali Lainnya',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
