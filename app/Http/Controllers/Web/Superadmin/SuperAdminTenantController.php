<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\Request;

class SuperAdminTenantController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::withCount('users')
            ->with('activeSubscription.plan');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $tenants = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('pages.superadmin.tenants.index', [
            'tenants' => $tenants,
            'types'   => Company::TYPES,
        ]);
    }

    public function show($id)
    {
        $tenant = Company::with([
            'activeSubscription.plan',
            'subscriptions.plan',
            'invoices' => fn($q) => $q->latest('issued_at')->limit(10),
        ])->withCount('users')->findOrFail($id);

        return view('pages.superadmin.tenants.show', compact('tenant'));
    }

    public function suspend(Request $request, $id)
    {
        $tenant = Company::findOrFail($id);

        $request->validate([
            'suspend_reason' => 'nullable|string|max:255',
        ]);

        $tenant->update([
            'is_active'      => false,
            'suspended_at'   => now(),
            'suspend_reason' => $request->suspend_reason,
        ]);

        AuditLog::record('suspend_tenant', $tenant, "Tenant {$tenant->name} disuspend");

        return redirect()
            ->route('superadmin.tenants.show', $tenant->id)
            ->with('success', 'Tenant berhasil disuspend.');
    }

    public function activate($id)
    {
        $tenant = Company::findOrFail($id);

        $tenant->update([
            'is_active'      => true,
            'suspended_at'   => null,
            'suspend_reason' => null,
        ]);

        AuditLog::record('activate_tenant', $tenant, "Tenant {$tenant->name} diaktifkan kembali");

        return redirect()
            ->route('superadmin.tenants.show', $tenant->id)
            ->with('success', 'Tenant berhasil diaktifkan kembali.');
    }

    public function destroy($id)
    {
        $tenant = Company::findOrFail($id);
        $name   = $tenant->name;

        $tenant->delete();

        AuditLog::record('delete_tenant', null, "Tenant {$name} (ID: {$id}) dihapus");

        return redirect()
            ->route('superadmin.tenants.index')
            ->with('success', 'Tenant berhasil dihapus.');
    }
}
