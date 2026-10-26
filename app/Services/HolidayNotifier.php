<?php

namespace App\Services;

use App\Models\CompanyHoliday;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi libur ke HP karyawan.
 *  - created  : HR menambah libur
 *  - updated  : HR mengubah tanggal / nama libur
 *  - canceled : HR menghapus libur yang belum lewat
 *  - reminder : H-1 sebelum libur (dijalankan scheduler)
 */
class HolidayNotifier
{
    public function __construct(private FcmService $fcm) {}

    private function recipients(int $companyId)
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('role', 'employee')
            ->where('is_active', true)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->get(['id', 'fcm_token']);
    }

    private function send(CompanyHoliday $h, string $event, string $title, string $body): array
    {
        $result = $this->fcm->sendToUsers($this->recipients((int) $h->company_id), $title, $body, [
            'type'       => 'holiday',
            'event'      => $event,
            'holiday_id' => $h->id,
            'start_date' => $h->start_date?->format('Y-m-d'),
            'end_date'   => $h->end_date?->format('Y-m-d'),
        ]);

        Log::info("Notifikasi libur [{$event}]", ['holiday_id' => $h->id] + $result);

        return $result;
    }

    public function created(CompanyHoliday $h): array
    {
        return $this->send($h, 'created', '📅 Libur: ' . $h->name, $h->period_label . $this->noteSuffix($h));
    }

    public function updated(CompanyHoliday $h): array
    {
        return $this->send($h, 'updated', '📅 Jadwal libur diperbarui', $h->name . ' — ' . $h->period_label);
    }

    public function canceled(CompanyHoliday $h): array
    {
        return $this->send($h, 'canceled', '❌ Libur dibatalkan', $h->name . ' (' . $h->period_label . ') dibatalkan. Masuk kerja seperti biasa.');
    }

    public function reminder(CompanyHoliday $h): array
    {
        return $this->send($h, 'reminder', '🔔 Besok libur: ' . $h->name, $h->period_label . $this->noteSuffix($h));
    }

    private function noteSuffix(CompanyHoliday $h): string
    {
        return $h->note ? ' — ' . \Illuminate\Support\Str::limit($h->note, 80) : '';
    }
}
