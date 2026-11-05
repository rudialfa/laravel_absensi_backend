<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\BroadcastNotification;
use App\Models\Company;
use App\Models\User;
use App\Services\BroadcastService;
use App\Services\FcmService;
use App\Services\NotificationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Kirim Notifikasi (pengumuman massal) + Log semua notifikasi aplikasi.
 *
 * Pengumuman disimpan di broadcast_notifications, lalu dikirim lewat NotificationService
 * (kotak masuk aplikasi + push FCM) oleh BroadcastService:
 *  - "Kirim sekarang"  → diproses setelah halaman selesai dimuat,
 *  - "Jadwalkan"       → dikirim command `broadcasts:send` (scheduler tiap menit).
 */
class SuperAdminNotificationController extends Controller
{
    public const TABS = ['broadcasts' => 'Pengumuman', 'logs' => 'Log Notifikasi'];

    /** Kategori yang dikenali aplikasi (ikon notifikasi) */
    public const CATEGORIES = [
        'system'       => 'Sistem / Pengumuman',
        'subscription' => 'Langganan',
        'school'       => 'Sekolah',
        'attendance'   => 'Absensi',
        'schedule'     => 'Jadwal',
        'holiday'      => 'Libur',
        'payroll'      => 'Payroll',
        'report'       => 'Laporan',
        'reminder'     => 'Pengingat',
    ];

    public const COMPANY_TYPES = ['company' => 'Perusahaan', 'school' => 'Sekolah', 'pesantren' => 'Pesantren'];

    public const ROLES = [
        'hr'       => 'HR (perusahaan)',
        'employee' => 'Karyawan',
        'admin'    => 'Admin Sekolah',
        'guru'     => 'Guru',
        'wali'     => 'Wali Murid',
        'ustadz'   => 'Ustadz',
        'santri'   => 'Santri',
    ];

    public const PUSH_STATUS = ['sent' => 'Terkirim', 'failed' => 'Gagal', 'no_token' => 'Tidak ada token', 'skipped' => 'Tanpa push'];
    public const PUSH_BADGE = ['sent' => 'success', 'failed' => 'danger', 'no_token' => 'warning', 'skipped' => 'light'];

    public function __construct(private BroadcastService $broadcast) {}

    // =========================================================
    // INDEX (?tab=broadcasts|logs)
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'broadcasts';

        $common = [
            'tab'           => $tab,
            'tabs'          => self::TABS,
            'fcmConfigured' => app(FcmService::class)->isConfigured(),
            'statuses'      => BroadcastNotification::STATUSES,
            'statusBadge'   => BroadcastNotification::STATUS_BADGE,
            'categories'    => self::CATEGORIES,
            'pushStatus'    => self::PUSH_STATUS,
            'pushBadge'     => self::PUSH_BADGE,
            // Scheduler dianggap jalan kalau ada pengumuman terjadwal yang terlambat > 3 menit → tidak
            'schedulerLate' => BroadcastNotification::where('status', 'scheduled')->where('scheduled_at', '<', now()->subMinutes(3))->exists(),
        ];

        if ($tab === 'logs') {
            $query = AppNotification::query()
                ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
                ->when($request->category, fn($q, $c) => $q->where('category', $c))
                ->when($request->type, fn($q, $t) => $q->where('type', 'like', addcslashes($t, '%_\\') . '%'))
                ->when($request->push_status === 'none', fn($q) => $q->whereNull('push_status'))
                ->when($request->push_status && $request->push_status !== 'none', fn($q) => $q->where('push_status', $request->push_status))
                ->when($request->read === '1', fn($q) => $q->whereNotNull('read_at'))
                ->when($request->read === '0', fn($q) => $q->whereNull('read_at'))
                ->when($request->date, fn($q, $d) => $q->whereDate('created_at', $d))
                ->when($request->search, function ($q, $s) {
                    $like = '%' . addcslashes($s, '%_\\') . '%';
                    $q->where(fn($w) => $w->where('title', 'like', $like)->orWhere('body', 'like', $like)
                        ->orWhereIn('user_id', User::where('name', 'like', $like)->orWhere('email', 'like', $like)->select('id')));
                });

            $since = now()->subDay();

            return view('pages.superadmin.notifications.index', $common + [
                'items'      => (clone $query)->with(['user:id,name,role', 'company:id,name'])->latest('id')->paginate(40)->withQueryString(),
                'health'     => AppNotification::where('created_at', '>=', $since)->toBase()
                    ->selectRaw("count(*) as total, sum(push_status = 'sent') as sent, sum(push_status = 'failed') as failed,
                        sum(push_status = 'no_token') as no_token, sum(read_at is not null) as read_count")->first(),
                'types'      => AppNotification::where('created_at', '>=', now()->subDays(30))
                    ->selectRaw("substring_index(type, '.', 1) as grp, count(*) as total")->groupBy('grp')->orderByDesc('total')->pluck('total', 'grp'),
                'companies'  => Company::orderBy('name')->get(['id', 'name']),
                'tokenStats' => [
                    'users'      => User::whereNotNull('company_id')->where('is_active', true)->count(),
                    'with_token' => User::whereNotNull('company_id')->where('is_active', true)->whereNotNull('fcm_token')->where('fcm_token', '<>', '')->count(),
                ],
            ]);
        }

        $items = BroadcastNotification::with('creator:id,name')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) => $q->where(fn($w) => $w->where('title', 'like', '%' . addcslashes($s, '%_\\') . '%')->orWhere('body', 'like', '%' . addcslashes($s, '%_\\') . '%')))
            ->orderByRaw("FIELD(status, 'sending', 'scheduled', 'draft', 'failed', 'sent', 'canceled')")
            ->latest('id')
            ->paginate(20)->withQueryString();

        // Jumlah dibaca per pengumuman di halaman ini
        $reads = AppNotification::where('reference_type', 'BroadcastNotification')
            ->whereIn('reference_id', $items->pluck('id'))
            ->selectRaw('reference_id, count(*) as total, sum(read_at is not null) as read_count')
            ->groupBy('reference_id')->get()->keyBy('reference_id');

        return view('pages.superadmin.notifications.index', $common + ['items' => $items, 'reads' => $reads]);
    }

    // =========================================================
    // CREATE / STORE
    // =========================================================
    public function create(Request $request)
    {
        $b = (new BroadcastNotification())->forceFill(['target' => 'all', 'category' => 'system', 'only_active_tenants' => true]);

        // ?copy=ID → kirim ulang / duplikat pengumuman lama
        if ($request->copy && ($src = BroadcastNotification::find($request->copy))) {
            $b->forceFill($src->only(['title', 'body', 'category', 'action', 'url', 'target', 'company_types', 'company_ids', 'user_ids', 'roles', 'only_active_tenants']));
        }

        return view('pages.superadmin.notifications.create', $this->formData($b));
    }

    public function store(Request $request)
    {
        [$data, $mode] = $this->validated($request);

        $b = new BroadcastNotification();
        $b->forceFill($data + ['created_by' => auth()->id()]);
        $this->applyMode($b, $mode, $request);
        $b->save();

        AuditLog::record('create_broadcast', $b, "Membuat pengumuman \"{$b->title}\" ({$b->status})", [
            'target'   => $b->target,
            'estimate' => $this->broadcast->estimate($this->broadcast->specOf($b)),
        ]);

        return $this->afterSave($b, $mode);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show($id)
    {
        $b = BroadcastNotification::with('creator:id,name')->findOrFail($id);

        return view('pages.superadmin.notifications.show', [
            'b'           => $b,
            'stats'       => $this->broadcast->stats($b),
            'estimate'    => $b->isEditable() ? $this->broadcast->estimate($this->broadcast->specOf($b)) : null,
            'targetText'  => $this->targetText($b),
            'recent'      => AppNotification::where('reference_type', 'BroadcastNotification')->where('reference_id', $b->id)
                ->with(['user:id,name,role', 'company:id,name'])->latest('id')->limit(30)->get(),
            'byCompany'   => AppNotification::where('reference_type', 'BroadcastNotification')->where('reference_id', $b->id)
                ->selectRaw('company_id, count(*) as total, sum(read_at is not null) as read_count')
                ->groupBy('company_id')->orderByDesc('total')->limit(15)->get(),
            'companyNames' => Company::pluck('name', 'id'),
            'statuses'    => BroadcastNotification::STATUSES,
            'statusBadge' => BroadcastNotification::STATUS_BADGE,
            'categories'  => self::CATEGORIES,
            'pushStatus'  => self::PUSH_STATUS,
            'pushBadge'   => self::PUSH_BADGE,
            'fcmConfigured' => app(FcmService::class)->isConfigured(),
        ]);
    }

    // =========================================================
    // EDIT / UPDATE (draft & terjadwal)
    // =========================================================
    public function edit($id)
    {
        $b = BroadcastNotification::findOrFail($id);
        if (!$b->isEditable()) {
            return redirect()->route('superadmin.notifications.show', $b->id)->with('error', 'Pengumuman yang sudah dikirim tidak bisa diubah. Gunakan "Kirim ulang" untuk membuat salinan.');
        }

        return view('pages.superadmin.notifications.edit', $this->formData($b));
    }

    public function update(Request $request, $id)
    {
        $b = BroadcastNotification::findOrFail($id);
        if (!$b->isEditable()) {
            return redirect()->route('superadmin.notifications.show', $b->id)->with('error', 'Pengumuman ini sudah mulai dikirim.');
        }

        [$data, $mode] = $this->validated($request);
        $b->forceFill($data);
        $this->applyMode($b, $mode, $request);
        $b->save();

        AuditLog::record('update_broadcast', $b, "Mengubah pengumuman \"{$b->title}\" ({$b->status})");

        return $this->afterSave($b, $mode);
    }

    // =========================================================
    // AKSI
    // =========================================================

    /** Kirim draft / terjadwal sekarang juga */
    public function sendNow($id)
    {
        $b = BroadcastNotification::findOrFail($id);
        if (!$b->isEditable()) {
            return back()->with('error', 'Pengumuman ini sudah diproses.');
        }

        $b->forceFill(['status' => 'scheduled', 'scheduled_at' => now()])->save();
        AuditLog::record('send_broadcast', $b, "Mengirim pengumuman \"{$b->title}\"");

        return $this->afterSave($b, 'now');
    }

    public function cancel($id)
    {
        $b = BroadcastNotification::findOrFail($id);

        // Atomik: hanya batal kalau belum diambil proses pengiriman
        $ok = BroadcastNotification::where('id', $b->id)->whereIn('status', ['draft', 'scheduled'])->update(['status' => 'canceled']);
        if (!$ok) {
            return back()->with('error', 'Pengumuman sudah mulai/selesai dikirim, tidak bisa dibatalkan. Gunakan "Tarik dari kotak masuk".');
        }

        AuditLog::record('cancel_broadcast', $b, "Membatalkan pengumuman \"{$b->title}\"");

        return back()->with('success', 'Pengumuman dibatalkan.');
    }

    /** Hapus notifikasi pengumuman ini dari kotak masuk semua penerima (push yang sudah muncul di HP tidak bisa ditarik) */
    public function retract(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);
        $b = BroadcastNotification::findOrFail($id);

        $deleted = $this->broadcast->retract($b);

        AuditLog::record('retract_broadcast', $b, "Menarik pengumuman \"{$b->title}\" dari {$deleted} kotak masuk. Alasan: {$data['reason']}", ['deleted' => $deleted, 'reason' => $data['reason']]);

        return back()->with('success', "Pengumuman dihapus dari {$deleted} kotak masuk pengguna. Notifikasi pop-up yang sudah muncul di HP tidak bisa ditarik.");
    }

    public function destroy($id)
    {
        $b = BroadcastNotification::findOrFail($id);

        if ($b->status === 'sending') {
            return back()->with('error', 'Pengumuman sedang dikirim. Tunggu sampai selesai.');
        }
        if (in_array($b->status, ['sent', 'failed'], true) && !$b->retracted_at && $b->stored_count > 0) {
            return back()->with('error', 'Pengumuman ini sudah ada di kotak masuk pengguna. Tarik dulu dari kotak masuk, baru bisa dihapus.');
        }

        $snapshot = $b->getAttributes();
        $b->delete();

        AuditLog::record('delete_broadcast', null, "Menghapus pengumuman \"{$snapshot['title']}\"", $snapshot);

        return redirect()->route('superadmin.notifications.index')->with('success', 'Pengumuman dihapus.');
    }

    /** Kirim tes ke 1 pengguna (default: akun sendiri) sebelum dikirim massal */
    public function test(Request $request)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:150',
            'body'       => 'required|string|max:1000',
            'test_email' => 'nullable|email',
        ], [], ['title' => 'judul', 'body' => 'isi', 'test_email' => 'email penerima tes']);

        $user = $data['test_email'] ? User::where('email', $data['test_email'])->first() : $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Pengguna dengan email itu tidak ditemukan.'], 422);
        }

        $fresh = User::find($user->id, ['id', 'name', 'fcm_token', 'is_active']);
        if (!$fresh->fcm_token) {
            return response()->json(['ok' => false, 'message' => "{$fresh->name} belum punya token HP (belum login di aplikasi / izin notifikasi mati). Tes tidak bisa dikirim."], 422);
        }

        $r = app(NotificationService::class)->send($fresh->id, 'broadcast.test', '[TES] ' . $data['title'], $data['body'], [
            'category' => $request->input('category', 'system'),
            'store'    => false, // tes tidak disimpan di kotak masuk
        ]);

        return response()->json([
            'ok'      => $r['sent'] > 0,
            'message' => $r['sent'] > 0 ? "Tes terkirim ke HP {$fresh->name}." : "Gagal mengirim ke HP {$fresh->name}. Token mungkin sudah tidak berlaku — cek log server.",
        ], $r['sent'] > 0 ? 200 : 422);
    }

    /** Perkiraan jumlah penerima untuk form (AJAX) */
    public function estimate(Request $request)
    {
        return response()->json($this->broadcast->estimate($this->specFromRequest($request)));
    }

    // =========================================================
    // HELPER
    // =========================================================
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'               => 'required|string|max:150',
            'body'                => 'required|string|max:1000',
            'category'            => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'url'                 => 'nullable|url|max:255',
            'target'              => ['required', Rule::in(array_keys(BroadcastNotification::TARGETS))],
            'company_types'       => 'required_if:target,types|array',
            'company_types.*'     => Rule::in(array_keys(self::COMPANY_TYPES)),
            'company_ids'         => 'required_if:target,companies|array',
            'company_ids.*'       => 'integer|exists:companies,id',
            'user_emails'         => 'required_if:target,users|nullable|string|max:5000',
            'roles'               => 'nullable|array',
            'roles.*'             => Rule::in(array_keys(self::ROLES)),
            'only_active_tenants' => 'nullable|boolean',
            'mode'                => 'required|in:draft,now,schedule',
            'scheduled_at'        => 'required_if:mode,schedule|nullable|date',
        ], [
            'company_types.required_if' => 'Pilih minimal satu jenis tenant.',
            'company_ids.required_if'   => 'Pilih minimal satu tenant.',
            'user_emails.required_if'   => 'Isi email pengguna penerima.',
            'scheduled_at.required_if'  => 'Isi waktu kirim terjadwal.',
        ], ['title' => 'judul', 'body' => 'isi', 'url' => 'link']);

        $userIds = [];
        if ($data['target'] === 'users') {
            $emails = collect(preg_split('/[\s,;]+/', (string) $data['user_emails']))->filter()->map(fn($e) => strtolower(trim($e)))->unique();
            $userIds = User::whereIn(DB::raw('lower(email)'), $emails)->whereNotNull('company_id')->pluck('id')->all();
            if (empty($userIds)) {
                throw new HttpResponseException(back()->withInput()->withErrors(['user_emails' => 'Tidak ada pengguna tenant yang cocok dengan email tersebut.']));
            }
        }

        if ($data['mode'] === 'schedule') {
            $at = Carbon::parse($data['scheduled_at'], 'Asia/Jakarta');
            if ($at->lt(now()->subMinute())) {
                throw new HttpResponseException(back()->withInput()->withErrors(['scheduled_at' => 'Waktu kirim sudah lewat.']));
            }
        }

        return [[
            'title'               => $data['title'],
            'body'                => $data['body'],
            'category'            => $data['category'],
            'url'                 => $data['url'] ?? null,
            'target'              => $data['target'],
            'company_types'       => $data['target'] === 'types' ? array_values($data['company_types'] ?? []) : null,
            'company_ids'         => $data['target'] === 'companies' ? array_map('intval', $data['company_ids'] ?? []) : null,
            'user_ids'            => $data['target'] === 'users' ? $userIds : null,
            'roles'               => !empty($data['roles']) && $data['target'] !== 'users' ? array_values($data['roles']) : null,
            'only_active_tenants' => $request->boolean('only_active_tenants'),
        ], $data['mode']];
    }

    private function applyMode(BroadcastNotification $b, string $mode, Request $request): void
    {
        if ($mode === 'draft') {
            $b->status = 'draft';
            $b->scheduled_at = null;
        } elseif ($mode === 'schedule') {
            $b->status = 'scheduled';
            $b->scheduled_at = Carbon::parse($request->scheduled_at, 'Asia/Jakarta')->setTimezone(config('app.timezone'));
        } else {
            $b->status = 'scheduled';
            $b->scheduled_at = now();
        }
    }

    private function afterSave(BroadcastNotification $b, string $mode)
    {
        if ($mode === 'now') {
            // Diproses setelah response dikirim ke browser → halaman tidak menunggu ratusan push
            $id = $b->id;
            app()->terminating(fn() => app(BroadcastService::class)->process($id));

            return redirect()->route('superadmin.notifications.show', $b->id)
                ->with('success', 'Pengumuman sedang dikirim. Muat ulang halaman ini untuk melihat progresnya.');
        }

        return redirect()->route('superadmin.notifications.show', $b->id)->with('success', match ($mode) {
            'schedule' => 'Pengumuman dijadwalkan ' . $b->scheduled_at->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') . ' WIB.',
            default    => 'Draft pengumuman disimpan.',
        });
    }

    private function specFromRequest(Request $request): array
    {
        $userIds = [];
        if ($request->target === 'users') {
            $emails = collect(preg_split('/[\s,;]+/', (string) $request->user_emails))->filter()->map(fn($e) => strtolower(trim($e)));
            $userIds = $emails->isEmpty() ? [] : User::whereIn(DB::raw('lower(email)'), $emails)->pluck('id')->all();
        }

        return [
            'target'              => $request->target ?: 'all',
            'company_types'       => (array) $request->input('company_types', []),
            'company_ids'         => array_map('intval', (array) $request->input('company_ids', [])),
            'user_ids'            => $userIds,
            'roles'               => (array) $request->input('roles', []),
            'only_active_tenants' => $request->boolean('only_active_tenants'),
        ];
    }

    private function formData(BroadcastNotification $b): array
    {
        return [
            'b'             => $b,
            'companies'     => Company::orderBy('name')->get(['id', 'name', 'type', 'is_active']),
            'categories'    => self::CATEGORIES,
            'companyTypes'  => self::COMPANY_TYPES,
            'roles'         => self::ROLES,
            'targets'       => BroadcastNotification::TARGETS,
            'userEmails'    => $b->user_ids ? User::whereIn('id', $b->user_ids)->pluck('email')->implode("\n") : '',
            'fcmConfigured' => app(FcmService::class)->isConfigured(),
        ];
    }

    public function targetText(BroadcastNotification $b): string
    {
        $text = match ($b->target) {
            'types'     => 'Tenant jenis: ' . collect($b->company_types)->map(fn($t) => self::COMPANY_TYPES[$t] ?? $t)->implode(', '),
            'companies' => 'Tenant: ' . Company::whereIn('id', $b->company_ids ?? [])->pluck('name')->implode(', '),
            'users'     => count($b->user_ids ?? []) . ' pengguna tertentu',
            default     => 'Semua pengguna tenant',
        };

        if ($b->roles) {
            $text .= ' · role: ' . collect($b->roles)->map(fn($r) => self::ROLES[$r] ?? $r)->implode(', ');
        }

        return $text . ($b->only_active_tenants ? ' · tenant aktif saja' : '');
    }
}
