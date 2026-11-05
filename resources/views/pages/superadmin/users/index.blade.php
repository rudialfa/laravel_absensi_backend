@extends('layouts.app')

@section('title', 'Pengguna')

@section('main')
    @php
        $filters = collect(request()->only(['search', 'q', 'company_id', 'type', 'role', 'status']))->filter();
        $createUrl = route('superadmin.users.create', $selectedTenant ? ['company_id' => $selectedTenant->id] : []);
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Pengguna</h1>
                <div class="section-header-button">
                    <a href="{{ $createUrl }}" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Pengguna</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Pengguna</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <h2 class="section-title">
                    {{ $selectedTenant ? 'Pengguna di ' . $selectedTenant->name : 'Semua Pengguna Tenant' }}
                </h2>
                <p class="section-lead">
                    {{ number_format($totalUsers) }} akun · {{ number_format($inactiveUsers) }} nonaktif.
                    Akun superadmin dikelola di menu <a href="{{ route('superadmin.staff.index') }}">Staff Superadmin</a>.
                </p>

                {{-- Ringkasan per peran (klik = filter) --}}
                <div class="mb-3">
                    <a href="{{ route('superadmin.users.index', request()->except(['role', 'page'])) }}"
                        class="btn btn-sm mb-1 {{ !request('role') ? 'btn-primary' : 'btn-outline-primary' }}">
                        Semua <span class="badge badge-light">{{ $totalUsers }}</span>
                    </a>
                    @foreach ($roleCounts as $role => $total)
                        <a href="{{ route('superadmin.users.index', array_merge(request()->except(['role', 'page']), ['role' => $role])) }}"
                            class="btn btn-sm mb-1 {{ request('role') === $role ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ $roleLabels[$role] ?? ucfirst($role) }} <span
                                class="badge badge-light">{{ $total }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.users.index') }}">
                            @if (request('role'))
                                <input type="hidden" name="role" value="{{ request('role') }}">
                            @endif
                            <div class="form-row">
                                <div class="col-md-3 mb-3">
                                    <input type="text" name="search" value="{{ $search }}" class="form-control"
                                        placeholder="Nama, email, atau no. HP">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <select name="company_id" class="form-control">
                                        <option value="">Semua Tenant</option>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>
                                                {{ $company->name }}
                                            </option>
                                        @endforeach
                                        <option value="none" @selected(request('company_id') === 'none')>— Tanpa tenant —</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="type" class="form-control">
                                        <option value="">Semua Jenis</option>
                                        @foreach ($typeLabels as $value => $label)
                                            <option value="{{ $value }}" @selected(request('type') === $value)>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <select name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                        <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                                    </select>
                                </div>
                                <div class="col-md-1 mb-3">
                                    <select name="sort" class="form-control" title="Urutan">
                                        <option value="newest" @selected(request('sort', 'newest') === 'newest')>Baru</option>
                                        <option value="oldest" @selected(request('sort') === 'oldest')>Lama</option>
                                        <option value="name" @selected(request('sort') === 'name')>A–Z</option>
                                    </select>
                                </div>
                                <div class="col-md-1 mb-3">
                                    <button class="btn btn-primary btn-block" title="Cari"><i
                                            class="fas fa-search"></i></button>
                                </div>
                            </div>
                            @if ($filters->isNotEmpty())
                                <div class="mb-3">
                                    <a href="{{ route('superadmin.users.index') }}" class="text-small"><i
                                            class="fas fa-times"></i> Hapus semua filter</a>
                                </div>
                            @endif
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Pengguna</th>
                                        <th>Peran</th>
                                        <th>Tenant</th>
                                        <th>No. HP</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width:170px">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($users as $user)
                                        <tr>
                                            <td>
                                                <a href="{{ route('superadmin.users.show', $user->id) }}"
                                                    class="font-weight-bold">{{ $user->name }}</a>
                                                <div class="text-small text-muted">{{ $user->email }}</div>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge badge-light">{{ $roleLabels[$user->role] ?? ucfirst($user->role) }}</span>
                                                @if ($user->position)
                                                    <div class="text-small text-muted">{{ $user->position }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($user->company)
                                                    <a
                                                        href="{{ route('superadmin.users.index', ['company_id' => $user->company_id]) }}">{{ $user->company->name }}</a>
                                                    <div class="text-small text-muted">
                                                        {{ $typeLabels[$user->company->type] ?? ucfirst($user->company->type) }}
                                                    </div>
                                                @else
                                                    <span class="text-danger small">Tanpa tenant</span>
                                                @endif
                                            </td>
                                            <td>{{ $user->phone ?: '-' }}</td>
                                            <td>
                                                <span class="badge badge-{{ $user->is_active ? 'success' : 'secondary' }}">
                                                    {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('superadmin.users.show', $user->id) }}"
                                                    class="btn btn-sm btn-info" title="Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('superadmin.users.edit', $user->id) }}"
                                                    class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="{{ route('superadmin.users.toggle-active', $user->id) }}"
                                                    method="POST" class="d-inline"
                                                    onsubmit="return confirm('{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ addslashes($user->name) }}?')">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button
                                                        class="btn btn-sm {{ $user->is_active ? 'btn-warning' : 'btn-success' }}"
                                                        title="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                        <i
                                                            class="fas {{ $user->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                {{ $filters->isNotEmpty() ? 'Tidak ada pengguna yang cocok dengan filter.' : 'Belum ada pengguna.' }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($users->hasPages())
                        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                            <small class="text-muted">Menampilkan {{ $users->firstItem() }}–{{ $users->lastItem() }} dari
                                {{ $users->total() }}</small>
                            {{ $users->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
