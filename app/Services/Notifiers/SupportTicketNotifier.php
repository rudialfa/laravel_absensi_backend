<?php

namespace App\Services\Notifiers;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Services\NotificationService;
use App\Support\HelpCenterFormat;
use Illuminate\Support\Str;

/**
 * Notifikasi TIKET BANTUAN ke pemilik tiket (HR / karyawan).
 * Dipanggil dari panel Super Admin saat membalas / mengubah status.
 */
class SupportTicketNotifier
{
    public function __construct(private NotificationService $notif) {}

    /** Super Admin membalas (bukan catatan internal). */
    public function replied(SupportTicket $ticket, SupportTicketReply $reply): void
    {
        if ($reply->is_internal_note || ! $ticket->user_id) {
            return;
        }

        $this->notif->sendLater(
            $ticket->user_id,
            'support.reply',
            '💬 Balasan tiket ' . HelpCenterFormat::ticketNumber($ticket),
            'Tim Dukungan: ' . Str::limit(trim(preg_replace('/\s+/', ' ', $reply->message)), 120),
            $this->options($ticket),
        );
    }

    /** Status diubah Super Admin. Hanya resolved / closed / in_progress yang diberi tahu. */
    public function statusChanged(SupportTicket $ticket, string $oldStatus): void
    {
        if ($oldStatus === $ticket->status || ! $ticket->user_id) {
            return;
        }

        $number = HelpCenterFormat::ticketNumber($ticket);

        [$title, $body] = match ($ticket->status) {
            'resolved'    => ["✅ Tiket {$number} selesai", "\"{$ticket->subject}\" ditandai selesai. Balas tiket ini kalau masalah belum teratasi."],
            'closed'      => ["🔒 Tiket {$number} ditutup", "\"{$ticket->subject}\" sudah ditutup. Buat tiket baru kalau masih butuh bantuan."],
            'in_progress' => ["🛠️ Tiket {$number} sedang diproses", "Tim Dukungan sedang menangani \"{$ticket->subject}\"."],
            default       => [null, null],
        };

        if (! $title) {
            return;
        }

        $this->notif->sendLater($ticket->user_id, 'support.status', $title, $body, $this->options($ticket));
    }

    private function options(SupportTicket $ticket): array
    {
        return [
            'category'  => 'system',
            'action'    => 'support_ticket_detail',
            'reference' => $ticket,
            'data'      => [
                'ticket_id' => (string) $ticket->id,
                'status'    => $ticket->status,
            ],
        ];
    }
}
