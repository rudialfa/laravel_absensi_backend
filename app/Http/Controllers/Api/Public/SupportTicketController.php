<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Support\HelpCenterFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Tiket bantuan dari aplikasi (HR & karyawan) → dibalas Super Admin di panel web.
 * Route ini TIDAK dikunci langganan, supaya user tetap bisa minta bantuan.
 */
class SupportTicketController extends Controller
{
    /** Batas tiket yang belum selesai per user (anti spam). */
    private const MAX_OPEN_TICKETS = 5;

    /**
     * GET /api/support-tickets?status=open|in_progress|resolved|closed|active|done&page=1
     *   active = open + in_progress, done = resolved + closed
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $query = SupportTicket::where('user_id', $userId)
            ->with(['latestPublicReply'])
            ->withCount(['publicReplies as public_replies_count'])
            ->orderByRaw("FIELD(status, 'in_progress', 'open', 'resolved', 'closed')")
            ->latest('updated_at');

        $status = $request->input('status');
        if ($status === 'active') {
            $query->whereIn('status', ['open', 'in_progress']);
        } elseif ($status === 'done') {
            $query->whereIn('status', ['resolved', 'closed']);
        } elseif (in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            $query->where('status', $status);
        }

        $tickets = $query->paginate(20)
            ->through(fn($t) => HelpCenterFormat::ticket($t));

        $counts = SupportTicket::where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil daftar tiket',
            'data'    => $tickets,
            'counts'  => [
                'all'    => (int) $counts->sum(),
                'active' => (int) (($counts['open'] ?? 0) + ($counts['in_progress'] ?? 0)),
                'done'   => (int) (($counts['resolved'] ?? 0) + ($counts['closed'] ?? 0)),
            ],
        ]);
    }

    /**
     * Pilihan kategori & prioritas untuk form buat tiket.
     * GET /api/support-tickets/options
     */
    public function options()
    {
        $map = fn(array $items) => collect($items)
            ->map(fn($label, $value) => ['value' => $value, 'label' => $label])
            ->values();

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil',
            'data'    => [
                'categories' => $map(HelpCenterFormat::TICKET_CATEGORIES),
                'priorities' => $map(HelpCenterFormat::TICKET_PRIORITIES),
            ],
        ]);
    }

    /**
     * POST /api/support-tickets
     * Body: { subject, message, category?, priority? }
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'subject'  => 'required|string|min:5|max:150',
            'message'  => 'required|string|min:10|max:5000',
            'category' => 'nullable|in:' . implode(',', array_keys(HelpCenterFormat::TICKET_CATEGORIES)),
            'priority' => 'nullable|in:low,medium,high',
        ], [
            'subject.required' => 'Judul wajib diisi.',
            'subject.min'      => 'Judul minimal 5 karakter.',
            'subject.max'      => 'Judul maksimal 150 karakter.',
            'message.required' => 'Pesan wajib diisi.',
            'message.min'      => 'Jelaskan kendala minimal 10 karakter.',
            'message.max'      => 'Pesan maksimal 5000 karakter.',
            'category.in'      => 'Kategori tidak valid.',
            'priority.in'      => 'Prioritas tidak valid.',
        ]);

        $openCount = SupportTicket::where('user_id', $user->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        if ($openCount >= self::MAX_OPEN_TICKETS) {
            return response()->json([
                'status'  => false,
                'message' => 'Anda masih punya ' . self::MAX_OPEN_TICKETS . ' tiket yang belum selesai. Lanjutkan percakapan di tiket yang ada, ya.',
            ], 422);
        }

        $ticket = SupportTicket::create([
            'company_id' => $user->company_id,
            'user_id'    => $user->id,
            'subject'    => trim($data['subject']),
            'message'    => trim($data['message']),
            'category'   => $data['category'] ?? 'other',
            'priority'   => $data['priority'] ?? 'medium',
            'status'     => 'open',
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Tiket ' . HelpCenterFormat::ticketNumber($ticket) . ' terkirim. Tim kami akan membalas secepatnya.',
            'data'    => HelpCenterFormat::ticket($ticket, true),
        ], 201);
    }

    /**
     * GET /api/support-tickets/{id}  → tiket + percakapan (tanpa catatan internal)
     */
    public function show(Request $request, int $id)
    {
        $userId = $request->user()->id;

        $ticket = SupportTicket::where('user_id', $userId)
            ->withCount(['publicReplies as public_replies_count'])
            ->find($id);

        if (! $ticket) {
            return response()->json([
                'status'  => false,
                'message' => 'Tiket tidak ditemukan',
            ], 404);
        }

        $replies = $ticket->publicReplies()
            ->with('user:id,name')
            ->oldest()
            ->get()
            ->map(fn($r) => HelpCenterFormat::reply($r, $ticket, $userId));

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil detail tiket',
            'data'    => [
                'ticket'  => HelpCenterFormat::ticket($ticket, true),
                'replies' => $replies,
            ],
        ]);
    }

    /**
     * POST /api/support-tickets/{id}/reply
     * Body: { message, attachment_base64? }   (gambar jpg/png, maks 3 MB)
     *
     * Tiket "Selesai" (resolved) yang dibalas user → dibuka lagi.
     * Tiket "Ditutup" (closed) tidak bisa dibalas.
     */
    public function reply(Request $request, int $id)
    {
        $user   = $request->user();
        $ticket = SupportTicket::where('user_id', $user->id)->find($id);

        if (! $ticket) {
            return response()->json([
                'status'  => false,
                'message' => 'Tiket tidak ditemukan',
            ], 404);
        }

        if ($ticket->status === 'closed') {
            return response()->json([
                'status'  => false,
                'message' => 'Tiket ini sudah ditutup. Silakan buat tiket baru kalau masih butuh bantuan.',
            ], 422);
        }

        $data = $request->validate([
            'message'           => 'required_without:attachment_base64|nullable|string|max:5000',
            'attachment_base64' => 'nullable|string',
        ], [
            'message.required_without' => 'Pesan wajib diisi.',
            'message.max'              => 'Pesan maksimal 5000 karakter.',
        ]);

        $path = null;
        if (! empty($data['attachment_base64'])) {
            $path = $this->storeImage($data['attachment_base64'], $ticket->id);

            if ($path === false) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Lampiran harus gambar JPG/PNG dengan ukuran maksimal 3 MB.',
                ], 422);
            }
        }

        $reply = SupportTicketReply::create([
            'ticket_id'        => $ticket->id,
            'user_id'          => $user->id,
            'message'          => trim((string) ($data['message'] ?? '')) ?: '(Lampiran gambar)',
            'attachment'       => $path,
            'is_internal_note' => false,
        ]);

        // Balasan user → tiket kembali menunggu balasan tim
        if ($ticket->status !== 'open') {
            $ticket->status      = 'open';
            $ticket->resolved_at = null;
        }
        $ticket->touch();
        $ticket->save();

        $reply->setRelation('user', $user);

        return response()->json([
            'status'  => true,
            'message' => 'Balasan terkirim',
            'data'    => HelpCenterFormat::reply($reply, $ticket, $user->id),
        ], 201);
    }

    /** @return string|false|null path di disk public */
    private function storeImage(string $base64, int $ticketId)
    {
        if (str_contains($base64, ',')) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $binary = base64_decode($base64, true);
        if ($binary === false || strlen($binary) > 3 * 1024 * 1024) {
            return false;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;

        if (! $ext) {
            return false;
        }

        $path = "support-tickets/{$ticketId}/" . Str::random(20) . ".{$ext}";
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}