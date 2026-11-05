@extends('layouts.app')

@section('title', 'Staff Superadmin')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Staff Superadmin</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.staff.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Staff</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Staff</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <h2 class="section-title">Staff Internal</h2>
                <p class="section-lead">
                    Akun dengan akses penuh ke panel superadmin · {{ $totalActive }} aktif dari {{ $totalAll }} akun.
                </p>

                <div class="card">
                    <div class="card-body pb-0">
                        <form method="GET" action="{{ route('superadmin.staff.index') }}">
                            <div class="form-row">
                                <div class="col-md-6 mb-3">
                                    <input type="text" name="search" value="{{ $search }}" class="form-control"
                                        placeholder="Cari nama, email, atau no. HP">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <select name="status" class="form-control">
                                        <option value="">Semua Status</option>
                                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                                        <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <button class="btn btn-primary btn-block"><i class="fas fa-search"></i> Cari</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>No. HP</th>
                                        <th>Aktivitas Terakhir</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width:170px">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($staff as $s)
                                        @php
                                            $activity = $lastActivity[$s->id] ?? null;
                                            $isSelf = $s->id === auth()->id();
                                        @endphp
                                        <tr>
                                            <td>
                                                <a href="{{ route('superadmin.staff.show', $s->id) }}" class="font-weight-bold">{{ $s->name }}</a>
                                                @if ($isSelf)
                                                    <span class="badge badge-primary">Anda</span>
                                                @endif
                                                <div class="text-small text-muted">{{ $s->email }}</div>
                                            </td>
                                            <td>{{ $s->phone ?: '-' }}</td>
                                            <td>
                                                @if ($activity)
                                                    {{ \Carbon\Carbon::parse($activity->last_at)->diffForHumans() }}
                                                    <div class="text-small text-muted">{{ number_format($activity->total) }} aksi tercatat</div>
                                                @else
                                                    <span class="text-muted">Belum ada</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $s->is_active ? 'success' : 'secondary' }}">
                                                    {{ $s->is_active ? 'Aktif' : 'Nonaktif' }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('superadmin.staff.show', $s->id) }}" class="btn btn-sm btn-info" title="Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('superadmin.staff.edit', $s->id) }}" class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                @unless ($isSelf)
                                                    <form action="{{ route('superadmin.staff.toggle-active', $s->id) }}" method="POST" class="d-inline"
                                                        onsubmit="return confirm('{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }} staff {{ addslashes($s->name) }}?')">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="btn btn-sm {{ $s->is_active ? 'btn-warning' : 'btn-success' }}"
                                                            title="{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                            <i class="fas {{ $s->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                                        </button>
                                                    </form>
                                                @endunless
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Tidak ada staff yang cocok</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($staff->hasPages())
                        <div class="card-footer text-right">
                            {{ $staff->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
