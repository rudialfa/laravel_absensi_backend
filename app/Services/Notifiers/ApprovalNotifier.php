<?php

namespace App\Services\Notifiers;

use App\Models\Company;
use App\Models\Leaves;
use App\Models\OvertimeRequest;
use App\Models\Permission;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\CompanySettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi pengajuan & persetujuan: izin, cuti, lembur.
 *
 *   submitted() → ke semua HR (kalau HR mengaktifkan "notifikasi langsung")
 *   decided()   → ke karyawan yang mengajukan
 *
 * Semua dibungkus try/catch: gagal kirim notifikasi TIDAK PERNAH
 * menggagalkan proses simpan / approve.
 */
class ApprovalNotifier
{
    public const KIND_PERMISSION = 'permission';
    public const KIND_LEAVE      = 'leave';
    public const KIND_OVERTIME   = 'overtime';

    private const LABELS = [
        self::KIND_PERMISSION => 'izin',
        self::KIND_LEAVE      => 'cuti',
        self::KIND_OVERTIME   => 'lembur',
    ];

    private const BULAN = [
        1 => 'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des',
    ];

    public function __construct(private NotificationService $notif) {}

    // ----------------------------------------------------------
    // KE HR: ada pengajuan baru
    // ----------------------------------------------------------

    public function submitted(string $kind, Model $item): void
    {
        try {
            $company = Company::find($item->company_id);

            if (!$company || !CompanySettings::notif($company, 'hr_instant_approval.enabled', true)) {
                return;
            }

            $employee = User::find($item->user_id, ['id', 'name']);
            $label    = self::LABELS[$kind];

            $this->notif->sendLater(
                $this->notif->hrOf((int) $item->company_id),
                "{$kind}.submitted",
                '📝 Pengajuan ' . $label . ' baru',
                ($employee?->name ?? 'Karyawan') . ' mengajukan ' . $this->detail($kind, $item),
                [
                    'category'  => 'approval',
                    'action'    => "hr_{$kind}_detail",
                    'reference' => $item,
                    'data'      => [
                        'kind'        => $kind,
                        "{$kind}_id"  => $item->getKey(),
                        'employee_id' => $item->user_id,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Notif pengajuan gagal', ['kind' => $kind, 'error' => $e->getMessage()]);
        }
    }

    // ----------------------------------------------------------
    // KE KARYAWAN: pengajuan disetujui / ditolak
    // ----------------------------------------------------------

    public function decided(string $kind, Model $item, bool $approved, ?string $note = null): void
    {
        try {
            $label = self::LABELS[$kind];

            $title = $approved
                ? '✅ ' . ucfirst($label) . ' disetujui'
                : '❌ ' . ucfirst($label) . ' ditolak';

            $body = ucfirst($this->detail($kind, $item)) . ($approved ? ' telah disetujui HR.' : ' ditolak HR.');

            if ($note) {
                $body .= ($approved ? ' Catatan: ' : ' Alasan: ') . $note;
            }

            $this->notif->sendLater(
                $item->user_id,
                $kind . ($approved ? '.approved' : '.rejected'),
                $title,
                $body,
                [
                    'category'  => 'approval',
                    'action'    => "{$kind}_detail",
                    'reference' => $item,
                    'data'      => [
                        'kind'       => $kind,
                        "{$kind}_id" => $item->getKey(),
                        'approved'   => $approved ? 1 : 0,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Notif keputusan gagal', ['kind' => $kind, 'error' => $e->getMessage()]);
        }
    }

    // ----------------------------------------------------------
    // TEKS DETAIL
    // ----------------------------------------------------------

    private function tgl($date): string
    {
        $d = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $d->day . ' ' . self::BULAN[$d->month] . ' ' . $d->year;
    }

    private function range($start, $end): string
    {
        $s = $start instanceof Carbon ? $start : Carbon::parse($start);
        $e = $end ? ($end instanceof Carbon ? $end : Carbon::parse($end)) : null;

        if (!$e || $s->isSameDay($e)) {
            return $this->tgl($s);
        }

        $days = (int) $s->diffInDays($e, true) + 1;

        return $this->tgl($s) . ' – ' . $this->tgl($e) . " ({$days} hari)";
    }

    private function hm(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }

    private function detail(string $kind, Model $item): string
    {
        if ($kind === self::KIND_PERMISSION && $item instanceof Permission) {
            $text = strtolower($item->type_label) . ' ' . $this->range($item->date_permission, $item->end_date);

            if ($item->start_time && $item->end_time) {
                $text .= ' jam ' . $this->hm($item->start_time) . '–' . $this->hm($item->end_time);
            } elseif ($item->end_time) {
                $text .= ' s/d jam ' . $this->hm($item->end_time);
            } elseif ($item->start_time) {
                $text .= ' mulai jam ' . $this->hm($item->start_time);
            }

            return $text;
        }

        if ($kind === self::KIND_LEAVE && $item instanceof Leaves) {
            return strtolower($item->type_label) . ' ' . $this->range($item->start_date, $item->end_date);
        }

        if ($kind === self::KIND_OVERTIME && $item instanceof OvertimeRequest) {
            return 'lembur ' . $this->tgl($item->date)
                . ' jam ' . $this->hm($item->start_time) . '–' . $this->hm($item->end_time)
                . ' (' . $item->duration_label . ')';
        }

        return self::LABELS[$kind] ?? 'pengajuan';
    }
}
