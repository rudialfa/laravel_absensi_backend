@extends('layouts.app')

@section('title', 'Paket ' . $plan->name)

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $link = fn(string $route, ...$params) => \Illuminate\Support\Facades\Route::has($route)
            ? route($route, $params)
            : null;
        $typeLabels = ['company' => 'Perusahaan', 'school' => 'Sekolah', 'pesantren' => 'Pesantren'];
        $subLabel = [
            'trial' => 'Trial',
            'active' => 'Aktif',
            'grace' => 'Toleransi',
            'expired' => 'Kedaluwarsa',
            'cancelled' => 'Dibatalkan',
        ];
        $subBadge = [
            'trial' => 'info',
            'active' => 'success',
            'grace' => 'warning',
            'expired' => 'secondary',
            'cancelled' => 'danger',
        ];
        $invLabel = [
            'paid' => 'Lunas',
            'pending' => 'Menunggu',
            'expired' => 'Kedaluwarsa',
            'cancelled' => 'Dibatalkan',
        ];
        $invBadge = ['paid' => 'success', 'pending' => 'warning', 'expired' => 'secondary', 'cancelled' => 'danger'];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <div class="section-header-back">
                    <a href="{{ route('superadmin.plans.index') }}" class="btn btn-icon"><i class="fas fa-arrow-left"></i></a>
                </div>
                <h1>Detail Paket</h1>
                <div class="section-header-button">
                    <a href="{{ route('superadmin.plans.edit', $plan->id) }}" class="btn btn-primary"><i
                            class="fas fa-edit"></i> Edit</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('superadmin.plans.index') }}">Paket</a></div>
                    <div class="breadcrumb-item">{{ $plan->name }}</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h4>{{ $plan->name }}</h4>
                                <div class="card-header-action">
                                    <span
                                        class="badge badge-{{ $plan->is_active ? 'success' : 'secondary' }}">{{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    @if ($plan->is_popular)
                                        <span class="badge badge-primary mb-2">Terpopuler</span>
                                    @endif
                                    <div class="h3 mb-0">{{ $plan->is_free ? 'Gratis' : $rupiah($plan->price) }}</div>
                                    <div class="text-muted">untuk {{ $plan->duration_days }} hari</div>
                                    @if (!$plan->is_free && $plan->duration_days > 30)
                                        <small class="text-muted">≈ {{ $rupiah($pricePerMonth) }} per 30 hari</small>
                                    @endif
                                    @if ($plan->saving_label)
                                        <div><span class="badge badge-light mt-2">{{ $plan->saving_label }}</span></div>
                                    @endif
                                </div>
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <th style="width:120px">Slug</th>
                                        <td><code>{{ $plan->slug }}</code></td>
                                    </tr>
                                    <tr>
                                        <th>Jenis</th>
                                        <td>{{ $plan->is_free ? 'Gratis (dipakai untuk trial)' : 'Berbayar' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Urutan</th>
                                        <td>{{ $plan->sort_order }}</td>
                                    </tr>
                                    <tr>
                                        <th>Deskripsi</th>
                                        <td>{!! nl2br(e($plan->description ?: '-')) !!}</td>
                                    </tr>
                                    <tr>
                                        <th>Dibuat</th>
                                        <td>{{ optional($plan->created_at)->format('d M Y') }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-footer bg-whitesmoke">
                                <a href="{{ route('superadmin.plans.edit', $plan->id) }}"
                                    class="btn btn-primary btn-block"><i class="fas fa-edit"></i> Edit Paket</a>
                                <a href="{{ route('superadmin.plans.create', ['from' => $plan->id]) }}"
                                    class="btn btn-outline-primary btn-block"><i class="fas fa-copy"></i> Salin Jadi Paket
                                    Baru</a>
                                <form action="{{ route('superadmin.plans.toggle-active', $plan->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-block {{ $plan->is_active ? 'btn-warning' : 'btn-success' }}">
                                        <i class="fas {{ $plan->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                        {{ $plan->is_active ? 'Nonaktifkan (sembunyikan dari aplikasi)' : 'Aktifkan' }}
                                    </button>
                                </form>
                                @if ($canDelete)
                                    <form action="{{ route('superadmin.plans.destroy', $plan->id) }}" method="POST"
                                        onsubmit="return confirm('Hapus paket {{ addslashes($plan->name) }}? Tindakan ini tidak bisa dibatalkan.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-block"><i class="fas fa-trash"></i> Hapus
                                            Paket</button>
                                    </form>
                                @else
                                    <small class="text-muted d-block text-center mt-2">
                                        Paket sudah pernah dipakai, jadi tidak bisa dihapus.
                                    </small>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-7">
                        <div class="row">
                            <div class="col-6">
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-success"><i class="fas fa-building"></i></div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>Tenant Aktif</h4>
                                        </div>
                                        <div class="card-body">
                                            {{ ($subsByStatus['active'] ?? 0) + ($subsByStatus['trial'] ?? 0) + ($subsByStatus['grace'] ?? 0) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card card-statistic-1">
                                    <div class="card-icon bg-primary"><i class="fas fa-money-bill-wave"></i></div>
                                    <div class="card-wrap">
                                        <div class="card-header">
                                            <h4>Pendapatan</h4>
                                        </div>
                                        <div class="card-body">{{ $rupiah($invoiceStats['paid']->amount ?? 0) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Status Langganan &amp; Invoice</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Langganan</h6>
                                        @forelse ($subsByStatus as $status => $total)
                                            <div class="d-flex justify-content-between mb-1">
                                                <span
                                                    class="badge badge-{{ $subBadge[$status] ?? 'secondary' }}">{{ $subLabel[$status] ?? ucfirst($status) }}</span>
                                                <strong>{{ $total }}</strong>
                                            </div>
                                        @empty
                                            <p class="text-muted">Belum dipakai tenant mana pun.</p>
                                        @endforelse
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Invoice</h6>
                                        @forelse ($invoiceStats as $status => $row)
                                            <div class="d-flex justify-content-between mb-1">
                                                <span
                                                    class="badge badge-{{ $invBadge[$status] ?? 'secondary' }}">{{ $invLabel[$status] ?? ucfirst($status) }}</span>
                                                <span><strong>{{ $row->total }}</strong> <small class="text-muted">·
                                                        {{ $rupiah($row->amount) }}</small></span>
                                            </div>
                                        @empty
                                            <p class="text-muted">Belum ada invoice.</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4>Tenant Pengguna Paket (15 teratas)</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Tenant</th>
                                                <th>Status</th>
                                                <th>Berakhir</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($tenants as $sub)
                                                <tr>
                                                    <td>
                                                        @if ($sub->company)
                                                            <a
                                                                href="{{ route('superadmin.tenants.show', $sub->company_id) }}">{{ $sub->company->name }}</a>
                                                            <div class="text-small text-muted">
                                                                {{ $typeLabels[$sub->company->type] ?? ucfirst($sub->company->type) }}
                                                            </div>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                    <td><span
                                                            class="badge badge-{{ $subBadge[$sub->status] ?? 'secondary' }}">{{ $subLabel[$sub->status] ?? ucfirst($sub->status) }}</span>
                                                    </td>
                                                    <td>
                                                        @php $subUrl = $link('superadmin.subscriptions.show', $sub->id); @endphp
                                                        @if ($subUrl)
                                                            <a
                                                                href="{{ $subUrl }}">{{ optional($sub->expires_at)->format('d M Y') }}</a>
                                                        @else
                                                            {{ optional($sub->expires_at)->format('d M Y') }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">Belum ada tenant</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        @if ($discounts->isNotEmpty())
                            <div class="card">
                                <div class="card-header">
                                    <h4>Voucher Khusus Paket Ini</h4>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-striped mb-0">
                                        @foreach ($discounts as $discount)
                                            <tr>
                                                <td class="pl-4">
                                                    {{ $discount->name }}
                                                    @if ($discount->code)
                                                        <code class="ml-1">{{ $discount->code }}</code>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $discount->discount_type === 'percent' ? rtrim(rtrim($discount->discount_value, '0'), '.') . '%' : $rupiah($discount->discount_value) }}
                                                </td>
                                                <td class="text-right pr-4">
                                                    <span
                                                        class="badge badge-{{ $discount->is_active ? 'success' : 'secondary' }}">{{ $discount->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection
