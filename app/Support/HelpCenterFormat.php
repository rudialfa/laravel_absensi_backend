<?php

namespace App\Support;

use App\Models\AppPolicy;
use App\Models\HelpArticle;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Format response untuk Bantuan / FAQ, Tiket Bantuan, dan Kebijakan.
 * Semua tanggal dikirim dalam WIB dan sudah berupa label siap tampil.
 */
class HelpCenterFormat
{
    private const BULAN = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public const TICKET_CATEGORIES = [
        'absensi'   => 'Absensi',
        'akun'      => 'Akun & Login',
        'langganan' => 'Langganan & Pembayaran',
        'bug'       => 'Error / Aplikasi bermasalah',
        'saran'     => 'Saran & Masukan',
        'other'     => 'Lainnya',
    ];

    public const TICKET_PRIORITIES = [
        'low'    => 'Rendah',
        'medium' => 'Sedang',
        'high'   => 'Tinggi',
    ];

    public const TICKET_STATUSES = [
        'open'        => 'Menunggu balasan',
        'in_progress' => 'Sedang diproses',
        'resolved'    => 'Selesai',
        'closed'      => 'Ditutup',
    ];

    public const POLICY_TYPES = [
        'terms_of_service' => 'Syarat & Ketentuan',
        'privacy_policy'   => 'Kebijakan Privasi',
        'refund_policy'    => 'Kebijakan Pengembalian Dana',
        'other'            => 'Kebijakan Lainnya',
    ];

    // ============================================================
    // TANGGAL
    // ============================================================

    public static function wib(?Carbon $d): ?Carbon
    {
        return $d?->copy()->timezone('Asia/Jakarta');
    }

    /** "1 Nov 2026" */
    public static function date(?Carbon $d): ?string
    {
        $d = self::wib($d);
        return $d ? $d->day . ' ' . self::BULAN[$d->month] . ' ' . $d->year : null;
    }

    /** "1 Nov 2026, 14:05" */
    public static function dateTime(?Carbon $d): ?string
    {
        $d = self::wib($d);
        return $d ? self::date($d) . ', ' . $d->format('H:i') : null;
    }

    /** "Hari ini", "Kemarin", atau "1 Nov 2026" */
    public static function dayLabel(?Carbon $d): ?string
    {
        $d = self::wib($d);
        if (! $d) return null;

        $today = now('Asia/Jakarta')->startOfDay();
        if ($d->isSameDay($today)) return 'Hari ini';
        if ($d->isSameDay($today->copy()->subDay())) return 'Kemarin';

        return self::date($d);
    }

    /** "14:05" kalau hari ini, selain itu "1 Nov 2026" */
    public static function shortLabel(?Carbon $d): ?string
    {
        $d = self::wib($d);
        if (! $d) return null;

        return $d->isSameDay(now('Asia/Jakarta')) ? $d->format('H:i') : self::date($d);
    }

    // ============================================================
    // KEBIJAKAN
    // ============================================================

    public static function policy(AppPolicy $p, bool $withContent = true): array
    {
        $data = [
            'id'                 => $p->id,
            'type'               => $p->type,
            'type_label'         => self::POLICY_TYPES[$p->type] ?? Str::headline($p->type),
            'title'              => $p->title,
            'version'            => $p->version,
            'published_at'       => self::wib($p->published_at)?->format('Y-m-d H:i:s'),
            'published_at_label' => self::date($p->published_at),
        ];

        if ($withContent) {
            $data['content'] = $p->content;
        }

        return $data;
    }

    // ============================================================
    // FAQ
    // ============================================================

    public static function article(HelpArticle $a, bool $withContent = true): array
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $a->content)));

        $data = [
            'id'               => $a->id,
            'category'         => $a->category,
            'title'            => $a->title,
            'excerpt'          => Str::limit($plain, 120),
            'view_count'       => (int) $a->view_count,
            'updated_at_label' => self::date($a->updated_at),
        ];

        if ($withContent) {
            $data['content'] = $a->content;
        }

        return $data;
    }

    // ============================================================
    // TIKET
    // ============================================================

    public static function ticketNumber(SupportTicket $t): string
    {
        return 'T-' . str_pad((string) $t->id, 5, '0', STR_PAD_LEFT);
    }

    public static function ticket(SupportTicket $t, bool $full = false): array
    {
        $lastReply = $t->relationLoaded('latestPublicReply') ? $t->latestPublicReply : null;
        $lastAt    = $lastReply?->created_at ?? $t->created_at;

        // "Dibalas tim" = balasan terakhir bukan dari pemilik tiket
        $answered = $lastReply && (int) $lastReply->user_id !== (int) $t->user_id;

        $data = [
            'id'                => $t->id,
            'ticket_number'     => self::ticketNumber($t),
            'subject'           => $t->subject,
            'excerpt'           => Str::limit(trim(preg_replace('/\s+/', ' ', (string) $t->message)), 100),
            'category'          => $t->category,
            'category_label'    => self::TICKET_CATEGORIES[$t->category] ?? Str::headline((string) $t->category),
            'priority'          => $t->priority,
            'priority_label'    => self::TICKET_PRIORITIES[$t->priority] ?? $t->priority,
            'status'            => $t->status,
            'status_label'      => self::TICKET_STATUSES[$t->status] ?? $t->status,
            'replies_count'     => (int) ($t->public_replies_count ?? 0),
            'has_new_answer'    => $answered && in_array($t->status, ['in_progress', 'resolved'], true),
            'last_activity'     => self::shortLabel($lastAt),
            'created_at_label'  => self::dateTime($t->created_at),
            'resolved_at_label' => self::dateTime($t->resolved_at),
            'can_reply'         => $t->status !== 'closed',
            'is_closed'         => in_array($t->status, ['resolved', 'closed'], true),
        ];

        if ($full) {
            $data['message'] = $t->message;
            $data['user_id'] = $t->user_id;
        }

        return $data;
    }

    public static function reply(SupportTicketReply $r, SupportTicket $t, ?int $viewerId = null): array
    {
        $fromOwner = (int) $r->user_id === (int) $t->user_id;

        return [
            'id'             => $r->id,
            'message'        => $r->message,
            'attachment_url' => self::fileUrl($r->attachment),
            'is_mine'        => $viewerId !== null && (int) $r->user_id === $viewerId,
            'from_support'   => ! $fromOwner,
            'sender_name'    => $fromOwner ? ($r->user->name ?? 'Anda') : 'Tim Dukungan',
            'date_label'     => self::dayLabel($r->created_at),
            'time_label'     => self::wib($r->created_at)?->format('H:i'),
            'created_at_label' => self::dateTime($r->created_at),
        ];
    }

    public static function fileUrl(?string $path): ?string
    {
        if (! $path) return null;
        if (Str::startsWith($path, ['http://', 'https://'])) return $path;

        // asset() memakai alamat server yang sedang diakses HP → gambar tetap terbuka
        return asset('storage/' . ltrim($path, '/'));
    }
}
