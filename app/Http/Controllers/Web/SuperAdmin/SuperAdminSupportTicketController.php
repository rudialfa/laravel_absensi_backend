<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Services\Notifiers\SupportTicketNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tiket Bantuan dari semua tenant (support_tickets + support_ticket_replies).
 *
 * Tiket dibuat user tenant lewat aplikasi (Api\Public\SupportTicketController).
 * Superadmin/staff: balas (bisa lampiran & catatan internal), ubah status, prioritas & kategori,
 * tugaskan ke staff, hapus tiket spam, dan export.
 */
class SuperAdminSupportTicketController extends Controller
{
    public const STATUSES = [
        'open'        => 'Baru',
        'in_progress' => 'Diproses',
        'resolved'    => 'Selesai',
        'closed'      => 'Ditutup',
    ];
    public const STATUS_BADGE = ['open' => 'info', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary'];

    public const PRIORITIES = ['high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah'];
    public const PRIORITY_BADGE = ['high' => 'danger', 'medium' => 'warning', 'low' => 'light'];

    public const CATEGORIES = [
        'billing'   => 'Tagihan & Langganan',
        'technical' => 'Kendala Teknis',
        'account'   => 'Akun & Login',
        'other'     => 'Lainnya',
    ];

    /** Tiket aktif yang belum dibalas staff lebih dari ini dianggap terlambat (jam) */
    public const SLA_HOURS = 24;

    public function __construct(private SupportTicketNotifier $notifier) {}

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $staffIds = $this->staff()->pluck('id');

        $tickets = $this->query($request)
            ->with(['company:id,name,type', 'user:id,name,email,role', 'assignedTo:id,name'])
            ->withCount(['replies as public_replies_count' => fn($q) => $q->where('is_internal_note', false)])
            ->addSelect([
                // Siapa yang terakhir membalas (bukan catatan internal) → untuk "menunggu balasan kita"
                'last_reply_user_id' => SupportTicketReply::select('user_id')
                    ->whereColumn('support_ticket_replies.ticket_id', 'support_tickets.id')
                    ->where('is_internal_note', false)
                    ->latest('id')->limit(1),
                'last_reply_at' => SupportTicketReply::select('created_at')
                    ->whereColumn('support_ticket_replies.ticket_id', 'support_tickets.id')
                    ->where('is_internal_note', false)
                    ->latest('id')->limit(1),
            ])
            ->orderByRaw("FIELD(status, 'open', 'in_progress', 'resolved', 'closed')")
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->latest('updated_at')
            ->paginate(20)->withQueryString();

        $counts = SupportTicket::query()->toBase()
            ->selectRaw("count(*) as total,
                sum(status = 'open') as open,
                sum(status = 'in_progress') as in_progress,
                sum(status in ('open','in_progress') and priority = 'high') as high,
                sum(status in ('open','in_progress') and assigned_to is null) as unassigned,
                sum(status in ('open','in_progress') and assigned_to = ?) as mine", [auth()->id()])
            ->first();

        return view('pages.superadmin.support-tickets.index', [
            'tickets'       => $tickets,
            'counts'        => $counts,
            'staffIds'      => $staffIds,
            'staff'         => $this->staff()->get(['id', 'name']),
            'companies'     => Company::whereIn('id', SupportTicket::whereNotNull('company_id')->select('company_id'))->orderBy('name')->get(['id', 'name']),
            'statuses'      => self::STATUSES,
            'statusBadge'   => self::STATUS_BADGE,
            'priorities'    => self::PRIORITIES,
            'priorityBadge' => self::PRIORITY_BADGE,
            'categories'    => self::CATEGORIES,
        ]);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show(string $id)
    {
        $ticket = SupportTicket::with(['company', 'user:id,name,email,phone,role,company_id', 'assignedTo:id,name'])->findOrFail($id);

        $replies = $ticket->replies()->with('user:id,name,role')->oldest()->get();

        return view('pages.superadmin.support-tickets.show', [
            'ticket'        => $ticket,
            'replies'       => $replies,
            'staff'         => $this->staff()->get(['id', 'name']),
            'otherTickets'  => SupportTicket::where('user_id', $ticket->user_id)->where('id', '!=', $ticket->id)->latest()->limit(5)->get(['id', 'subject', 'status', 'created_at']),
            'statuses'      => self::STATUSES,
            'statusBadge'   => self::STATUS_BADGE,
            'priorities'    => self::PRIORITIES,
            'priorityBadge' => self::PRIORITY_BADGE,
            'categories'    => self::CATEGORIES,
        ]);
    }

    // =========================================================
    // BALAS (dengan lampiran opsional)
    // =========================================================
    public function reply(Request $request, string $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'message'          => 'required|string|max:5000',
            'attachment'       => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:4096',
            'is_internal_note' => 'nullable|boolean',
            'set_status'       => ['nullable', Rule::in(array_keys(self::STATUSES))],
        ], [], ['message' => 'balasan', 'attachment' => 'lampiran']);

        $internal = $request->boolean('is_internal_note');
        $path = $request->hasFile('attachment')
            ? $request->file('attachment')->store('support-tickets/' . $ticket->id, 'public')
            : null;

        $oldStatus = $ticket->status;

        $reply = DB::transaction(function () use ($ticket, $data, $internal, $path, $request) {
            $reply = SupportTicketReply::create([
                'ticket_id'        => $ticket->id,
                'user_id'          => auth()->id(),
                'message'          => $data['message'],
                'attachment'       => $path,
                'is_internal_note' => $internal,
            ]);

            // Status: pakai pilihan staff kalau ada; kalau tidak, balasan publik pada tiket baru → diproses
            if (!empty($data['set_status'])) {
                $ticket->status = $data['set_status'];
            } elseif (!$internal && $ticket->status === 'open') {
                $ticket->status = 'in_progress';
            }

            $ticket->resolved_at = $ticket->status === 'resolved' ? ($ticket->resolved_at ?? now()) : (in_array($ticket->status, ['open', 'in_progress'], true) ? null : $ticket->resolved_at);

            // Ambil tiket kalau belum ditugaskan
            if (!$ticket->assigned_to && !$internal) {
                $ticket->assigned_to = auth()->id();
            }

            $ticket->updated_at = now();
            $ticket->save();

            return $reply;
        });

        // 🔔 Notifikasi ke pemilik tiket — catatan internal tidak dikirim
        if (!$internal) {
            try {
                $this->notifier->replied($ticket, $reply);
                if ($oldStatus !== $ticket->status) {
                    $this->notifier->statusChanged($ticket, $oldStatus);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        AuditLog::record(
            $internal ? 'note_support_ticket' : 'reply_support_ticket',
            $ticket,
            ($internal ? 'Catatan internal' : 'Membalas') . " tiket #{$ticket->id}: \"{$ticket->subject}\"",
            ['status_before' => $oldStatus, 'status_after' => $ticket->status, 'attachment' => (bool) $path]
        );

        return back()->with('success', $internal ? 'Catatan internal disimpan.' : 'Balasan terkirim ke pengguna.');
    }

    // =========================================================
    // UBAH STATUS
    // =========================================================
    public function updateStatus(Request $request, string $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(self::STATUSES))]]);

        $oldStatus = $ticket->status;
        if ($oldStatus === $data['status']) {
            return back()->with('success', 'Status tidak berubah.');
        }

        $ticket->status = $data['status'];
        if ($data['status'] === 'resolved' && !$ticket->resolved_at) {
            $ticket->resolved_at = now();
        }
        if (in_array($data['status'], ['open', 'in_progress'], true)) {
            $ticket->resolved_at = null;
        }
        $ticket->save();

        try {
            $this->notifier->statusChanged($ticket, $oldStatus);
        } catch (\Throwable $e) {
            report($e);
        }

        AuditLog::record('update_ticket_status', $ticket, "Status tiket #{$ticket->id}: {$oldStatus} → {$data['status']}", ['before' => $oldStatus, 'after' => $data['status']]);

        return back()->with('success', 'Status tiket diperbarui menjadi ' . self::STATUSES[$data['status']] . '.');
    }

    // =========================================================
    // PRIORITAS & KATEGORI
    // =========================================================
    public function update(Request $request, string $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'priority' => ['required', Rule::in(array_keys(self::PRIORITIES))],
            'category' => ['required', 'string', 'max:50'],
        ]);

        $before = $ticket->only(['priority', 'category']);
        $ticket->forceFill($data)->save();

        AuditLog::record('update_ticket_meta', $ticket, "Prioritas/kategori tiket #{$ticket->id} diubah", ['before' => $before, 'after' => $data]);

        return back()->with('success', 'Prioritas & kategori tiket diperbarui.');
    }

    // =========================================================
    // TUGASKAN
    // =========================================================
    public function assign(Request $request, string $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            // Hanya staff internal (superadmin tanpa tenant). Kosong = lepas penugasan.
            'assigned_to' => ['nullable', Rule::in($this->staff()->pluck('id')->all())],
        ], ['assigned_to.in' => 'Hanya bisa ditugaskan ke staff internal.']);

        $ticket->assigned_to = $data['assigned_to'] ?? null;
        $ticket->save();

        $staff = $ticket->assigned_to ? User::find($ticket->assigned_to) : null;

        AuditLog::record('assign_ticket', $ticket, $staff ? "Tiket #{$ticket->id} ditugaskan ke {$staff->name}" : "Penugasan tiket #{$ticket->id} dilepas");

        return back()->with('success', $staff ? "Tiket ditugaskan ke {$staff->name}." : 'Penugasan dilepas.');
    }

    // =========================================================
    // HAPUS (spam / ganda)
    // =========================================================
    public function destroy(Request $request, string $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $ticket = SupportTicket::with('replies')->findOrFail($id);
        $files = $ticket->replies->pluck('attachment')->filter()->all();
        $snapshot = $ticket->getAttributes() + ['replies_count' => $ticket->replies->count()];

        DB::transaction(fn() => $ticket->delete()); // balasan ikut terhapus (cascade)

        foreach ($files as $file) {
            if (!preg_match('#^https?://#i', $file)) {
                try {
                    Storage::disk('public')->delete($file);
                } catch (\Throwable) {
                }
            }
        }

        AuditLog::record('delete_support_ticket', null, "Tiket #{$snapshot['id']} \"{$ticket->subject}\" dihapus. Alasan: {$data['reason']}", $snapshot + ['reason' => $data['reason']]);

        return redirect()->route('superadmin.support-tickets.index')->with('success', 'Tiket dihapus.');
    }

    // =========================================================
    // EXPORT
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        AuditLog::record('export_support_tickets', null, 'Export tiket bantuan ke CSV', ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['No', 'Dibuat', 'Tenant', 'Pengirim', 'Subjek', 'Kategori', 'Prioritas', 'Status', 'Ditangani', 'Balasan', 'Selesai', 'Lama selesai (jam)'], ';');

            $this->query($request)->with(['company:id,name', 'user:id,name', 'assignedTo:id,name'])
                ->withCount(['replies as public_replies_count' => fn($q) => $q->where('is_internal_note', false)])
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $t) {
                        fputcsv($out, [
                            $t->id,
                            optional($t->created_at)->format('Y-m-d H:i'),
                            $t->company->name ?? '-',
                            $t->user->name ?? '-',
                            $t->subject,
                            self::CATEGORIES[$t->category] ?? $t->category,
                            self::PRIORITIES[$t->priority] ?? $t->priority,
                            self::STATUSES[$t->status] ?? $t->status,
                            $t->assignedTo->name ?? '',
                            $t->public_replies_count,
                            $t->resolved_at ? \Carbon\Carbon::parse($t->resolved_at)->format('Y-m-d H:i') : '',
                            $t->resolved_at ? round($t->created_at->diffInMinutes(\Carbon\Carbon::parse($t->resolved_at)) / 60, 1) : '',
                        ], ';');
                    }
                });

            fclose($out);
        }, 'tiket-bantuan-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // HELPER
    // =========================================================
    private function query(Request $request)
    {
        return SupportTicket::query()
            ->when($request->status === 'active', fn($q) => $q->whereIn('status', ['open', 'in_progress']))
            ->when($request->status && $request->status !== 'active', fn($q) => $q->where('status', $request->status))
            ->when($request->priority, fn($q, $p) => $q->where('priority', $p))
            ->when($request->category, fn($q, $c) => $q->where('category', $c))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->assigned === 'me', fn($q) => $q->where('assigned_to', auth()->id()))
            ->when($request->assigned === 'none', fn($q) => $q->whereNull('assigned_to'))
            ->when(ctype_digit((string) $request->assigned), fn($q) => $q->where('assigned_to', $request->assigned))
            // Tiket aktif yang balasan terakhirnya dari pengguna (atau belum dibalas sama sekali)
            ->when($request->waiting === '1', fn($q) => $q->whereIn('status', ['open', 'in_progress'])
                ->whereRaw('coalesce((select r.user_id from support_ticket_replies r where r.ticket_id = support_tickets.id and r.is_internal_note = 0 order by r.id desc limit 1), support_tickets.user_id) = support_tickets.user_id'))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->where(fn($w) => $w->where('subject', 'like', $like)->orWhere('message', 'like', $like)
                    ->orWhere('id', ltrim($s, '#'))
                    ->orWhereHas('company', fn($c) => $c->where('name', 'like', $like))
                    ->orWhereHas('user', fn($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            });
    }

    /** Staff internal = akun superadmin tanpa tenant (lihat menu Staff) */
    private function staff()
    {
        return User::where('role', 'superadmin')->whereNull('company_id')->where('is_active', true)->orderBy('name');
    }

    public static function fileUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return preg_match('#^https?://#i', $path) ? $path : Storage::disk('public')->url($path);
    }
}
