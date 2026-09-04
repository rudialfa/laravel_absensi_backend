<?php

namespace App\Enums\School;

enum Gender: string
{
    case Laki  = 'L';
    case Perempuan = 'P';

    public function label(): string
    {
        return match ($this) {
            self::Laki      => 'Laki-laki',
            self::Perempuan => 'Perempuan',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
