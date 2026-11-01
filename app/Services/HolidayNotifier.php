<?php

namespace App\Services;

use App\Models\CompanyHoliday;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Notifikasi libur ke HP karyawan.
 *  - created  : HR menambah libur
 *  - updated  : HR mengubah tanggal / nama libur
 *  - canceled : HR menghapus libur yang belum lewat
 *  - reminder : H-1 sebelum libur (dijalankan scheduler)
 *
 * Versi baru: lewat NotificationService → selain pop-up,
 * notifikasi libur sekarang juga masuk kotak masuk aplikasi.
 */
class HolidayNotifier
{
    public function __construct(private NotificationService $notif) {}

    /**
     * @return array{sent:int, failed:int, skipped:int, stored:int}
     */
    private function send(CompanyHoliday $h, string $event, string $title, string $body): array
    {
        $r = $this->notif->send(
            $this->notif->employeesOf((int) $h->company_id),
            'holiday.' . $event,
            $title,
            $body,
            [
                'category'       => 'holiday',
                'action'         => 'holiday_detail',
                // Untuk "canceled" data libur sudah terhapus, jadi pakai id manual
                'reference_type' => 'CompanyHoliday',
                'reference_id'   => $h->id,
                'data'           => [
                    'holiday_id' => $h->id,
                    'event'      => $event,
                    'start_date' => $h->start_date?->format('Y-m-d'),
                    'end_date'   => $h->end_date?->format('Y-m-d'),
                ],
            ]
        );

        $result = [
            'sent'    => $r['sent'],
            'failed'  => $r['failed'],
            'skipped' => $r['no_token'],
            'stored'  => $r['stored'],
        ];

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
        return $h->note ? ' — ' . Str::limit($h->note, 80) : '';
    }
}
