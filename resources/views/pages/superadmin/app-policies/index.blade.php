@extends('layouts.app')

@section('title', 'Kebijakan Aplikasi')

@section('main')
    @php
        $typeLabels = [
            'terms_of_service' => 'Syarat & Ketentuan',
            'privacy_policy' => 'Kebijakan Privasi',
            'refund_policy' => 'Kebijakan Pengembalian Dana',
            'other' => 'Lainnya',
        ];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Kebijakan Aplikasi</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.app-policies.create') }}" class="btn btn-primary">Buat Versi Baru</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Superadmin</a></div>
                    <div class="breadcrumb-item">Kebijakan Aplikasi</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <h2 class="section-title">Syarat & Ketentuan, Kebijakan Privasi</h2>
                <p class="section-lead">
                    Versi baru disimpan sebagai <b>draft</b>. Klik <b>Publish</b> supaya tampil di aplikasi.
                    Hanya 1 versi aktif per jenis; versi lama otomatis nonaktif.
                </p>

                <div class="card">
                    <div class="card-header">
                        <h4>Semua Versi</h4>
                        <div class="card-header-form">
                            <form method="GET" action="{{ route('superadmin.app-policies.index') }}" class="form-inline">
                                <select name="type" class="form-control mr-2" onchange="this.form.submit()">
                                    <option value="">Semua Jenis</option>
                                    @foreach ($typeLabels as $value => $label)
                                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Jenis</th>
                                        <th>Judul</th>
                                        <th>Versi</th>
                                        <th>Status</th>
                                        <th>Dipublish</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($policies as $policy)
                                        <tr>
                                            <td>{{ $typeLabels[$policy->type] ?? $policy->type }}</td>
                                            <td>{{ $policy->title }}</td>
                                            <td>{{ $policy->version }}</td>
                                            <td>
                                                @if ($policy->is_active)
                                                    <span class="badge badge-success">Aktif</span>
                                                @elseif ($policy->published_at)
                                                    <span class="badge badge-light">Versi lama</span>
                                                @else
                                                    <span class="badge badge-secondary">Draft</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($policy->published_at)
                                                    {{ $policy->published_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}
                                                    <br><small class="text-muted">{{ $policy->publishedBy->name ?? '-' }}</small>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-center">
                                                    @if (! $policy->is_active)
                                                        <a href="{{ route('superadmin.app-policies.edit', $policy->id) }}"
                                                            class="btn btn-sm btn-info btn-icon">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>

                                                        <form action="{{ route('superadmin.app-policies.publish', $policy->id) }}"
                                                            method="POST" class="ml-2"
                                                            onsubmit="return confirm('Publish versi ini? Versi aktif sebelumnya akan dinonaktifkan.')">
                                                            @csrf
                                                            <button class="btn btn-sm btn-success btn-icon">
                                                                <i class="fas fa-upload"></i> Publish
                                                            </button>
                                                        </form>

                                                        <form action="{{ route('superadmin.app-policies.destroy', $policy->id) }}"
                                                            method="POST" class="ml-2"
                                                            onsubmit="return confirm('Hapus draft ini?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-sm btn-danger btn-icon">
                                                                <i class="fas fa-times"></i> Hapus
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-muted small">Buat versi baru untuk mengubah</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">Belum ada kebijakan. Klik "Buat Versi Baru".</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        {{ $policies->withQueryString()->links() }}
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection