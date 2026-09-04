<?php

namespace App\Console\Commands;

use App\Models\StudentAttendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOldAttendancePhotos extends Command
{
    protected $signature = 'school:cleanup-attendance-photos';

    protected $description = 'Hapus file foto bukti absen yang lebih tua dari retention policy (config school.photo_retention_days). Record absen di database TETAP disimpan, hanya file foto yang dibuang.';

    public function handle(): int
    {
        $retentionDays = config('school.photo_retention_days', 90);
        $cutoffDate = now()->subDays($retentionDays)->toDateString();

        $attendances = StudentAttendance::whereDate('date', '<', $cutoffDate)
            ->whereNotNull('photo_evidence')
            ->get();

        $deletedCount = 0;

        foreach ($attendances as $attendance) {
            if (Storage::disk('attendance_photos')->exists($attendance->photo_evidence)) {
                Storage::disk('attendance_photos')->delete($attendance->photo_evidence);
                $deletedCount++;
            }

            // Kosongkan kolomnya juga, biar tidak dicek ulang tiap kali command ini jalan
            $attendance->update(['photo_evidence' => null]);
        }

        $this->info("Selesai. {$deletedCount} foto absen (sebelum {$cutoffDate}) berhasil dihapus.");

        return self::SUCCESS;
    }
}
