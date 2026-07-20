@extends('layouts.app')

@section('title', 'Manajemen Tenant')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Manajemen Tenant</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Tenants</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <h2 class="section-title">Semua Tenant</h2>
                <p class="section-lead">Kelola seluruh company/pesantren/school yang terdaftar di platform.</p>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Daftar Tenant</h4>
                            </div>
                            <div class="card-body">

                                <form method="GET" action="{{ route('superadmin.tenants.index') }}" class="form-row mb-3">
                                    <div class="col-md-4 mb-2">
                                        <input type="text" name="name" value="{{ request('name') }}"
                                            class="form-control" placeholder="Cari nama tenant">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <select name="type" class="form-control">
                                            <option value="">Semua Tipe</option>
                                            @foreach ($types as $type)
                                                <option value="{{ $type }}" @selected(request('type') === $type)>
                                                    {{ ucfirst($type) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <select name="status" class="form-control">
                                            <option value="">Semua Status</option>
                                            <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                            <option value="suspended" @selected(request('status') === 'suspended')>Disuspend</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <button class="btn btn-primary btn-block"><i class="fas fa-search"></i>
                                            Cari</button>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <thead>
                                            <tr>
                                                <th>Nama</th>
                                                <th>Tipe</th>
                                                <th>Karyawan</th>
                                                <th>Langganan</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($tenants as $tenant)
                                                <tr>
                                                    <td>
                                                        <a
                                                            href="{{ route('superadmin.tenants.show', $tenant->id) }}">{{ $tenant->name }}</a>
                                                        <br><small class="text-muted">{{ $tenant->email }}</small>
                                                    </td>
                                                    <td>{{ ucfirst($tenant->type) }}</td>
                                                    <td>{{ $tenant->users_count }}</td>
                                                    <td>
                                                        @if ($tenant->activeSubscription)
                                                            {{ $tenant->activeSubscription->plan->name ?? '-' }}
                                                            <span
                                                                class="badge badge-info">{{ ucfirst($tenant->activeSubscription->status) }}</span>
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
                                                    <td>
                                                        <div class="d-flex justify-content-center">
                                                            <a href='{{ route('superadmin.tenants.show', $tenant->id) }}'
                                                                class="btn btn-sm btn-info btn-icon">
                                                                <i class="fas fa-eye"></i>
                                                                Detail
                                                            </a>

                                                            <form
                                                                action="{{ route('superadmin.tenants.destroy', $tenant->id) }}"
                                                                method="POST" class="ml-2"
                                                                onsubmit="return confirm('Yakin ingin menghapus tenant ini? Semua data terkait akan ikut terhapus.');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="btn btn-sm btn-danger btn-icon">
                                                                    <i class="fas fa-times"></i> Hapus
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center">Belum ada tenant</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $tenants->withQueryString()->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
