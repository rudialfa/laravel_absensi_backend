<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;

/**
 * Inbox / notifikasi pribadi untuk navbar (lonceng).
 *
 *  - inbox()        : daftar semua notifikasi milik user yang login (filter: semua / belum dibaca, kategori).
 *  - read()         : tandai 1 notifikasi sudah dibaca (hanya miliknya sendiri).
 *  - readAll()      : tandai semua notifikasi belum dibaca sebagai sudah dibaca.
 *
 * Tidak mengubah atau menghapus data lain. Notifikasi tetap dibuat oleh NotificationService.
 */
class SuperAdminHeaderController extends Controller
{
    private const CATEGORIES = [
        'chat',
        'approval',
        'reminder',
        'attendance',
        'schedule',
        'holiday',
        'report',
        'payroll',
        'school',
        'subscription',
        'system',
    ];

    public function inbox(Request $request)
    {
        $userId = $request->user()->id;
        $filter = $request->input('filter') === 'unread' ? 'unread' : 'all';
        $category = in_array($request->input('category'), self::CATEGORIES, true) ? $request->input('category') : null;

        $items = AppNotification::where('user_id', $userId)
            ->when($filter === 'unread', fn($q) => $q->whereNull('read_at'))
            ->when($category, fn($q) => $q->where('category', $category))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('pages.superadmin.inbox.index', [
            'items'      => $items,
            'filter'     => $filter,
            'category'   => $category,
            'categories' => self::CATEGORIES,
            'unread'     => AppNotification::where('user_id', $userId)->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, int $id)
    {
        $item = AppNotification::where('user_id', $request->user()->id)->findOrFail($id);

        if (!$item->read_at) {
            $item->forceFill(['read_at' => now()])->save();
        }

        return back();
    }

    public function readAll(Request $request)
    {
        $count = AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', $count ? "{$count} notifikasi ditandai sudah dibaca." : 'Tidak ada notifikasi yang belum dibaca.');
    }
}
