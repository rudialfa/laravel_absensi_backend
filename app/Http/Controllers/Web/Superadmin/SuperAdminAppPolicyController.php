<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AppPolicy;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Kebijakan Aplikasi (app_policies): Syarat & Ketentuan, Kebijakan Privasi, Refund, dll.
 *
 * Alur berversi:
 *  - versi baru selalu draft → bisa diedit → Publish (versi aktif lama otomatis nonaktif);
 *  - versi aktif & versi lama yang pernah tayang TIDAK bisa diedit/dihapus (jejak hukum);
 *  - untuk mengubah, buat versi baru dari versi aktif ("Buat versi baru dari ini").
 */
class SuperAdminAppPolicyController extends Controller
{
    public const TYPES = [
        'terms_of_service' => 'Syarat & Ketentuan',
        'privacy_policy'   => 'Kebijakan Privasi',
        'refund_policy'    => 'Kebijakan Pengembalian Dana',
        'other'            => 'Lainnya',
    ];

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $policies = AppPolicy::with('publishedBy:id,name')
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->status === 'active', fn($q) => $q->where('is_active', true))
            ->when($request->status === 'draft', fn($q) => $q->whereNull('published_at'))
            ->when($request->status === 'old', fn($q) => $q->where('is_active', false)->whereNotNull('published_at'))
            ->orderBy('type')->orderByDesc('is_active')->latest()
            ->paginate(20)->withQueryString();

        // Versi aktif + jumlah draft per jenis
        $active = AppPolicy::where('is_active', true)->get()->keyBy('type');
        $drafts = AppPolicy::whereNull('published_at')->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

        return view('pages.superadmin.app-policies.index', [
            'policies'   => $policies,
            'typeLabels' => self::TYPES,
            'active'     => $active,
            'drafts'     => $drafts,
        ]);
    }

    // =========================================================
    // CREATE / STORE (?type= & ?from=ID untuk lanjut dari versi lain)
    // =========================================================
    public function create(Request $request)
    {
        $source = $request->from ? AppPolicy::find($request->from) : null;
        $type = $source->type ?? $request->type;

        return view('pages.superadmin.app-policies.create', [
            'source'      => $source,
            'typeLabels'  => self::TYPES,
            'prefill'     => [
                'type'    => $type,
                'title'   => $source->title ?? '',
                'content' => $source->content ?? '',
                'version' => $type ? $this->nextVersion($type) : '1.0',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $policy = new AppPolicy();
        $policy->forceFill($data + ['is_active' => false])->save();

        AuditLog::record('create_app_policy', $policy, "Membuat draft {$this->label($policy)}");

        return redirect()->route('superadmin.app-policies.show', $policy->id)
            ->with('success', 'Draft tersimpan. Periksa pratinjau, lalu klik Publish kalau sudah siap.');
    }

    // =========================================================
    // SHOW (pratinjau)
    // =========================================================
    public function show(string $id)
    {
        $policy = AppPolicy::with('publishedBy:id,name')->findOrFail($id);
        $current = AppPolicy::where('type', $policy->type)->where('is_active', true)->where('id', '!=', $policy->id)->first();

        return view('pages.superadmin.app-policies.show', [
            'policy'     => $policy,
            'html'       => self::render($policy->content),
            'current'    => $current,
            'diff'       => $current ? $this->lineDiffStats($current->content, $policy->content) : null,
            'versions'   => AppPolicy::where('type', $policy->type)->orderByDesc('created_at')->get(['id', 'version', 'is_active', 'published_at', 'created_at']),
            'typeLabels' => self::TYPES,
        ]);
    }

    // =========================================================
    // EDIT / UPDATE (draft saja)
    // =========================================================
    public function edit(string $id)
    {
        $policy = AppPolicy::findOrFail($id);

        if ($policy->published_at) {
            return redirect()->route('superadmin.app-policies.show', $policy->id)
                ->with('error', 'Versi yang sudah pernah tayang tidak bisa diedit. Klik "Buat versi baru dari ini".');
        }

        return view('pages.superadmin.app-policies.edit', ['policy' => $policy, 'typeLabels' => self::TYPES]);
    }

    public function update(Request $request, string $id)
    {
        $policy = AppPolicy::findOrFail($id);

        if ($policy->published_at) {
            return redirect()->route('superadmin.app-policies.show', $policy->id)
                ->with('error', 'Versi yang sudah pernah tayang tidak bisa diedit.');
        }

        $policy->forceFill($this->validated($request, $policy))->save();

        AuditLog::record('update_app_policy', $policy, "Mengubah draft {$this->label($policy)}");

        return redirect()->route('superadmin.app-policies.show', $policy->id)->with('success', 'Draft diperbarui.');
    }

    // =========================================================
    // PUBLISH
    // =========================================================
    public function publish(Request $request, string $id)
    {
        $policy = AppPolicy::findOrFail($id);

        if ($policy->is_active) {
            return back()->with('success', 'Versi ini sudah aktif.');
        }

        $previous = null;

        DB::transaction(function () use ($policy, &$previous) {
            $previous = AppPolicy::where('type', $policy->type)->where('is_active', true)->lockForUpdate()->first();

            AppPolicy::where('type', $policy->type)->where('id', '!=', $policy->id)->where('is_active', true)
                ->update(['is_active' => false]);

            $policy->forceFill([
                'is_active'    => true,
                // Tanggal tayang pertama dipertahankan kalau ini versi lama yang ditayangkan ulang
                'published_at' => $policy->published_at ?? now(),
                'published_by' => auth()->id(),
            ])->save();
        });

        AuditLog::record('publish_app_policy', $policy, "Menayangkan {$this->label($policy)}", [
            'previous_id'      => $previous?->id,
            'previous_version' => $previous?->version,
            'republish'        => (bool) $policy->wasChanged('published_by') && !$policy->wasChanged('published_at'),
        ]);

        return redirect()->route('superadmin.app-policies.show', $policy->id)
            ->with('success', 'Versi ' . $policy->version . ' sekarang aktif' . ($previous ? ' menggantikan versi ' . $previous->version : '') . '.');
    }

    // =========================================================
    // DESTROY (draft saja)
    // =========================================================
    public function destroy(string $id)
    {
        $policy = AppPolicy::findOrFail($id);

        if ($policy->published_at) {
            return back()->with('error', 'Versi yang sudah pernah tayang tidak bisa dihapus — disimpan sebagai riwayat persetujuan pengguna.');
        }

        $snapshot = $policy->getAttributes();
        $label = $this->label($policy);
        $policy->delete();

        AuditLog::record('delete_app_policy', null, "Menghapus draft {$label}", $snapshot);

        return redirect()->route('superadmin.app-policies.index', ['type' => $snapshot['type']])->with('success', 'Draft dihapus.');
    }

    // =========================================================
    // HELPER
    // =========================================================
    private function validated(Request $request, ?AppPolicy $policy = null): array
    {
        $type = $policy->type ?? $request->type;

        $rules = [
            'title'   => 'required|string|max:255',
            'content' => 'required|string|max:200000',
            'version' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9A-Za-z.\-]+$/',
                // Nomor versi unik per jenis kebijakan
                Rule::unique('app_policies', 'version')->where('type', $type)->ignore($policy?->id),
            ],
        ];
        if (!$policy) {
            $rules['type'] = ['required', Rule::in(array_keys(self::TYPES))];
        }

        $data = $request->validate($rules, [
            'version.unique' => 'Versi ini sudah dipakai untuk jenis kebijakan yang sama.',
            'version.regex'  => 'Versi hanya boleh huruf, angka, titik, dan strip (contoh: 1.0, 2.1).',
        ], ['title' => 'judul', 'content' => 'isi', 'version' => 'versi', 'type' => 'jenis']);

        return $data;
    }

    /** Saran nomor versi berikutnya: 1.0 → 1.1, 2.9 → 2.10 */
    private function nextVersion(string $type): string
    {
        $versions = AppPolicy::where('type', $type)->pluck('version');
        if ($versions->isEmpty()) {
            return '1.0';
        }

        $latest = $versions->sort(fn($a, $b) => version_compare($a, $b))->last();
        $parts = explode('.', $latest);
        $last = array_pop($parts);

        return ctype_digit($last) ? implode('.', array_merge($parts, [(int) $last + 1])) : $latest . '.1';
    }

    private function label(AppPolicy $p): string
    {
        return (self::TYPES[$p->type] ?? $p->type) . " \"{$p->title}\" versi {$p->version}";
    }

    /** Jumlah baris ditambah / dihapus dibanding versi aktif */
    private function lineDiffStats(string $old, string $new): array
    {
        $a = array_map('trim', preg_split('/\R/', $old));
        $b = array_map('trim', preg_split('/\R/', $new));

        return [
            'added'   => count(array_diff($b, $a)),
            'removed' => count(array_diff($a, $b)),
        ];
    }

    /**
     * Render format sederhana yang dipakai aplikasi:
     * "# Judul", "## Sub", "- poin", "1. poin", "**tebal**", paragraf dipisah baris kosong.
     * Teks di-escape dulu, jadi aman dari HTML/script.
     */
    public static function render(?string $content): string
    {
        $lines = preg_split('/\R/', e((string) $content));
        $html = '';
        $list = null;
        $para = [];

        $flushPara = function () use (&$para, &$html) {
            if ($para) {
                $html .= '<p>' . implode('<br>', $para) . '</p>';
                $para = [];
            }
        };
        $closeList = function () use (&$list, &$html) {
            if ($list) {
                $html .= "</{$list}>";
                $list = null;
            }
        };
        $inline = fn($t) => preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t);

        foreach ($lines as $line) {
            $t = rtrim($line);

            if (trim($t) === '') {
                $flushPara();
                $closeList();
                continue;
            }
            if (preg_match('/^(#{1,3})\s+(.*)$/', $t, $m)) {
                $flushPara();
                $closeList();
                $level = strlen($m[1]) + 2; // # → h3, ## → h4, ### → h5
                $html .= "<h{$level}>" . $inline($m[2]) . "</h{$level}>";
                continue;
            }
            $item = null;
            $type = null;
            if (preg_match('/^\s*[-*]\s+(.*)$/', $t, $m)) {
                [$item, $type] = [$m[1], 'ul'];
            } elseif (preg_match('/^\s*\d+[.)]\s+(.*)$/', $t, $m)) {
                [$item, $type] = [$m[1], 'ol'];
            }
            if ($type) {
                $flushPara();
                if ($list !== $type) {
                    $closeList();
                    $html .= "<{$type}>";
                    $list = $type;
                }
                $html .= '<li>' . $inline($item) . '</li>';
                continue;
            }

            $closeList();
            $para[] = $inline($t);
        }

        $flushPara();
        $closeList();

        return $html;
    }
}
