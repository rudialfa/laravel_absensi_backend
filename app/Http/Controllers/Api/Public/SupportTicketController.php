<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    /**
     * List tiket bantuan milik user yang sedang login.
     * GET /api/support-tickets?status=open&page=1
     */
    public function index(Request $request)
    {
        $query = SupportTicket::with(['assignedTo'])
            ->where('user_id', auth()->id())
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil daftar tiket bantuan',
            'data' => $tickets,
        ]);
    }

    /**
     * Buat tiket bantuan baru.
     * POST /api/support-tickets
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'category' => 'nullable|string|max:100',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        $ticket = SupportTicket::create([
            'company_id' => auth()->user()->company_id,
            'user_id' => auth()->id(),
            'subject' => $data['subject'],
            'message' => $data['message'],
            'category' => $data['category'] ?? 'other',
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Tiket bantuan berhasil dikirim',
            'data' => $ticket,
        ], 201);
    }

    /**
     * Detail 1 tiket milik sendiri + histori balasan.
     * GET /api/support-tickets/{id}
     */
    public function show(int $id)
    {
        $ticket = SupportTicket::with(['assignedTo'])
            ->where('user_id', auth()->id())
            ->find($id);

        if (! $ticket) {
            return response()->json([
                'status' => false,
                'message' => 'Tiket tidak ditemukan',
            ], 404);
        }

        // Balasan internal antar staff superadmin tidak boleh terlihat oleh user
        $replies = $ticket->replies()
            ->with('user')
            ->where('is_internal_note', false)
            ->oldest()
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil detail tiket',
            'data' => [
                'ticket' => $ticket,
                'replies' => $replies,
            ],
        ]);
    }

    /**
     * Balas tiket milik sendiri (menambah pesan baru dalam percakapan).
     * POST /api/support-tickets/{id}/reply
     */
    public function reply(Request $request, int $id)
    {
        $ticket = SupportTicket::where('user_id', auth()->id())->find($id);

        if (! $ticket) {
            return response()->json([
                'status' => false,
                'message' => 'Tiket tidak ditemukan',
            ], 404);
        }

        if (in_array($ticket->status, ['resolved', 'closed'])) {
            return response()->json([
                'status' => false,
                'message' => 'Tiket ini sudah ditutup, silakan buat tiket baru kalau masih butuh bantuan.',
            ], 422);
        }

        $data = $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|string',
        ]);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $data['message'],
            'attachment' => $data['attachment'] ?? null,
            'is_internal_note' => false,
        ]);

        // Reopen tiket kalau sebelumnya sedang in_progress dan user balas lagi
        if ($ticket->status === 'in_progress') {
            $ticket->status = 'open';
            $ticket->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Balasan berhasil dikirim',
            'data' => $reply,
        ], 201);
    }
}
