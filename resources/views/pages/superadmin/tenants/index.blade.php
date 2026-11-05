@extends('layouts.app')

@section('title', 'Tenant / Lembaga')

@section('main')
    @php
        $subBadge = ['trial' => 'info', 'active' => 'success', 'grace' => 'warning', 'expired' => 'secondary', 'cancelled' => 'danger'];
        $subLabel = ['trial' => 'Trial', 'active' => 'Aktif', 'grace' => 'Toleransi', 'expired' => 'Kedaluwarsa', 'cancelled' => 'Dibatalkan'];
        $hasFilter = request()->hasAny(['search', 'type', 'status', 'subscription']) &&
            collect(request()->only(['search', 'type', 'status', 'subscription']))->filter()->isNotEmpty();
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Tenant / Lembaga</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.tenants.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Tambah Tenant
                    </a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Tenant</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <h2 class="section-title">Semua Tenant</h2>
                <p class="section-lead">
                    {{ $stats['total'] }} lembaga terdaftar · {{ $stats['active'] }} aktif · {{ $stats['suspended'] }} disuspend
                </p>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.tenants.index') }}">
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                        placeholder="Nama, email, atau kota">
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="type" class="form-control">
                                        <option value="">Semua Jenis</option>
                                        @foreach ($typeLabels as $value => $label)
                                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                        <option value="suspended" @selected(request('status') === 'suspended')>Disuspend</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="subscription" class="form-control">
                                        <option value="">Semua Langganan</option>
                                        @foreach ($subLabel as $value => $label)
                                            <option value="{{ $value }}" @selected(request('subscription') === $value)>{{ $label }}</option>
                                        @endforeach
                                        <option value="none" @selected(request('subscription') === 'none')>Belum berlangganan</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="sort" class="form-control">
                                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>Terbaru</option>
                                        <option value="oldest" @selected(request('sort') === 'oldest')>Terlama</option>
                                        <option value="name" @selected(request('sort') === 'name')>Nama A–Z</option>
                                        <option value="users" @selected(request('sort') === 'users')>Pengguna terbanyak</option>
                                    </select>
                                </div>
                                <div class="col-md-1 mb-3">
                                    <button class="btn btn-primary btn-block" title="Cari"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                            @if ($hasFilter)
                                <div class="mb-3">
                                    <a href="{{ route('superadmin.tenants.index') }}" class="text-small">
                                        <i class="fas fa-times"></i> Hapus filter
                                    </a>
                                </div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Lembaga</th>
                                        <th>Jenis</th>
                                        <th>Kota</th>
                                        <th class="text-center">Pengguna</th>
                                        <th>Langganan</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width:190px">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($tenants as $tenant)
                                        @php $sub = $tenant->subscriptions->first(); @endphp
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    @if ($tenant->image_url)
                                                        <img src="{{ str_starts_with($tenant->image_url, 'http') ? $tenant->image_url : asset($tenant->image_url) }}"
                                                            alt="" class="rounded mr-2 border" style="width:36px;height:36px;object-fit:contain">
                                                    @else
                                                        <div class="rounded mr-2 bg-light d-flex align-items-center justify-content-center"
                                                            style="width:36px;height:36px"><i class="fas fa-building text-muted"></i></div>
                                                    @endif
                                                    <div>
                                                        <a href="{{ route('superadmin.tenants.show', $tenant->id) }}" class="font-weight-bold">{{ $tenant->name }}</a>
                                                        <div class="text-small text-muted">{{ $tenant->email }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                {{ $typeLabels[$tenant->type] ?? ucfirst($tenant->type) }}
                                                @if ($tenant->is_boarding)
                                                    <br><span class="badge badge-light">Berasrama</span>
                                                @endif
                                            </td>
                                            <td>{{ $tenant->city ?: '-' }}</td>
                                            <td class="text-center">{{ $tenant->users_count }}</td>
                                            <td>
                                                @if ($sub)
                                                    <span class="badge badge-{{ $subBadge[$sub->status] ?? 'secondary' }}">
                                                        {{ $subLabel[$sub->status] ?? ucfirst($sub->status) }}
                                                    </span>
                                                    <div class="text-small text-muted">
                                                        {{ $sub->plan->name ?? '-' }} · s/d {{ optional($sub->expires_at)->format('d M Y') }}
                                                    </div>
                                                @else
                                                    <span class="text-muted">Belum berlangganan</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($tenant->is_active)
                                                    <span class="badge badge-success">Aktif</span>
                                                @else
                                                    <span class="badge badge-danger">Disuspend</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('superadmin.tenants.show', $tenant->id) }}" class="btn btn-sm btn-info" title="Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('superadmin.tenants.edit', $tenant->id) }}" class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" title="Hapus"
                                                    data-toggle="modal" data-target="#deleteModal"
                                                    data-action="{{ route('superadmin.tenants.destroy', $tenant->id) }}"
                                                    data-name="{{ $tenant->name }}" data-users="{{ $tenant->users_count }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                {{ $hasFilter ? 'Tidak ada tenant yang cocok dengan filter.' : 'Belum ada tenant.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($tenants->hasPages())
                        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                            <small class="text-muted">
                                Menampilkan {{ $tenants->firstItem() }}–{{ $tenants->lastItem() }} dari {{ $tenants->total() }}
                            </small>
                            {{ $tenants->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    @include('pages.superadmin.tenants._delete-modal')
@endsection
