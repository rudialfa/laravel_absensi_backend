@extends('layouts.app')

@section('title', 'Paket Langganan')

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $durationLabel = function (int $days) {
            return match (true) {
                $days % 365 === 0 => ($days / 365) . ' tahun',
                $days % 30 === 0 => ($days / 30) . ' bulan',
                default => $days . ' hari',
            };
        };
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Paket Langganan</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.plans.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Paket</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Paket</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                @foreach ($warnings as $warning)
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> {{ $warning }}</div>
                @endforeach

                <div class="d-flex justify-content-between align-items-end flex-wrap mb-3">
                    <div>
                        <h2 class="section-title mt-0">Semua Paket</h2>
                        <p class="section-lead mb-0">Urutan di tabel = urutan tampil di aplikasi. Paket yang sudah pernah dipakai tidak bisa dihapus, cukup dinonaktifkan.</p>
                    </div>
                    <div class="btn-group mt-2">
                        <a href="{{ route('superadmin.plans.index') }}" class="btn btn-sm {{ !request('status') ? 'btn-primary' : 'btn-outline-primary' }}">Semua</a>
                        <a href="{{ route('superadmin.plans.index', ['status' => 'active']) }}" class="btn btn-sm {{ request('status') === 'active' ? 'btn-primary' : 'btn-outline-primary' }}">Aktif</a>
                        <a href="{{ route('superadmin.plans.index', ['status' => 'inactive']) }}" class="btn btn-sm {{ request('status') === 'inactive' ? 'btn-primary' : 'btn-outline-primary' }}">Nonaktif</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:50px">#</th>
                                        <th>Paket</th>
                                        <th>Durasi</th>
                                        <th>Harga</th>
                                        <th class="text-center">Tenant Aktif</th>
                                        <th class="text-right">Pendapatan</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width:190px">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($plans as $plan)
                                        @php $rev = $revenue[$plan->id] ?? null; @endphp
                                        <tr class="{{ $plan->is_active ? '' : 'text-muted' }}">
                                            <td>{{ $plan->sort_order }}</td>
                                            <td>
                                                <a href="{{ route('superadmin.plans.show', $plan->id) }}" class="font-weight-bold">{{ $plan->name }}</a>
                                                @if ($plan->is_popular)
                                                    <span class="badge badge-primary">Terpopuler</span>
                                                @endif
                                                @if ($plan->saving_label)
                                                    <span class="badge badge-light">{{ $plan->saving_label }}</span>
                                                @endif
                                                <div class="text-small"><code>{{ $plan->slug }}</code></div>
                                            </td>
                                            <td>{{ $durationLabel((int) $plan->duration_days) }}
                                                <div class="text-small text-muted">{{ $plan->duration_days }} hari</div>
                                            </td>
                                            <td>
                                                @if ($plan->is_free)
                                                    <span class="badge badge-info">Gratis / Trial</span>
                                                @else
                                                    {{ $rupiah($plan->price) }}
                                                    @if ($plan->duration_days > 30)
                                                        <div class="text-small text-muted">≈ {{ $rupiah($plan->price / $plan->duration_days * 30) }}/bln</div>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                {{ $plan->active_subscriptions_count }}
                                                @if ($plan->subscriptions_count > $plan->active_subscriptions_count)
                                                    <div class="text-small text-muted">dari {{ $plan->subscriptions_count }} total</div>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                {{ $rev ? $rupiah($rev->total) : '-' }}
                                                @if ($rev)
                                                    <div class="text-small text-muted">{{ $rev->paid_count }} invoice</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $plan->is_active ? 'success' : 'secondary' }}">
                                                    {{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('superadmin.plans.show', $plan->id) }}" class="btn btn-sm btn-info" title="Detail"><i class="fas fa-eye"></i></a>
                                                <a href="{{ route('superadmin.plans.edit', $plan->id) }}" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                                <a href="{{ route('superadmin.plans.create', ['from' => $plan->id]) }}" class="btn btn-sm btn-light" title="Salin jadi paket baru"><i class="fas fa-copy"></i></a>
                                                <form action="{{ route('superadmin.plans.toggle-active', $plan->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-sm {{ $plan->is_active ? 'btn-warning' : 'btn-success' }}"
                                                        title="{{ $plan->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                        <i class="fas {{ $plan->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Belum ada paket</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
