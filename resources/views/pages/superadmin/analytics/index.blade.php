@extends('layouts.app')

@section('title', 'Analytics')

@push('style')
    <style>
        .card-statistic-1 .card-body small { font-size: 12px; font-weight: 500; }
        .chart-box { position: relative; height: 280px; }
        .chart-box-sm { position: relative; height: 240px; }
        .empty-chart { height: 100%; display: flex; align-items: center; justify-content: center; color: #98a6ad; }
    </style>
@endpush

@section('main')
    @php
        $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $link = fn(string $route, ...$params) => \Illuminate\Support\Facades\Route::has($route) ? route($route, $params) : null;

        $typeLabels = [
            'company'   => 'Perusahaan',
            'school'    => 'Sekolah',
            'pesantren' => 'Pesantren',
            'tpq'       => 'TPQ',
            'kuliah'    => 'Kampus / Kuliah',
        ];
        $subStatusLabels = [
            'trial'     => 'Trial',
            'active'    => 'Aktif (berbayar)',
            'grace'     => 'Masa toleransi',
            'expired'   => 'Kedaluwarsa',
            'cancelled' => 'Dibatalkan',
        ];
        $subStatusColors = [
            'trial'     => '#3abaf4',
            'active'    => '#47c363',
            'grace'     => '#ffa426',
            'expired'   => '#cdd3d8',
            'cancelled' => '#fc544b',
        ];
        $invStatusLabels = [
            'paid'      => 'Lunas',
            'pending'   => 'Menunggu bayar',
            'expired'   => 'Kedaluwarsa',
            'cancelled' => 'Dibatalkan',
        ];
        $invStatusBadge = [
            'paid'      => 'success',
            'pending'   => 'warning',
            'expired'   => 'secondary',
            'cancelled' => 'danger',
        ];
        $palette = ['#6777ef', '#47c363', '#3abaf4', '#ffa426', '#fc544b', '#191d21', '#cdd3d8'];

        $invoiceTotalPeriod = $invoicesByStatus->sum();

        // Data grafik
        $chart = [
            'trend' => $trend,
            'status' => [
                'labels' => $subscriptionsByStatus->keys()->map(fn($s) => $subStatusLabels[$s] ?? ucfirst($s))->values(),
                'data'   => $subscriptionsByStatus->values(),
                'colors' => $subscriptionsByStatus->keys()->map(fn($s) => $subStatusColors[$s] ?? '#cdd3d8')->values(),
            ],
            'plan' => [
                'labels' => $revenueByPlan->pluck('plan_name'),
                'data'   => $revenueByPlan->pluck('total')->map(fn($v) => (float) $v),
            ],
            'type' => [
                'labels' => $revenueByType->keys()->map(fn($t) => $typeLabels[$t] ?? ucfirst($t))->values(),
                'data'   => $revenueByType->values()->map(fn($v) => (float) $v),
            ],
            'palette' => $palette,
        ];
    @endphp

    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Analytics</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></div>
                    <div class="breadcrumb-item">Analytics</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="d-flex flex-wrap justify-content-between align-items-end mb-3">
                    <div>
                        <h2 class="section-title mt-0">Analytics &amp; Pendapatan</h2>
                        <p class="section-lead mb-0">
                            Periode: {{ $periodStart->translatedFormat('M Y') }} – {{ now()->translatedFormat('M Y') }}
                            ({{ $months }} bulan)
                        </p>
                    </div>
                    <div class="btn-group mt-2" role="group">
                        @foreach ($ranges as $range)
                            <a href="{{ route('superadmin.analytics', ['months' => $range]) }}"
                                class="btn btn-sm {{ $months === $range ? 'btn-primary' : 'btn-outline-primary' }}">
                                {{ $range }} bln
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- ── Kartu ringkasan ──────────────────────────────── --}}
                <div class="row">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-primary"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="card-wrap">
                                <div class="card-header"><h4>Pendapatan Bulan Ini</h4></div>
                                <div class="card-body">
                                    {{ $rupiah($revenueThisMonth) }}
                                    <div>
                                        @if ($revenueGrowthPercent === null)
                                            <small class="text-muted">Bulan lalu: {{ $rupiah($revenueLastMonth) }}</small>
                                        @else
                                            <small class="{{ $revenueGrowthPercent >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $revenueGrowthPercent >= 0 ? '▲' : '▼' }} {{ abs($revenueGrowthPercent) }}% dari bulan lalu
                                            </small>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-success"><i class="fas fa-chart-line"></i></div>
                            <div class="card-wrap">
                                <div class="card-header"><h4>MRR (estimasi)</h4></div>
                                <div class="card-body">
                                    {{ $rupiah($mrr) }}
                                    <div><small class="text-muted">dari langganan berbayar aktif</small></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-info"><i class="fas fa-coins"></i></div>
                            <div class="card-wrap">
                                <div class="card-header"><h4>Pendapatan {{ $months }} Bulan</h4></div>
                                <div class="card-body">
                                    {{ $rupiah($revenuePeriod) }}
                                    <div><small class="text-muted">{{ number_format($paidInvoicesPeriod) }} invoice lunas</small></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-warning"><i class="fas fa-file-invoice"></i></div>
                            <div class="card-wrap">
                                <div class="card-header"><h4>Invoice Pending</h4></div>
                                <div class="card-body">
                                    {{ number_format($pendingInvoices) }}
                                    <div><small class="{{ $overdueInvoices ? 'text-danger' : 'text-muted' }}">{{ $overdueInvoices }} lewat jatuh tempo</small></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-primary"><i class="fas fa-building"></i></div>
                            <div class="card-wrap">
                                <div class="card-header"><h4>Tenant Aktif</h4></div>
                                <div class="card-body">
                                    {{ number_format($activeTenants) }}
                                    <div><small class="text-muted">dari {{ $totalTenants }} · {{ $suspendedTenants }} disuspend</small></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-success"><i class="fas fa-user-check"></i></div>
                            <div class="card-wrap">
                                <div class="card-header"><h4>Tenant Membayar</h4></div>
                                <div class="card-body">
                                    {{ number_format($payingTenants) }}
                                    <div><small class="text-muted">dalam {{ $months }} bulan terakhir</small></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-info"><i class="fas fa-exchange-alt"></i></div>
                            <div class="card-wrap">
                                <div class="card-header"><h4>Konversi ke Berbayar</h4></div>
                                <div class="card-body">
                                    {{ $conversionRate === null ? '-' : $conversionRate . '%' }}
                                    <div><small class="text-muted">{{ $everPaid }} dari {{ $everSubscribed }} tenant pernah bayar</small></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card card-statistic-1">
                            <div class="card-icon bg-danger"><i class="fas fa-percentage"></i></div>
                            <div class="card-wrap">
                                <div class="card-header"><h4>Total Diskon</h4></div>
                                <div class="card-body">
                                    {{ $rupiah($discountPeriod) }}
                                    <div><small class="text-muted">rata-rata invoice {{ $rupiah($avgPerInvoice) }}</small></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Tren pendapatan ──────────────────────────────── --}}
                <div class="card">
                    <div class="card-header">
                        <h4>Pendapatan per Bulan</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart-box">
                            @if (array_sum($trend['revenue']) > 0)
                                <canvas id="chartRevenueTrend"></canvas>
                            @else
                                <div class="empty-chart">Belum ada pembayaran lunas di periode ini</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h4>Tenant Baru per Bulan</h4>
                            </div>
                            <div class="card-body">
                                <div class="chart-box-sm">
                                    @if (array_sum($trend['newTenants']) > 0)
                                        <canvas id="chartNewTenants"></canvas>
                                    @else
                                        <div class="empty-chart">Belum ada tenant baru di periode ini</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h4>Langganan per Status</h4>
                            </div>
                            <div class="card-body">
                                <div class="chart-box-sm">
                                    @if ($subscriptionsByStatus->sum() > 0)
                                        <canvas id="chartStatus"></canvas>
                                    @else
                                        <div class="empty-chart">Belum ada langganan</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Komposisi pendapatan ─────────────────────────── --}}
                <div class="row">
                    <div class="col-12 col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h4>Pendapatan per Paket</h4>
                            </div>
                            <div class="card-body">
                                <div class="chart-box-sm">
                                    @if ($revenueByPlan->isNotEmpty())
                                        <canvas id="chartRevenuePlan"></canvas>
                                    @else
                                        <div class="empty-chart">Belum ada data</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h4>Pendapatan per Jenis Lembaga</h4>
                            </div>
                            <div class="card-body">
                                <div class="chart-box-sm">
                                    @if ($revenueByType->isNotEmpty())
                                        <canvas id="chartRevenueType"></canvas>
                                    @else
                                        <div class="empty-chart">Belum ada data</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    {{-- Tenant per jenis --}}
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Tenant per Jenis</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped mb-0">
                                    <tbody>
                                        @forelse ($tenantsByType as $type => $total)
                                            <tr>
                                                <td>{{ $typeLabels[$type] ?? ucfirst($type) }}</td>
                                                <td class="text-right"><strong>{{ $total }}</strong></td>
                                            </tr>
                                        @empty
                                            <tr><td class="text-center text-muted">Belum ada tenant</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Invoice per status --}}
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Invoice Diterbitkan</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped mb-0">
                                    <tbody>
                                        @forelse ($invoicesByStatus as $status => $total)
                                            <tr>
                                                <td>
                                                    <span class="badge badge-{{ $invStatusBadge[$status] ?? 'secondary' }}">
                                                        {{ $invStatusLabels[$status] ?? ucfirst($status) }}
                                                    </span>
                                                </td>
                                                <td class="text-right">
                                                    <strong>{{ $total }}</strong>
                                                    <small class="text-muted">({{ $invoiceTotalPeriod ? round($total / $invoiceTotalPeriod * 100) : 0 }}%)</small>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td class="text-center text-muted">Belum ada invoice</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Voucher terlaris --}}
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Voucher Paling Sering Dipakai</h4>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped mb-0">
                                    <tbody>
                                        @forelse ($topDiscounts as $discount)
                                            <tr>
                                                <td>
                                                    {{ $discount->name }}
                                                    @if ($discount->code)
                                                        <br><code>{{ $discount->code }}</code>
                                                    @endif
                                                </td>
                                                <td class="text-right">
                                                    <strong>{{ $discount->used }}x</strong>
                                                    <br><small class="text-muted">{{ $rupiah($discount->total_discount) }}</small>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td class="text-center text-muted">Belum ada voucher terpakai</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Tenant pembayar terbesar ────────────────────── --}}
                <div class="card">
                    <div class="card-header">
                        <h4>10 Tenant dengan Pembayaran Terbesar</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Tenant</th>
                                        <th>Jenis</th>
                                        <th class="text-right">Invoice Lunas</th>
                                        <th class="text-right">Total Bayar</th>
                                        <th>Terakhir Bayar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($topTenants as $i => $tenant)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>
                                                @php $url = $link('superadmin.tenants.show', $tenant->id); @endphp
                                                @if ($url)
                                                    <a href="{{ $url }}">{{ $tenant->name }}</a>
                                                @else
                                                    {{ $tenant->name }}
                                                @endif
                                            </td>
                                            <td>{{ $typeLabels[$tenant->type] ?? ucfirst($tenant->type) }}</td>
                                            <td class="text-right">{{ $tenant->invoices }}</td>
                                            <td class="text-right"><strong>{{ $rupiah($tenant->total) }}</strong></td>
                                            <td>{{ \Carbon\Carbon::parse($tenant->last_paid_at)->format('d M Y') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Belum ada pembayaran di periode ini</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <p class="text-muted small">
                    <i class="fas fa-info-circle"></i>
                    MRR = jumlah harga paket dari langganan berstatus <b>Aktif (berbayar)</b>, dinormalisasi ke 30 hari.
                    Paket tahunan Rp1.200.000 dihitung Rp98.630/bulan. Langganan trial tidak dihitung.
                </p>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <script>
        (function () {
            const data = @json($chart);
            const rupiah = v => 'Rp ' + Number(v).toLocaleString('id-ID');
            const shortRupiah = v => {
                if (v >= 1e9) return 'Rp ' + (v / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M';
                if (v >= 1e6) return 'Rp ' + (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
                if (v >= 1e3) return 'Rp ' + (v / 1e3).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' rb';
                return 'Rp ' + v;
            };

            Chart.defaults.font.family = "'Nunito', 'Segoe UI', Arial, sans-serif";
            Chart.defaults.color = '#6c757d';
            Chart.defaults.maintainAspectRatio = false;

            const grid = { color: 'rgba(0,0,0,.05)' };
            const el = id => document.getElementById(id);

            if (el('chartRevenueTrend')) {
                new Chart(el('chartRevenueTrend'), {
                    data: {
                        labels: data.trend.labels,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Pendapatan',
                                data: data.trend.revenue,
                                backgroundColor: '#6777ef',
                                borderRadius: 4,
                                yAxisID: 'y',
                            },
                            {
                                type: 'line',
                                label: 'Invoice lunas',
                                data: data.trend.paidCount,
                                borderColor: '#47c363',
                                backgroundColor: '#47c363',
                                tension: 0.3,
                                yAxisID: 'y1',
                            },
                        ],
                    },
                    options: {
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: c => c.dataset.yAxisID === 'y'
                                        ? ' Pendapatan: ' + rupiah(c.parsed.y)
                                        : ' Invoice lunas: ' + c.parsed.y,
                                },
                            },
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, grid, ticks: { callback: shortRupiah } },
                            y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { precision: 0 } },
                        },
                    },
                });
            }

            if (el('chartNewTenants')) {
                new Chart(el('chartNewTenants'), {
                    type: 'line',
                    data: {
                        labels: data.trend.labels,
                        datasets: [{
                            label: 'Tenant baru',
                            data: data.trend.newTenants,
                            borderColor: '#3abaf4',
                            backgroundColor: 'rgba(58,186,244,.15)',
                            fill: true,
                            tension: 0.3,
                        }],
                    },
                    options: {
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false } },
                            y: { beginAtZero: true, grid, ticks: { precision: 0 } },
                        },
                    },
                });
            }

            if (el('chartStatus')) {
                new Chart(el('chartStatus'), {
                    type: 'doughnut',
                    data: {
                        labels: data.status.labels,
                        datasets: [{ data: data.status.data, backgroundColor: data.status.colors, borderWidth: 2 }],
                    },
                    options: {
                        cutout: '62%',
                        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                    },
                });
            }

            if (el('chartRevenuePlan')) {
                new Chart(el('chartRevenuePlan'), {
                    type: 'bar',
                    data: {
                        labels: data.plan.labels,
                        datasets: [{
                            label: 'Pendapatan',
                            data: data.plan.data,
                            backgroundColor: '#6777ef',
                            borderRadius: 4,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: c => ' ' + rupiah(c.parsed.x) } },
                        },
                        scales: {
                            x: { beginAtZero: true, grid, ticks: { callback: shortRupiah } },
                            y: { grid: { display: false } },
                        },
                    },
                });
            }

            if (el('chartRevenueType')) {
                new Chart(el('chartRevenueType'), {
                    type: 'doughnut',
                    data: {
                        labels: data.type.labels,
                        datasets: [{ data: data.type.data, backgroundColor: data.palette, borderWidth: 2 }],
                    },
                    options: {
                        cutout: '62%',
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12 } },
                            tooltip: { callbacks: { label: c => ' ' + c.label + ': ' + rupiah(c.parsed) } },
                        },
                    },
                });
            }
        })();
    </script>
@endpush
