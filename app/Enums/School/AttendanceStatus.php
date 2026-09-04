<?php

namespace App\Enums\School;

enum AttendanceStatus: string
{
    case Hadir     = 'hadir';
    case Terlambat = 'terlambat';
    case Izin      = 'izin';
    case Sakit     = 'sakit';
    case Alpa      = 'alpa';

    /**
     * Label yang enak dibaca untuk ditampilkan di UI (dashboard admin/guru/wali).
     */
    public function label(): string
    {
        return match ($this) {
            self::Hadir     => 'Hadir',
            self::Terlambat => 'Terlambat',
            self::Izin      => 'Izin',
            self::Sakit     => 'Sakit',
            self::Alpa      => 'Alpa (Tanpa Keterangan)',
        };
    }

    /**
     * Status yang dianggap "masuk" untuk keperluan rekap kehadiran
     * (misal hitung persentase kehadiran bulanan).
     */
    public function isPresent(): bool
    {
        return in_array($this, [self::Hadir, self::Terlambat]);
    }

    /**
     * Dipakai di rule validasi: 'status' => ['required', Rule::enum(AttendanceStatus::class)]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
