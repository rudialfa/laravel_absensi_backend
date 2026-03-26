<?php

namespace App\Http\Controllers\Api\ChatMessage;

use App\Events\ChatMessageDeleted as ChatMessageDeletedEvent;
use App\Events\ChatMessageRead as ChatMessageReadEvent;
use App\Events\NewChatMessage;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatMessageRead;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    // ============================================================
    // 1. LIST CONVERSATIONS (inbox)
    //    GET /api/chat/conversations?page=1
    // ============================================================
    public function conversations(Request $request): JsonResponse
    {
        $me = $request->user();

        $conversations = ChatConversation::with([
            'userOne:id,name,image_url,position',
            'userTwo:id,name,image_url,position',
            'lastMessage.attachments',
            'lastMessage.reads',
        ])
            ->where('company_id', $me->company_id)
            ->where(function ($q) use ($me) {
                $q->where('user_one_id', $me->id)
                    ->orWhere('user_two_id', $me->id);
            })
            ->orderByDesc(function ($q) {
                $q->select('created_at')
                    ->from('chat_messages')
                    ->whereColumn('conversation_id', 'chat_conversations.id')
                    ->latest()
                    ->limit(1);
            })
            ->paginate(20);

        $data = $conversations->getCollection()->map(function ($conv) use ($me) {
            $other = $conv->getOtherUser($me->id);

            return [
                'id'           => $conv->id,
                'other_user'   => [
                    'id'         => $other->id,
                    'name'       => $other->name,
                    'image_url'  => $other->image_url,
                    'position'   => $other->position,
                    'department' => $other->department,
                    'role'       => $other->role,
                ],
                'last_message' => $conv->lastMessage
                    ? $this->formatMessage($conv->lastMessage, $me->id)
                    : null,
                'unread_count' => $conv->unreadCountFor($me->id),
                'updated_at'   => $conv->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'current_page' => $conversations->currentPage(),
                'last_page'    => $conversations->lastPage(),
                'total'        => $conversations->total(),
            ],
        ]);
    }

    // ============================================================
    // 2. BUKA / MULAI PERCAKAPAN
    //    POST /api/chat/conversations
    //    body: { target_user_id }
    // ============================================================
    public function openConversation(Request $request): JsonResponse
    {
        $me = $request->user();

        $validator = Validator::make($request->all(), [
            'target_user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $targetId = (int) $request->target_user_id;

        if ($targetId === $me->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa chat dengan diri sendiri.',
            ], 400);
        }

        $target = User::where('id', $targetId)
            ->where('company_id', $me->company_id)
            ->first();

        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan di perusahaan ini.',
            ], 404);
        }

        $conversation = ChatConversation::getOrCreate($me->company_id, $me->id, $targetId);

        return response()->json([
            'success' => true,
            'data'    => [
                'conversation_id' => $conversation->id,
                'other_user'      => [
                    'id'         => $target->id,
                    'name'       => $target->name,
                    'image_url'  => $target->image_url,
                    'position'   => $target->position,
                    'department' => $target->department,
                    'role'       => $target->role,
                ],
            ],
        ]);
    }

    // ============================================================
    // 3. LIST PESAN DALAM CONVERSATION
    //    GET /api/chat/conversations/{id}/messages?page=1
    //    Auto mark as read + broadcast read receipt ke pengirim
    // ============================================================
    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $me   = $request->user();
        $conv = $this->findConversation($conversationId, $me->id);

        if (!$conv) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation tidak ditemukan.',
            ], 404);
        }

        // Auto mark as read + broadcast read receipt ke pengirim asli
        $this->markAllAsRead($conv, $me->id);

        $messages = ChatMessage::with([
            'sender:id,name,image_url',
            'attachments',
            'reads',
            'replyTo.sender:id,name',
            'replyTo.attachments',
        ])
            ->where('conversation_id', $conversationId)
            ->whereNull('deleted_for_everyone_at')
            ->latest()
            ->paginate(30);

        $data = $messages->getCollection()
            ->map(fn($msg) => $this->formatMessage($msg, $me->id))
            ->reverse()
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'total'        => $messages->total(),
            ],
        ]);
    }

    // ============================================================
    // 4. KIRIM PESAN (text / image / video)
    //    POST /api/chat/conversations/{id}/messages
    //    body: { body?, reply_to_id?, files[] }
    // ============================================================
    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        $me   = $request->user();
        $conv = $this->findConversation($conversationId, $me->id);

        if (!$conv) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'body'        => 'nullable|string|max:5000',
            'reply_to_id' => 'nullable|integer|exists:chat_messages,id',
            'files'       => 'nullable|array|max:10',
            'files.*'     => 'file|max:102400|mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mkv,3gp',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (empty($request->body) && !$request->hasFile('files')) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan tidak boleh kosong.',
            ], 422);
        }

        // Tentukan tipe pesan berdasarkan file yang diupload
        $type = 'text';
        if ($request->hasFile('files')) {
            $mime  = $request->file('files')[0]->getMimeType();
            $type  = str_starts_with($mime, 'video/') ? 'video' : 'image';
        }

        DB::beginTransaction();
        try {
            $message = ChatMessage::create([
                'conversation_id' => $conversationId,
                'sender_id'       => $me->id,
                'type'            => $type,
                'body'            => $request->body,
                'reply_to_id'     => $request->reply_to_id,
            ]);

            if ($request->hasFile('files')) {
                $this->handleFileUploads($request->file('files'), $message);
            }

            // Update preview pesan terakhir di inbox
            $conv->update(['last_message_id' => $message->id]);

            DB::commit();

            $message->load([
                'sender:id,name,image_url',
                'attachments',
                'replyTo.sender:id,name',
            ]);

            // Broadcast ke lawan bicara via Reverb
            // ShouldBroadcastNow = langsung tanpa queue delay
            broadcast(new NewChatMessage($message));

            return response()->json([
                'success' => true,
                'message' => 'Pesan terkirim.',
                'data'    => $this->formatMessage($message, $me->id),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim pesan: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // 5. EDIT PESAN
    //    PUT /api/chat/messages/{id}
    //    body: { body }
    //    Hanya teks, hanya pengirim, max 15 menit
    // ============================================================
    public function editMessage(Request $request, int $messageId): JsonResponse
    {
        $me  = $request->user();
        $msg = ChatMessage::find($messageId);

        if (!$msg || $msg->sender_id !== $me->id) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan tidak ditemukan.',
            ], 404);
        }

        if ($msg->isDeletedForEveryone()) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan sudah dihapus.',
            ], 400);
        }

        if ($msg->created_at->diffInMinutes(now()) > 15) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa mengedit pesan lebih dari 15 menit yang lalu.',
            ], 400);
        }

        if ($msg->type !== 'text') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pesan teks yang bisa diedit.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $msg->update([
            'body'      => $request->body,
            'edited_at' => now(),
        ]);

        $msg->load(['sender:id,name,image_url', 'attachments', 'reads']);

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil diedit.',
            'data'    => $this->formatMessage($msg, $me->id),
        ]);
    }

    // ============================================================
    // 6. HAPUS PESAN
    //    DELETE /api/chat/messages/{id}
    //    body: { delete_for_everyone?: bool }
    // ============================================================
    public function deleteMessage(Request $request, int $messageId): JsonResponse
    {
        $me  = $request->user();
        $msg = ChatMessage::find($messageId);

        if (!$msg || $msg->sender_id !== $me->id) {
            return response()->json([
                'success' => false,
                'message' => 'Pesan tidak ditemukan.',
            ], 404);
        }

        $forEveryone = (bool) $request->input('delete_for_everyone', false);

        if ($forEveryone) {
            if ($msg->created_at->diffInHours(now()) > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batas waktu hapus untuk semua sudah lewat (1 jam).',
                ], 400);
            }

            $msg->update(['deleted_for_everyone_at' => now()]);

            // Broadcast ke lawan bicara agar bubble langsung berubah jadi 🚫
            broadcast(new ChatMessageDeletedEvent(
                conversationId: $msg->conversation_id,
                messageId: $msg->id,
            ));

            return response()->json([
                'success' => true,
                'message' => 'Pesan dihapus untuk semua.',
            ]);
        }

        // Hapus hanya untuk pengirim (tidak perlu broadcast)
        $msg->update(['deleted_at_sender' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Pesan dihapus dari chat Anda.',
        ]);
    }

    // ============================================================
    // 7. MARK AS READ (manual trigger)
    //    POST /api/chat/conversations/{id}/read
    // ============================================================
    public function markAsRead(Request $request, int $conversationId): JsonResponse
    {
        $me   = $request->user();
        $conv = $this->findConversation($conversationId, $me->id);

        if (!$conv) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation tidak ditemukan.',
            ], 404);
        }

        $count = $this->markAllAsRead($conv, $me->id);

        return response()->json([
            'success' => true,
            'message' => "{$count} pesan ditandai sudah dibaca.",
        ]);
    }

    // ============================================================
    // 8. LIST USER YANG BISA DIAJAK CHAT
    //    GET /api/chat/users?search=keyword
    // ============================================================
    public function listUsers(Request $request): JsonResponse
    {
        $me     = $request->user();
        $search = $request->query('search');

        $users = User::where('company_id', $me->company_id)
            ->where('id', '!=', $me->id)
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->select('id', 'name', 'image_url', 'position', 'department', 'role')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    /**
     * Cari conversation yang dimiliki oleh user tertentu
     */
    private function findConversation(int $conversationId, int $userId): ?ChatConversation
    {
        return ChatConversation::where('id', $conversationId)
            ->where(function ($q) use ($userId) {
                $q->where('user_one_id', $userId)
                    ->orWhere('user_two_id', $userId);
            })
            ->first();
    }

    /**
     * Mark semua pesan yang belum dibaca sebagai sudah dibaca
     * + broadcast read receipt ke pengirim asli via Reverb
     */
    private function markAllAsRead(ChatConversation $conv, int $userId): int
    {
        $unreadMessages = ChatMessage::where('conversation_id', $conv->id)
            ->where('sender_id', '!=', $userId)
            ->whereNull('deleted_for_everyone_at')
            ->whereDoesntHave('reads', fn($q) => $q->where('user_id', $userId))
            ->get();

        if ($unreadMessages->isEmpty()) {
            return 0;
        }

        $inserts = $unreadMessages->map(fn($msg) => [
            'message_id' => $msg->id,
            'user_id'    => $userId,
            'read_at'    => now(),
        ])->toArray();

        ChatMessageRead::insertOrIgnore($inserts);

        // Broadcast read receipt ke pengirim asli
        // Pengirim akan terima event → centang berubah jadi ✓✓ biru
        broadcast(new ChatMessageReadEvent(
            conversationId: $conv->id,
            readByUserId: $userId,
            messageIds: $unreadMessages->pluck('id')->toArray(),
        ));

        return count($inserts);
    }

    /**
     * Upload file attachment (gambar / video)
     */
    private function handleFileUploads(array $files, ChatMessage $message): void
    {
        foreach ($files as $index => $file) {
            $mime    = $file->getMimeType();
            $isVideo = str_starts_with($mime, 'video/');
            $folder  = $isVideo ? 'image/chat-message/videos' : 'image/chat-message';
            $path    = $file->store($folder, 'public');
            $url     = Storage::url($path);

            ChatMessageAttachment::create([
                'message_id'    => $message->id,
                'type'          => $isVideo ? 'video' : 'image',
                'file_path'     => $path,
                'file_url'      => $url,
                'original_name' => $file->getClientOriginalName(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $mime,
                'sort_order'    => $index,
            ]);
        }
    }

    /**
     * Format satu pesan jadi array response
     */
    private function formatMessage(ChatMessage $msg, int $myUserId): array
    {
        // Pesan sendiri: cek apakah sudah dibaca lawan
        // Pesan lawan: cek apakah sudah saya baca
        $isRead = $msg->sender_id === $myUserId
            ? $msg->reads->isNotEmpty()
            : $msg->reads->where('user_id', $myUserId)->isNotEmpty();

        return [
            'id'              => $msg->id,
            'conversation_id' => $msg->conversation_id,
            'sender'          => $msg->sender ? [
                'id'        => $msg->sender->id,
                'name'      => $msg->sender->name,
                'image_url' => $msg->sender->image_url,
            ] : null,
            'is_mine'         => $msg->sender_id === $myUserId,
            'type'            => $msg->type,
            'body'            => $msg->isDeletedForEveryone() ? null : $msg->body,
            'is_deleted'      => $msg->isDeletedForEveryone(),
            'is_edited'       => $msg->edited_at !== null,
            'edited_at'       => $msg->edited_at,
            'attachments'     => $msg->isDeletedForEveryone()
                ? []
                : $msg->attachments->map(fn($a) => [
                    'id'               => $a->id,
                    'type'             => $a->type,
                    'file_url'         => $a->file_url,
                    'thumbnail_url'    => $a->thumbnail_url,
                    'original_name'    => $a->original_name,
                    'file_size'        => $a->file_size,
                    'file_size_label'  => $a->file_size_formatted,
                    'mime_type'        => $a->mime_type,
                    'duration_seconds' => $a->duration_seconds,
                ])->values(),
            'reply_to'        => $msg->replyTo ? [
                'id'     => $msg->replyTo->id,
                'body'   => $msg->replyTo->isDeletedForEveryone() ? null : $msg->replyTo->body,
                'sender' => $msg->replyTo->sender?->name,
            ] : null,
            'is_read'         => $isRead,
            'read_at'         => $msg->reads->where('user_id', '!=', $myUserId)->first()?->read_at,
            'created_at'      => $msg->created_at,
        ];
    }
}
