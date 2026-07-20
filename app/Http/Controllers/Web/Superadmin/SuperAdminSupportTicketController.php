<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Http\Request;

class SuperAdminSupportTicketController extends Controller
{
    /**
     * List semua tiket bantuan dari semua tenant.
     */
    public function index(Request $request)
    {
        $query = SupportTicket::with(['company', 'user', 'assignedTo'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhereHas('company', fn($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $tickets = $query->paginate(15)->withQueryString();

        return view('pages.superadmin.support-tickets.index', compact('tickets'));
    }

    /**
     * Detail 1 tiket + histori balasan.
     */
    public function show(string $id)
    {
        $ticket = SupportTicket::with(['company', 'user', 'assignedTo'])->findOrFail($id);

        $replies = $ticket->replies()->with('user')->oldest()->get();

        // Untuk dropdown assign — staff internal superadmin
        $staff = User::where('role', 'superadmin')->orWhere('role', 'staff')->get();

        return view('pages.superadmin.support-tickets.show', compact('ticket', 'replies', 'staff'));
    }

    /**
     * Superadmin/staff membalas tiket.
     */
    public function reply(Request $request, string $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|string',
            'is_internal_note' => 'boolean',
        ]);

        SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $data['message'],
            'attachment' => $data['attachment'] ?? null,
            'is_internal_note' => $request->boolean('is_internal_note'),
        ]);

        // Balasan dari superadmin otomatis pindahkan status ke in_progress kalau masih open
        if ($ticket->status === 'open') {
            $ticket->status = 'in_progress';
            $ticket->save();
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'reply_support_ticket',
            'subject_type' => SupportTicket::class,
            'subject_id' => $ticket->id,
            'description' => "Membalas tiket #{$ticket->id}: \"{$ticket->subject}\"",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Balasan berhasil dikirim.');
    }

    /**
     * Ubah status tiket (resolved / closed / reopen ke in_progress).
     */
    public function updateStatus(Request $request, string $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $oldStatus = $ticket->status;
        $ticket->status = $data['status'];

        if ($data['status'] === 'resolved' && ! $ticket->resolved_at) {
            $ticket->resolved_at = now();
        }

        $ticket->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'update_ticket_status',
            'subject_type' => SupportTicket::class,
            'subject_id' => $ticket->id,
            'description' => "Mengubah status tiket #{$ticket->id} dari {$oldStatus} ke {$data['status']}",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Status tiket berhasil diperbarui.');
    }

    /**
     * Assign tiket ke staff/superadmin tertentu.
     */
    public function assign(Request $request, string $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $data = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket->assigned_to = $data['assigned_to'];
        $ticket->save();

        $staff = User::find($data['assigned_to']);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'assign_ticket',
            'subject_type' => SupportTicket::class,
            'subject_id' => $ticket->id,
            'description' => "Menugaskan tiket #{$ticket->id} ke {$staff?->name}",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Tiket berhasil ditugaskan.');
    }
}
