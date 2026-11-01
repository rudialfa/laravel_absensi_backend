<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AppPolicy;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminAppPolicyController extends Controller
{
    /**
     * List semua kebijakan, dikelompokkan per tipe supaya kelihatan
     * versi mana yang sedang aktif vs draft/histori.
     */
    public function index(Request $request)
    {
        $query = AppPolicy::with('publishedBy')->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $policies = $query->paginate(15)->withQueryString();

        return view('pages.superadmin.app-policies.index', compact('policies'));
    }

    public function create()
    {
        return view('pages.superadmin.app-policies.create');
    }

    /**
     * Buat versi baru — TIDAK otomatis aktif, harus di-publish manual
     * lewat action terpisah supaya tidak salah pencet ubah kebijakan publik.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:privacy_policy,terms_of_service,refund_policy,other',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'version' => 'required|string|max:20',
        ]);

        $data['is_active'] = false;

        $policy = AppPolicy::create($data);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'create_app_policy',
            'subject_type' => AppPolicy::class,
            'subject_id' => $policy->id,
            'description' => "Membuat draft kebijakan \"{$policy->title}\" versi {$policy->version} ({$policy->type})",
            'ip_address' => $request->ip(),
        ]);

        return redirect()
            ->route('superadmin.app-policies.index')
            ->with('success', 'Draft kebijakan berhasil dibuat. Jangan lupa publish kalau sudah siap.');
    }

    public function edit(string $id)
    {
        $policy = AppPolicy::findOrFail($id);

        if ($policy->is_active) {
            return back()->with('error', 'Kebijakan yang sedang aktif tidak bisa diedit langsung. Buat versi baru lalu publish.');
        }

        return view('pages.superadmin.app-policies.edit', compact('policy'));
    }

    public function update(Request $request, string $id)
    {
        $policy = AppPolicy::findOrFail($id);

        if ($policy->is_active) {
            return back()->with('error', 'Kebijakan yang sedang aktif tidak bisa diedit langsung. Buat versi baru lalu publish.');
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'version' => 'required|string|max:20',
        ]);

        $policy->update($data);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'update_app_policy',
            'subject_type' => AppPolicy::class,
            'subject_id' => $policy->id,
            'description' => "Mengubah draft kebijakan \"{$policy->title}\" versi {$policy->version}",
            'ip_address' => $request->ip(),
        ]);

        return redirect()
            ->route('superadmin.app-policies.index')
            ->with('success', 'Draft kebijakan berhasil diperbarui.');
    }

    /**
     * Publish versi ini: nonaktifkan versi lama dengan tipe yang sama,
     * aktifkan versi ini. Hanya boleh ada 1 versi aktif per tipe.
     */
    public function publish(Request $request, string $id)
    {
        $policy = AppPolicy::findOrFail($id);

        DB::transaction(function () use ($policy) {
            AppPolicy::where('type', $policy->type)
                ->where('id', '!=', $policy->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $policy->is_active = true;
            $policy->published_at = now();
            $policy->published_by = auth()->id();
            $policy->save();
        });

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'publish_app_policy',
            'subject_type' => AppPolicy::class,
            'subject_id' => $policy->id,
            'description' => "Mempublikasikan kebijakan \"{$policy->title}\" versi {$policy->version} ({$policy->type})",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Kebijakan berhasil dipublikasikan dan menjadi versi aktif.');
    }

    public function destroy(string $id)
    {
        $policy = AppPolicy::findOrFail($id);

        if ($policy->is_active) {
            return back()->with('error', 'Tidak bisa menghapus kebijakan yang sedang aktif.');
        }

        $title = $policy->title;
        $policy->delete();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'delete_app_policy',
            'subject_type' => null,
            'subject_id' => null,
            'description' => "Menghapus draft kebijakan \"{$title}\"",
            'meta' => ['policy_id' => $id],
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', 'Draft kebijakan berhasil dihapus.');
    }
}
