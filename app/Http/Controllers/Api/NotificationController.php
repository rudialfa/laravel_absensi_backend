<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;

/**
 * Kotak masuk notifikasi — dipakai SEMUA role (employee, hr, guru, wali, dst).
 * Setiap user hanya bisa melihat notifikasinya sendiri.
 */
class NotificationController extends Controller
{
    private function myQuery()
    {
        return AppNotification::query()->where('user_id', auth()->id());
    }

    // ============================================================
    // GET /api/notifications
    // filter: category, unread=1, per_page
    // ============================================================
    public function index(Request $request)
    {
        $q = $this->myQuery();

        if ($request->filled('category')) {
            $q->where('category', $request->category);
        }

        if ($request->boolean('unread')) {
            $q->whereNull('read_at');
        }

        $page = $q->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min((int) $request->get('per_page', 20), 50));

        $page->getCollection()->transform(fn($n) => $n->toApiArray());

        return response()->json([
            'status'       => true,
            'message'      => 'Daftar notifikasi',
            'unread_count' => $this->myQuery()->whereNull('read_at')->count(),
            'data'         => $page,
        ]);
    }

    // ============================================================
    // GET /api/notifications/unread-count
    // Untuk badge lonceng — panggil saat aplikasi dibuka / ada push masuk
    // ============================================================
    public function unreadCount()
    {
        $byCategory = $this->myQuery()
            ->whereNull('read_at')
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn($v) => (int) $v);

        return response()->json([
            'status' => true,
            'data'   => [
                'total'       => (int) $byCategory->sum(),
                'by_category' => $byCategory,
            ],
        ]);
    }

    // ============================================================
    // PATCH /api/notifications/{id}/read
    // ============================================================
    public function markRead($id)
    {
        $notif = $this->myQuery()->findOrFail($id);

        if (!$notif->read_at) {
            $notif->update(['read_at' => now()]);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Notifikasi ditandai sudah dibaca',
            'data'    => $notif->fresh()->toApiArray(),
        ]);
    }

    // ============================================================
    // PATCH /api/notifications/read-all
    // body opsional: { category }
    // ============================================================
    public function markAllRead(Request $request)
    {
        $count = $this->myQuery()
            ->whereNull('read_at')
            ->when($request->filled('category'), fn($q) => $q->where('category', $request->category))
            ->update(['read_at' => now()]);

        return response()->json([
            'status'  => true,
            'message' => "{$count} notifikasi ditandai sudah dibaca",
            'data'    => ['updated' => $count],
        ]);
    }

    // ============================================================
    // DELETE /api/notifications/{id}
    // ============================================================
    public function destroy($id)
    {
        $this->myQuery()->findOrFail($id)->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Notifikasi dihapus',
        ]);
    }
}
