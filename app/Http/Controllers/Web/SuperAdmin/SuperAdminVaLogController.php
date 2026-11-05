<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\VaPaymentLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Log callback/webhook dari bank & Midtrans (tabel va_payment_logs).
 * Hanya lihat — log adalah bukti, tidak boleh diedit. Yang bisa dihapus hanya log lama (pembersihan).
 */
class SuperAdminVaLogController extends Controller
{
    public const EVENTS = [
        'payment'        => 'Pembayaran (callback)',
        'inquiry'        => 'Inquiry tagihan',
        'inquiry_status' => 'Cek status',
    ];

    /** Log lebih baru dari ini tidak boleh dihapus */
    private const MIN_KEEP_DAYS = 30;

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $logs = $this->filteredQuery($request)
            ->with(['vaPayment:id,invoice_id,va_number,company_id', 'vaPayment.invoice:id,invoice_number', 'vaPayment.company:id,name'])
            ->orderByDesc('received_at')
            ->paginate(30)
            ->withQueryString();

        $since = now()->subDay();

        return view('pages.superadmin.va-logs.index', [
            'logs'    => $logs,
            'events'  => self::EVENTS,
            'banks'   => VaPaymentLog::select('bank')->distinct()->orderBy('bank')->pluck('bank'),
            'stats'   => [
                'today'     => VaPaymentLog::where('received_at', '>=', $since)->count(),
                'failed24'  => VaPaymentLog::where('received_at', '>=', $since)->where('is_success', false)->count(),
                'unmatched' => VaPaymentLog::whereNull('va_payment_id')->count(),
                'total'     => VaPaymentLog::count(),
                'oldest'    => VaPaymentLog::min('received_at'),
            ],
            'minKeepDays' => self::MIN_KEEP_DAYS,
        ]);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show($id)
    {
        $log = VaPaymentLog::with(['vaPayment.invoice.plan', 'vaPayment.company'])->findOrFail($id);

        // Callback lain untuk VA yang sama (timeline)
        $related = $log->va_payment_id
            ? VaPaymentLog::where('va_payment_id', $log->va_payment_id)
            ->where('id', '!=', $log->id)
            ->orderByDesc('received_at')
            ->limit(20)
            ->get()
            : collect();

        return view('pages.superadmin.va-logs.show', [
            'log'     => $log,
            'related' => $related,
            'events'  => self::EVENTS,
        ]);
    }

    // =========================================================
    // PEMBERSIHAN LOG LAMA
    // =========================================================
    public function prune(Request $request)
    {
        $data = $request->validate([
            'older_than_days' => 'required|integer|min:' . self::MIN_KEEP_DAYS . '|max:3650',
            'only_success'    => 'nullable|boolean',
        ], [], ['older_than_days' => 'umur log']);

        $cutoff = now()->subDays((int) $data['older_than_days']);

        $query = VaPaymentLog::where('received_at', '<', $cutoff);

        if ($request->boolean('only_success')) {
            $query->where('is_success', true);
        }

        $deleted = $query->delete();

        AuditLog::record('prune_va_logs', null, "Hapus {$deleted} log callback bank yang lebih lama dari {$data['older_than_days']} hari", [
            'cutoff'       => $cutoff->toDateTimeString(),
            'only_success' => $request->boolean('only_success'),
            'deleted'      => $deleted,
        ]);

        return back()->with('success', "{$deleted} log lama berhasil dihapus.");
    }

    // =========================================================
    // HELPER
    // =========================================================
    private function filteredQuery(Request $request)
    {
        $query = VaPaymentLog::query();

        if ($request->filled('bank')) {
            $query->where('bank', $request->bank);
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('result')) {
            match ($request->result) {
                'success'   => $query->where('is_success', true),
                'failed'    => $query->where('is_success', false),
                'unmatched' => $query->whereNull('va_payment_id'),
                default     => null,
            };
        }

        if ($request->filled('va_payment_id')) {
            $query->where('va_payment_id', $request->va_payment_id);
        }

        if ($request->filled('from')) {
            $query->where('received_at', '>=', Carbon::parse($request->from)->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('received_at', '<=', Carbon::parse($request->to)->endOfDay());
        }

        // Cari di isi payload (order_id / no. invoice, transaction_id, no. VA) & pesan error
        if ($request->filled('search')) {
            $like = '%' . addcslashes(trim($request->search), '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('CAST(request_payload AS CHAR) LIKE ?', [$like])
                    ->orWhere('error_message', 'like', $like)
                    ->orWhere('ip_address', 'like', $like)
                    ->orWhereHas('vaPayment', fn($v) => $v->where('va_number', 'like', $like));
            });
        }

        return $query;
    }
}
