@extends('layouts.app')

@section('title', 'Superadmin Dashboard')

@push('style')
    <style>
        .stat-link { color: inherit; text-decoration: none !important; }
        .stat-link:hover .card { box-shadow: 0 4px 18px rgba(0, 0, 0, .08); }
        .card-statistic-1 .card-body small { font-size: 12px; font-weight: 500; }
        .mini-bar { height: 6px; border-radius: 3px; background: #eef1f6; overflow: hidden; }
        .mini-bar span { display: block; height: 100%; background: #6777ef; border-radius: 3px; }
    </style>
@endpush

@section('main')
    @php
        $link = fn(string $route, ...$params) => \Illuminate\Support\Facades\Route::has($route) ? route($route, $params) : null;
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $typeLabels = [
            'company'   => 'Perusahaan',
            'school'    => 'Sekolah',
            'pesantren' => 'Pesantren',
            'tpq'       => 'TPQ',
            'kuliah'    => 'Kampus / Kuliah',
        ];
        $roleLabels = [
            'hr'       => 'HR / Admin Perusahaan',
            'employee' => 'Karyawan',
            'ustadz'   => 'Ustadz',
            'santri'   => 'Santri',
            'guru'     => 'Guru',
            'wali'     => 'Wali Murid',
            'admin'    => 'Admin Sekolah',
            'staff'    => 'Staff Internal',
        ];
        $statusBadge = [
            'paid'      => 'success',
            'pending'   => 'warning',
            'expired'   => 'secondary',
            'cancelled' => 'danger',
        ];

        $cards = [
            [
                'route' => 'superadmin.tenants.index', 'icon' => 'fa-building', 'color' => 'primary',
                'label' => 'Total Tenant', 'value' => number_format($totalTenants),
                'sub'   => "{$activeTenants} aktif · {$suspended} disuspend",
            ],
            [
                'route' => 'superadmin.users.index', 'icon' => 'fa-users', 'color' => 'info',
                'label' => 'Total Pengguna', 'value' => number_format($totalUsers),
                'sub'   => number_format($activeUsers) . ' akun aktif',
            ],
            [
                'route' => 'superadmin.subscriptions.index', 'icon' => 'fa-sync-alt', 'color' => 'success',
                'label' => 'Langganan Aktif', 'value' => number_format($activeSubs),
                'sub'   => "{$paidSubs} berbayar · {$trialSubs} trial",
            ],
            [
                'route' => 'superadmin.analytics', 'icon' => 'fa-money-bill-wave', 'color' => 'success',
                'label' => 'Pendapatan Bulan Ini', 'value' => $rupiah($revenueThisMonth),
                'sub'   => $revenueGrowth === null
                    ? 'Bulan lalu: ' . $rupiah($revenueLastMonth)
                    : ($revenueGrowth >= 0 ? '▲ ' : '▼ ') . abs($revenueGrowth) . '% dari bulan lalu',
            ],
            [
                'route' => 'superadmin.invoices.index', 'icon' => 'fa-file-invoice-dollar', 'color' => 'warning',
                'label' => 'Invoice Pending', 'value' => number_format($pendingInvoices),
                'sub'   => "{$overdueInvoices} lewat jatuh tempo",
            ],
            [
                'route' => 'superadmin.support-tickets.index', 'icon' => 'fa-headset', 'color' => 'danger',
                'label' => 'Tiket Terbuka', 'value' => number_format($openTickets),
                'sub'   => "{$inProgressTickets} sedang ditangani",
            ],
        ];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Dashboard</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active">Dashboard</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                {{-- Peringatan yang butuh tindakan --}}
                @if ($graceSubs > 0 || $overdueInvoices > 0 || $openTickets > 0)
                    <div class="alert alert-light border mb-4">
                        <strong><i class="fas fa-bell text-warning"></i> Perlu perhatian:</strong>
                        @if ($graceSubs > 0)
                            <span class="ml-2">{{ $graceSubs }} tenant dalam masa toleransi</span>
                        @endif
                        @if ($overdueInvoices > 0)
                            <span class="ml-2">· {{ $overdueInvoices }} invoice lewat jatuh tempo</span>
                        @endif
                        @if ($openTickets > 0)
                            <span class="ml-2">· {{ $openTickets }} tiket belum dibalas</span>
                        @endif
                    </div>
                @endif

                {{-- Kartu statistik --}}
                <div class="row">
                    @foreach ($cards as $card)
                        @php $url = $link($card['route']); @endphp
                        <div class="col-12 col-sm-6 col-lg-4">
                            @if ($url)<a href="{{ $url }}" class="stat-link">@endif
                            <div class="card card-statistic-1">
                                <div class="card-icon bg-{{ $card['color'] }}">
                                    <i class="fas {{ $card['icon'] }}"></i>
                                </div>
                                <div class="card-wrap">
                                    <div class="card-header">
                                        <h4>{{ $card['label'] }}</h4>
                                    </div>
                                    <div class="card-body">
                                        {{ $card['value'] }}
                                        <div><small class="text-muted">{{ $card['sub'] }}</small></div>
                                    </div>
                                </div>
                            </div>
                            @if ($url)</a>@endif
                        </div>
                    @endforeach
                </div>

                {{-- Komposisi tenant & pengguna --}}
                <div class="row">
                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Tenant per Jenis Lembaga</h4>
                                <div class="card-header-action">
                                    <span class="badge badge-light">{{ $newTenantsMonth }} baru bulan ini</span>
                                </div>
                            </div>
                            <div class="card-body">
                                @forelse ($tenantsByType as $type => $total)
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>{{ $typeLabels[$type] ?? ucfirst($type) }}</span>
                                            <strong>{{ $total }}</strong>
                                        </div>
                                        <div class="mini-bar">
                                            <span style="width: {{ $totalTenants ? round($total / $totalTenants * 100) : 0 }}%"></span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">Belum ada tenant.</p>
                                @endforelse

                                @if ($tenantsWithoutSubs > 0)
                                    <p class="text-muted small mb-0 mt-2">
                                        <i class="fas fa-info-circle"></i>
                                        {{ $tenantsWithoutSubs }} tenant belum pernah berlangganan (termasuk trial).
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Pengguna per Peran</h4>
                            </div>
                            <div class="card-body">
                                @forelse ($usersByRole as $role => $total)
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>{{ $roleLabels[$role] ?? ucfirst($role) }}</span>
                                            <strong>{{ number_format($total) }}</strong>
                                        </div>
                                        <div class="mini-bar">
                                            <span style="width: {{ $totalUsers ? round($total / $totalUsers * 100) : 0 }}%; background:#3abaf4"></span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">Belum ada pengguna.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Langganan yang perlu dipantau --}}
                <div class="row">
                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Akan Berakhir (7 hari ke depan)</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Tenant</th>
                                                <th>Paket</th>
                                                <th>Berakhir</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($expiringSoon as $sub)
                                                <tr>
                                                    <td>
                                                        @php $url = $link('superadmin.subscriptions.show', $sub->id); @endphp
                                                        @if ($url)
                                                            <a href="{{ $url }}">{{ $sub->company->name ?? '-' }}</a>
                                                        @else
                                                            {{ $sub->company->name ?? '-' }}
                                                        @endif
                                                        @if ($sub->status === 'trial')
                                                            <span class="badge badge-info">Trial</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $sub->plan->name ?? '-' }}</td>
                                                    <td>
                                                        {{ optional($sub->expires_at)->format('d M Y') }}
                                                        <br><small class="text-muted">{{ optional($sub->expires_at)->diffForHumans() }}</small>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">Tidak ada langganan yang akan berakhir</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Masa Toleransi (Grace)</h4>
                                <div class="card-header-action">
                                    <span class="badge badge-danger">{{ $graceSubs }}</span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Tenant</th>
                                                <th>Paket</th>
                                                <th>Habis Sejak</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($graceList as $sub)
                                                <tr>
                                                    <td>
                                                        @php $url = $link('superadmin.subscriptions.show', $sub->id); @endphp
                                                        @if ($url)
                                                            <a href="{{ $url }}">{{ $sub->company->name ?? '-' }}</a>
                                                        @else
                                                            {{ $sub->company->name ?? '-' }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $sub->plan->name ?? '-' }}</td>
                                                    <td>
                                                        {{ optional($sub->expires_at)->format('d M Y') }}
                                                        <br><small class="text-danger">{{ optional($sub->expires_at)->diffForHumans() }}</small>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">Tidak ada tenant dalam masa toleransi</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Invoice terbaru & tiket --}}
                <div class="row">
                    <div class="col-12 col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h4>Invoice Terbaru</h4>
                                @if ($url = $link('superadmin.invoices.index'))
                                    <div class="card-header-action">
                                        <a href="{{ $url }}" class="btn btn-sm btn-primary">Semua</a>
                                    </div>
                                @endif
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>No. Invoice</th>
                                                <th>Tenant</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($latestInvoices as $invoice)
                                                <tr>
                                                    <td>
                                                        @php $url = $link('superadmin.invoices.show', $invoice->id); @endphp
                                                        @if ($url)
                                                            <a href="{{ $url }}">{{ $invoice->invoice_number }}</a>
                                                        @else
                                                            {{ $invoice->invoice_number }}
                                                        @endif
                                                        <br><small class="text-muted">{{ optional($invoice->issued_at)->format('d M Y H:i') }}</small>
                                                    </td>
                                                    <td>
                                                        {{ $invoice->company->name ?? '-' }}
                                                        <br><small class="text-muted">{{ $invoice->plan->name ?? '-' }}</small>
                                                    </td>
                                                    <td>{{ $rupiah($invoice->total_amount) }}</td>
                                                    <td>
                                                        <span class="badge badge-{{ $statusBadge[$invoice->status] ?? 'secondary' }}">
                                                            {{ ucfirst($invoice->status) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted">Belum ada invoice</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h4>Tiket Menunggu</h4>
                                @if ($url = $link('superadmin.support-tickets.index'))
                                    <div class="card-header-action">
                                        <a href="{{ $url }}" class="btn btn-sm btn-primary">Semua</a>
                                    </div>
                                @endif
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled list-unstyled-border mb-0">
                                    @forelse ($latestTickets as $ticket)
                                        <li class="media">
                                            <div class="media-body">
                                                <div class="float-right">
                                                    <span class="badge badge-{{ $ticket->status === 'open' ? 'info' : 'warning' }}">
                                                        {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                                    </span>
                                                </div>
                                                @php $url = $link('superadmin.support-tickets.show', $ticket->id); @endphp
                                                <div class="media-title">
                                                    @if ($url)
                                                        <a href="{{ $url }}">{{ \Illuminate\Support\Str::limit($ticket->subject, 40) }}</a>
                                                    @else
                                                        {{ \Illuminate\Support\Str::limit($ticket->subject, 40) }}
                                                    @endif
                                                </div>
                                                <span class="text-small text-muted">
                                                    {{ $ticket->company->name ?? '-' }} · {{ $ticket->created_at->diffForHumans() }}
                                                </span>
                                            </div>
                                        </li>
                                    @empty
                                        <li class="text-muted">Tidak ada tiket yang menunggu.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tenant terbaru --}}
                <div class="card">
                    <div class="card-header">
                        <h4>Tenant Terbaru</h4>
                        @if ($url = $link('superadmin.tenants.index'))
                            <div class="card-header-action">
                                <a href="{{ $url }}" class="btn btn-sm btn-primary">Semua</a>
                            </div>
                        @endif
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Jenis</th>
                                        <th>Kota</th>
                                        <th>Pengguna</th>
                                        <th>Terdaftar</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($latestTenants as $tenant)
                                        <tr>
                                            <td>
                                                @php $url = $link('superadmin.tenants.show', $tenant->id); @endphp
                                                @if ($url)
                                                    <a href="{{ $url }}">{{ $tenant->name }}</a>
                                                @else
                                                    {{ $tenant->name }}
                                                @endif
                                            </td>
                                            <td>{{ $typeLabels[$tenant->type] ?? ucfirst($tenant->type) }}</td>
                                            <td>{{ $tenant->city ?? '-' }}</td>
                                            <td>{{ $tenant->users_count }}</td>
                                            <td>{{ optional($tenant->created_at)->format('d M Y') }}</td>
                                            <td>
                                                <span class="badge badge-{{ $tenant->is_active ? 'success' : 'danger' }}">
                                                    {{ $tenant->is_active ? 'Aktif' : 'Disuspend' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Belum ada tenant</td>
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
